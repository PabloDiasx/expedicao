<?php

namespace App\Http\Controllers;

use App\Enums\EventSource;
use App\Enums\TransitionResult;
use App\Support\Invoices\InvoiceSerialLookupService;
use App\Support\Operations\EquipmentStatusService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExpeditionController extends Controller
{
    public function index(TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        $recentDispatches = DB::table('status_histories as sh')
            ->join('equipments as e', 'e.id', '=', 'sh.equipment_id')
            ->join('statuses as st', 'st.id', '=', 'sh.to_status_id')
            ->leftJoin('users as u', 'u.id', '=', 'sh.user_id')
            ->where('sh.tenant_id', $tenant->id)
            ->where('sh.event_source', EventSource::ScannerExpedicao->value)
            ->orderByDesc('sh.changed_at')
            ->limit(15)
            ->get([
                'e.serial_number',
                'e.barcode',
                'e.entry_invoice_number',
                'st.name as status_name',
                'st.color as status_color',
                'u.name as user_name',
                'sh.changed_at',
                'sh.notes',
            ]);

        return view('expedition.index', [
            'recentDispatches' => $recentDispatches,
        ]);
    }

    public function store(
        Request $request,
        TenantContext $tenantContext,
        EquipmentStatusService $statusService,
        InvoiceSerialLookupService $invoiceSerialLookupService
    ): RedirectResponse {
        $tenant = $tenantContext->tenant();
        if (! $tenant) {
            return back()->withErrors([
                'barcode' => 'Tenant nao identificado.',
            ]);
        }

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:120'],
            'device_identifier' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $validated = $this->prepareBarcodeInput((int) $tenant->id, $validated, $statusService);
        $invoiceLookup = $invoiceSerialLookupService->findBySerial(
            (int) $tenant->id,
            (string) $validated['barcode']
        );

        if (($invoiceLookup['multiple'] ?? false) === true) {
            return back()->withInput($validated)->with('swal', [
                'icon' => 'warning',
                'title' => 'Mais de uma nota encontrada',
                'text' => 'Existe mais de uma NF para este serial. Revise as notas antes de registrar a entrada.',
            ]);
        }

        $dispatchStatusId = DB::table('statuses')
            ->where('code', 'carregado')
            ->value('id');

        $dispatchSectorId = DB::table('sectors')
            ->where('code', 'expedicao')
            ->where('is_active', true)
            ->value('id');

        if (! $dispatchStatusId || ! $dispatchSectorId) {
            return back()->withErrors([
                'barcode' => 'Configuracao de entrada incompleta. Verifique status "Carregado" e setor padrao.',
            ])->withInput();
        }

        $ensuredEquipment = $this->ensureEntryEquipmentExists(
            tenantId: (int) $tenant->id,
            preparedInput: $validated,
            statusId: (int) $dispatchStatusId,
            sectorId: (int) $dispatchSectorId
        );
        if (! ($ensuredEquipment['ok'] ?? false)) {
            return back()->withInput($validated)->with('swal', [
                'icon' => 'warning',
                'title' => 'Modelo nao identificado',
                'text' => (string) ($ensuredEquipment['error'] ?? 'Nao foi possivel cadastrar o equipamento na entrada.'),
            ]);
        }
        $equipmentCreated = (bool) ($ensuredEquipment['created'] ?? false);

        $result = $statusService->applyBarcodeTransition(
            tenantId: (int) $tenant->id,
            userId: auth()->id(),
            barcode: (string) $validated['barcode'],
            toStatusId: (int) $dispatchStatusId,
            sectorId: (int) $dispatchSectorId,
            deviceIdentifier: $validated['device_identifier'] ?? null,
            notes: $validated['notes'] ?? null,
            eventSource: EventSource::ScannerExpedicao->value
        );

        if ($result['result'] === TransitionResult::NotFound->value) {
            $result = $statusService->applyBarcodeTransition(
                tenantId: (int) $tenant->id,
                userId: auth()->id(),
                barcode: (string) ($validated['barcode_original'] ?? $validated['barcode']),
                toStatusId: (int) $dispatchStatusId,
                sectorId: (int) $dispatchSectorId,
                deviceIdentifier: $validated['device_identifier'] ?? null,
                notes: $validated['notes'] ?? null,
                eventSource: EventSource::ScannerExpedicao->value
            );
        }

        if ($result['result'] === TransitionResult::NotFound->value) {
            $ensuredEquipmentId = isset($ensuredEquipment['equipment_id']) ? (int) $ensuredEquipment['equipment_id'] : 0;
            if ($ensuredEquipmentId > 0) {
                $result = [
                    'result' => TransitionResult::NoChange->value,
                    'equipment_id' => $ensuredEquipmentId,
                    'serial_number' => (string) $validated['barcode'],
                    'status_name' => 'Carregado',
                    'status_code' => 'carregado',
                ];
            } else {
                return back()->withInput($validated)->with('swal', [
                    'icon' => 'error',
                    'title' => 'Entrada nao concluida',
                    'text' => 'Nao foi possivel localizar ou cadastrar o equipamento para a entrada.',
                ]);
            }
        }

        if (! in_array($result['result'], [TransitionResult::Updated->value, TransitionResult::NoChange->value], true)) {
            return back()->withErrors([
                'barcode' => 'Nao foi possivel concluir a entrada.',
            ])->withInput($validated);
        }

        $equipmentId = isset($result['equipment_id']) ? (int) $result['equipment_id'] : 0;
        $serialNumber = (string) ($result['serial_number'] ?? '');

        if ($equipmentId > 0) {
            $now = now();
            $invoiceUpdate = [
                'updated_at' => $now,
            ];

            if (($invoiceLookup['matched'] ?? false) === true) {
                $invoiceUpdate['entry_invoice_id'] = $invoiceLookup['invoice_id'];
                $invoiceUpdate['entry_invoice_external_id'] = $invoiceLookup['invoice_external_id'];
                $invoiceUpdate['entry_invoice_number'] = $invoiceLookup['invoice_number'];
                $invoiceUpdate['entry_customer_name'] = $invoiceLookup['customer_name'];
                $invoiceUpdate['entry_destination'] = $invoiceLookup['destination'];
                $invoiceUpdate['entry_invoice_linked_at'] = $now;
            } else {
                $invoiceUpdate['entry_invoice_id'] = null;
                $invoiceUpdate['entry_invoice_external_id'] = null;
                $invoiceUpdate['entry_invoice_number'] = null;
                $invoiceUpdate['entry_customer_name'] = null;
                $invoiceUpdate['entry_destination'] = null;
                $invoiceUpdate['entry_invoice_linked_at'] = null;
            }

            DB::table('equipments')
                ->where('tenant_id', (int) $tenant->id)
                ->where('id', $equipmentId)
                ->update($invoiceUpdate);
        }

        if (($invoiceLookup['matched'] ?? false) === true) {
            $message = 'Equipamento '.$result['serial_number'].' registrado na entrada';
            if ($equipmentCreated) {
                $message .= ' e cadastrado automaticamente';
            }
            $message .= ' com NF '.$invoiceLookup['invoice_number'].'.';
            if (! empty($invoiceLookup['customer_name'])) {
                $message .= ' Cliente: '.$invoiceLookup['customer_name'].'.';
            }
            if (! empty($invoiceLookup['destination'])) {
                $message .= ' Destino: '.$invoiceLookup['destination'].'.';
            }

            return redirect()->route('equipments.index', [
                'q' => $serialNumber,
            ])->with('swal', [
                'icon' => 'success',
                'title' => 'Entrada registrada',
                'text' => $message,
            ]);
        }

        return redirect()->route('equipments.index', [
            'q' => $serialNumber,
        ])->with('swal', [
            'icon' => 'info',
            'title' => 'Entrada registrada sem NF',
            'text' => 'Equipamento '.$result['serial_number'].' registrado na entrada'.($equipmentCreated ? ' e cadastrado automaticamente' : '').'. NF ainda nao encontrada para este serial.',
        ]);
    }

    public function lookupInvoice(
        Request $request,
        TenantContext $tenantContext,
        InvoiceSerialLookupService $invoiceSerialLookupService,
        EquipmentStatusService $statusService
    ): JsonResponse {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:120'],
        ]);

        $prepared = $this->prepareBarcodeInput((int) $tenant->id, [
            'barcode' => (string) $validated['barcode'],
            'notes' => null,
        ], $statusService);

        $serial = (string) $prepared['barcode'];
        $invoiceLookup = $invoiceSerialLookupService->findBySerial((int) $tenant->id, $serial);
        $finalDigits = $this->extractFinalDigitsFromSerial($serial);

        $hasSingleMatch = ($invoiceLookup['matched'] ?? false) === true
            && ($invoiceLookup['multiple'] ?? false) === false;

        return response()->json([
            'barcode_original' => (string) ($prepared['barcode_original'] ?? ''),
            'barcode_convertido' => $serial,
            'serial_final' => $finalDigits,
            'houve_conversao' => ($prepared['converted_serial'] ?? null) !== null,
            'invoice' => [
                'found' => $hasSingleMatch,
                'multiple' => (bool) ($invoiceLookup['multiple'] ?? false),
                'matched_count' => (int) ($invoiceLookup['matched_count'] ?? 0),
                'numero' => $hasSingleMatch ? ($invoiceLookup['invoice_number'] ?? null) : null,
                'cliente' => $hasSingleMatch ? ($invoiceLookup['customer_name'] ?? null) : null,
                'destino' => $hasSingleMatch ? ($invoiceLookup['destination'] ?? null) : null,
            ],
        ]);
    }

    /**
     * @param  array{barcode:string, device_identifier?:string|null, notes?:string|null}  $validated
     * @return array{
     *     barcode:string,
     *     barcode_original:string,
     *     converted_serial:string|null,
     *     device_identifier?:string|null,
     *     notes?:string|null
     * }
     */
    private function prepareBarcodeInput(int $tenantId, array $validated, EquipmentStatusService $statusService): array
    {
        $rawBarcode = trim((string) ($validated['barcode'] ?? ''));
        $normalizedBarcode = mb_strtoupper($rawBarcode);
        $normalizedBarcode = preg_replace('/\s+/', '', $normalizedBarcode) ?? $normalizedBarcode;
        $validated['barcode_original'] = $normalizedBarcode;

        $convertedSerial = $statusService->convertScannerCode($tenantId, $normalizedBarcode);
        $validated['converted_serial'] = $convertedSerial;
        if ($convertedSerial === null || $convertedSerial === $normalizedBarcode) {
            $validated['barcode'] = $normalizedBarcode;

            return $validated;
        }

        $validated['barcode'] = $convertedSerial;

        return $validated;
    }

    /**
     * @param  array{
     *     barcode:string,
     *     barcode_original:string,
     *     converted_serial:string|null,
     *     device_identifier?:string|null,
     *     notes?:string|null
     * }  $preparedInput
     * @return array{
     *     ok: bool,
     *     created: bool,
     *     equipment_id: int|null,
     *     error?: string
     * }
     */
    private function ensureEntryEquipmentExists(
        int $tenantId,
        array $preparedInput,
        int $statusId,
        int $sectorId
    ): array {
        $serial = trim((string) ($preparedInput['barcode'] ?? ''));
        $barcodeOriginal = trim((string) ($preparedInput['barcode_original'] ?? $serial));

        if ($serial === '') {
            return [
                'ok' => false,
                'created' => false,
                'equipment_id' => null,
                'error' => 'Codigo de barras invalido para cadastro na entrada.',
            ];
        }

        $serialPrefix = $this->extractSerialPrefix($serial);
        $modelId = $this->resolveModelForSerialPrefix($tenantId, $serialPrefix);

        if (! $modelId) {
            return [
                'ok' => false,
                'created' => false,
                'equipment_id' => null,
                'error' => $serialPrefix !== null
                    ? 'Nao existe modelo ativo para o prefixo '.$serialPrefix.'. Cadastre o modelo em "Modelos".'
                    : 'Nao foi possivel identificar o modelo pelo serial lido.',
            ];
        }

        return DB::transaction(function () use ($tenantId, $serial, $barcodeOriginal, $modelId, $statusId, $sectorId): array {
            $existing = DB::table('equipments')
                ->where('tenant_id', $tenantId)
                ->where(function ($query) use ($serial, $barcodeOriginal): void {
                    $query->where('serial_number', $serial)
                        ->orWhere('barcode', $serial)
                        ->orWhere('barcode', $barcodeOriginal);
                })
                ->lockForUpdate()
                ->first(['id']);

            if ($existing) {
                return [
                    'ok' => true,
                    'created' => false,
                    'equipment_id' => (int) $existing->id,
                ];
            }

            $now = now();

            $equipmentId = (int) DB::table('equipments')->insertGetId([
                'tenant_id' => $tenantId,
                'equipment_model_id' => $modelId,
                'serial_number' => $serial,
                'barcode' => $barcodeOriginal,
                'current_status_id' => $statusId,
                'current_sector_id' => $sectorId,
                'manufactured_at' => null,
                'assembled_at' => null,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'ok' => true,
                'created' => true,
                'equipment_id' => $equipmentId,
            ];
        });
    }

    private function extractSerialPrefix(string $serial): ?string
    {
        if (preg_match('/^([A-Z]+[0-9]+)/', strtoupper(trim($serial)), $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function resolveModelForSerialPrefix(int $tenantId, ?string $serialPrefix): ?int
    {
        if ($serialPrefix === null || $serialPrefix === '') {
            return null;
        }

        $prefix = strtoupper($serialPrefix);

        $barcodePrefixMatch = DB::table('equipment_models')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereRaw('UPPER(COALESCE(barcode_prefix, "")) = ?', [$prefix])
            ->value('id');
        if ($barcodePrefixMatch) {
            return (int) $barcodePrefixMatch;
        }

        $codeMatch = DB::table('equipment_models')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereRaw('UPPER(code) = ?', [$prefix])
            ->value('id');
        if ($codeMatch) {
            return (int) $codeMatch;
        }

        $nameMatch = DB::table('equipment_models')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereRaw('UPPER(name) like ?', [$prefix.'%'])
            ->orderByRaw('CASE WHEN UPPER(name) = ? THEN 0 ELSE 1 END', [$prefix])
            ->orderBy('id')
            ->value('id');
        if ($nameMatch) {
            return (int) $nameMatch;
        }

        $categoryMatch = DB::table('equipment_models')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereRaw('UPPER(COALESCE(category, "")) = ?', [$prefix])
            ->value('id');
        if ($categoryMatch) {
            return (int) $categoryMatch;
        }

        return null;
    }

    private function extractFinalDigitsFromSerial(string $serial): ?string
    {
        if (preg_match('/\.([0-9]{1,8})$/', $serial, $matches) !== 1) {
            return null;
        }

        $digits = ltrim((string) $matches[1], '0');

        return $digits === '' ? '0' : $digits;
    }
}
