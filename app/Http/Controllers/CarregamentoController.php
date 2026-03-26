<?php

namespace App\Http\Controllers;

use App\Models\FiscalInvoice;
use App\Support\Invoices\InvoiceSerialLookupService;
use App\Support\Operations\EquipmentStatusService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CarregamentoController extends Controller
{
    public function store(Request $request, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        $validated = $request->validate([
            'fiscal_invoice_id' => ['required', 'integer'],
            'motorista_nome' => ['required', 'string', 'max:150'],
            'motorista_documento' => ['required', 'string', 'max:30'],
            'motorista_empresa' => ['nullable', 'string', 'max:150'],
            'placa_veiculo' => ['required', 'string', 'max:15'],
        ]);

        $invoice = FiscalInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', (int) $validated['fiscal_invoice_id'])
            ->first();

        if (! $invoice) {
            return back()->with('swal', [
                'icon' => 'error',
                'title' => 'Erro',
                'text' => 'Nota fiscal nao encontrada.',
            ]);
        }

        $carregamentoId = DB::table('carregamentos')->insertGetId([
            'tenant_id' => $tenant->id,
            'fiscal_invoice_id' => $invoice->id,
            'user_id' => auth()->id(),
            'motorista_nome' => $validated['motorista_nome'],
            'motorista_documento' => $validated['motorista_documento'],
            'motorista_empresa' => $validated['motorista_empresa'] ?? null,
            'placa_veiculo' => mb_strtoupper(trim($validated['placa_veiculo'])),
            'status' => 'aberto',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Extract serial numbers from NF XML
        $serials = $this->extractSerialsFromInvoice($invoice);

        $now = now();
        foreach ($serials as $serial) {
            // Try to find existing equipment
            $equipment = DB::table('equipments')
                ->where('tenant_id', $tenant->id)
                ->where('serial_number', $serial)
                ->first(['id']);

            DB::table('carregamento_items')->insert([
                'carregamento_id' => $carregamentoId,
                'equipment_id' => $equipment?->id,
                'serial_number' => $serial,
                'conferido' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        \App\Support\Webhooks\WebhookDispatcher::dispatch((int) $tenant->id, 'carregamento_criado', [
            'carregamento_id' => $carregamentoId,
            'invoice_number' => $invoice->numero ?? null,
            'motorista_nome' => $validated['motorista_nome'],
            'motorista_documento' => $validated['motorista_documento'],
            'placa_veiculo' => mb_strtoupper(trim($validated['placa_veiculo'])),
            'motorista_empresa' => $validated['motorista_empresa'] ?? null,
            'total_items' => count($serials),
            'user_name' => auth()->user()->name ?? null,
            'timestamp' => now()->toIso8601String(),
        ]);

        return redirect()->route('carregamentos.show', ['carregamento' => $carregamentoId]);
    }

    public function show(int $carregamento, TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        $row = DB::table('carregamentos as c')
            ->join('fiscal_invoices as fi', 'fi.id', '=', 'c.fiscal_invoice_id')
            ->where('c.tenant_id', $tenant->id)
            ->where('c.id', $carregamento)
            ->first([
                'c.*',
                'fi.numero as invoice_numero',
            ]);

        abort_unless($row, 404);

        $items = DB::table('carregamento_items as ci')
            ->leftJoin('equipments as e', 'e.id', '=', 'ci.equipment_id')
            ->leftJoin('statuses as st', 'st.id', '=', 'e.current_status_id')
            ->where('ci.carregamento_id', $carregamento)
            ->orderBy('ci.conferido')
            ->orderBy('ci.serial_number')
            ->get([
                'ci.id as item_id',
                'ci.serial_number',
                'ci.conferido',
                'ci.conferido_at',
                'ci.barcode_scanned',
                'ci.equipment_id',
                'e.barcode',
                'st.name as status_name',
                'st.code as status_code',
                'st.color as status_color',
            ]);

        $totalItems = $items->count();
        $totalConferidos = $items->where('conferido', true)->count();

        return view('expedition.carregamento-show', [
            'carregamento' => $row,
            'items' => $items,
            'totalItems' => $totalItems,
            'totalConferidos' => $totalConferidos,
        ]);
    }

    public function scan(
        Request $request,
        int $carregamento,
        TenantContext $tenantContext,
        EquipmentStatusService $statusService
    ): JsonResponse {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:120'],
        ]);

        $row = DB::table('carregamentos')
            ->where('tenant_id', $tenant->id)
            ->where('id', $carregamento)
            ->first(['id', 'fiscal_invoice_id']);

        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'Carregamento nao encontrado.'], 404);
        }

        // Convert barcode
        $rawBarcode = mb_strtoupper(trim($validated['barcode']));
        $rawBarcode = preg_replace('/\s+/', '', $rawBarcode) ?? $rawBarcode;
        $convertedSerial = $statusService->convertScannerCode((int) $tenant->id, $rawBarcode);
        $serial = $convertedSerial ?? $rawBarcode;

        // Find matching item in this carregamento
        $item = DB::table('carregamento_items as ci')
            ->leftJoin('equipments as e', 'e.id', '=', 'ci.equipment_id')
            ->where('ci.carregamento_id', $row->id)
            ->where(function ($q) use ($serial, $rawBarcode) {
                $q->where('ci.serial_number', $serial)
                    ->orWhere('e.barcode', $rawBarcode)
                    ->orWhere('e.serial_number', $serial);
            })
            ->first(['ci.id', 'ci.conferido', 'ci.serial_number', 'ci.equipment_id', 'e.current_status_id']);

        if (! $item) {
            return response()->json([
                'ok' => false,
                'type' => 'divergente',
                'message' => 'Aparelho ' . $serial . ' NAO pertence a esta nota fiscal. Equipamento divergente!',
            ]);
        }

        if ($item->conferido) {
            return response()->json([
                'ok' => false,
                'type' => 'duplicado',
                'message' => 'Aparelho ' . $item->serial_number . ' ja foi conferido neste carregamento.',
            ]);
        }

        // Check status is "liberado"
        $statusLiberado = DB::table('statuses')->where('code', 'liberado')->value('id');
        if ($statusLiberado && (int) $item->current_status_id !== (int) $statusLiberado) {
            $currentStatus = DB::table('statuses')->where('id', $item->current_status_id)->value('name');
            return response()->json([
                'ok' => false,
                'type' => 'status_invalido',
                'message' => 'Aparelho ' . $item->serial_number . ' esta com status "' . ($currentStatus ?? '?') . '". Precisa estar Liberado para carregar.',
            ]);
        }

        // Mark as conferido and update equipment status to "carregando"
        $now = now();
        DB::table('carregamento_items')
            ->where('id', $item->id)
            ->update([
                'conferido' => true,
                'conferido_at' => $now,
                'barcode_scanned' => $rawBarcode,
                'updated_at' => $now,
            ]);

        $statusCarregando = DB::table('statuses')->where('code', 'carregando')->value('id');
        if ($statusCarregando && $item->equipment_id) {
            DB::table('equipments')
                ->where('id', $item->equipment_id)
                ->update([
                    'current_status_id' => $statusCarregando,
                    'updated_at' => $now,
                ]);

            $tenantId = DB::table('equipments')->where('id', $item->equipment_id)->value('tenant_id');
            DB::table('status_histories')->insert([
                'tenant_id' => $tenantId,
                'equipment_id' => $item->equipment_id,
                'from_status_id' => $item->current_status_id,
                'to_status_id' => $statusCarregando,
                'sector_id' => null,
                'user_id' => auth()->id(),
                'event_source' => 'carregamento',
                'notes' => 'Conferido no carregamento #' . $row->id,
                'changed_at' => $now,
                'created_at' => $now,
            ]);
        }

        $tenantIdForWebhook = (int) DB::table('carregamentos')->where('id', $row->id)->value('tenant_id');
        \App\Support\Webhooks\WebhookDispatcher::dispatch($tenantIdForWebhook, 'equipamento_conferido', [
            'serial_number' => $item->serial_number,
            'carregamento_id' => $row->id,
            'invoice_number' => DB::table('carregamentos as c')->join('fiscal_invoices as fi', 'fi.id', '=', 'c.fiscal_invoice_id')->where('c.id', $row->id)->value('fi.numero'),
            'barcode_scanned' => $rawBarcode,
            'user_name' => auth()->user()->name ?? null,
            'timestamp' => $now->toIso8601String(),
        ]);

        // Count progress
        $total = DB::table('carregamento_items')->where('carregamento_id', $row->id)->count();
        $conferidos = DB::table('carregamento_items')->where('carregamento_id', $row->id)->where('conferido', true)->count();

        return response()->json([
            'ok' => true,
            'message' => $item->serial_number . ' conferido com sucesso!',
            'serial' => $item->serial_number,
            'total' => $total,
            'conferidos' => $conferidos,
        ]);
    }

    public function update(Request $request, int $carregamento, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        $validated = $request->validate([
            'motorista_nome' => ['required', 'string', 'max:150'],
            'motorista_documento' => ['required', 'string', 'max:30'],
            'motorista_empresa' => ['nullable', 'string', 'max:150'],
            'placa_veiculo' => ['required', 'string', 'max:15'],
        ]);

        $row = DB::table('carregamentos')
            ->where('tenant_id', $tenant->id)
            ->where('id', $carregamento)
            ->first(['id']);

        if (! $row) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Carregamento nao encontrado.']);
        }

        DB::table('carregamentos')
            ->where('id', $row->id)
            ->update([
                'motorista_nome' => $validated['motorista_nome'],
                'motorista_documento' => $validated['motorista_documento'],
                'motorista_empresa' => $validated['motorista_empresa'] ?? null,
                'placa_veiculo' => mb_strtoupper(trim($validated['placa_veiculo'])),
                'updated_at' => now(),
            ]);

        return back()->with('swal', ['icon' => 'success', 'title' => 'Atualizado', 'text' => 'Carregamento atualizado.']);
    }

    public function destroy(int $carregamento, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        $row = DB::table('carregamentos')
            ->where('tenant_id', $tenant->id)
            ->where('id', $carregamento)
            ->first(['id']);

        if (! $row) {
            return back()->with('swal', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Carregamento nao encontrado.']);
        }

        DB::table('carregamento_items')->where('carregamento_id', $row->id)->delete();
        DB::table('carregamentos')->where('id', $row->id)->delete();

        return redirect()->route('expedition.index', ['etapa' => 'carregamento'])->with('swal', [
            'icon' => 'success',
            'title' => 'Removido',
            'text' => 'Carregamento removido com sucesso.',
        ]);
    }

    public function finalizar(int $carregamento, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        $row = DB::table('carregamentos')
            ->where('tenant_id', $tenant->id)
            ->where('id', $carregamento)
            ->first(['id']);

        if (! $row) {
            return back()->with('swal', [
                'icon' => 'error',
                'title' => 'Erro',
                'text' => 'Carregamento nao encontrado.',
            ]);
        }

        $pending = DB::table('carregamento_items')
            ->where('carregamento_id', $row->id)
            ->where('conferido', false)
            ->count();

        if ($pending > 0) {
            return back()->with('swal', [
                'icon' => 'warning',
                'title' => 'Itens pendentes',
                'text' => 'Ainda existem ' . $pending . ' equipamentos nao conferidos.',
            ]);
        }

        $now = now();

        DB::table('carregamentos')
            ->where('id', $row->id)
            ->update([
                'status' => 'concluido',
                'updated_at' => $now,
            ]);

        // Update all equipments to "carregado" with history
        $statusCarregado = DB::table('statuses')->where('code', 'carregado')->value('id');
        if ($statusCarregado) {
            $items = DB::table('carregamento_items as ci')
                ->join('equipments as e', 'e.id', '=', 'ci.equipment_id')
                ->where('ci.carregamento_id', $row->id)
                ->whereNotNull('ci.equipment_id')
                ->get(['e.id as equipment_id', 'e.tenant_id', 'e.current_status_id']);

            foreach ($items as $eq) {
                DB::table('equipments')
                    ->where('id', $eq->equipment_id)
                    ->update([
                        'current_status_id' => $statusCarregado,
                        'updated_at' => $now,
                    ]);

                DB::table('status_histories')->insert([
                    'tenant_id' => $eq->tenant_id,
                    'equipment_id' => $eq->equipment_id,
                    'from_status_id' => $eq->current_status_id,
                    'to_status_id' => $statusCarregado,
                    'sector_id' => null,
                    'user_id' => auth()->id(),
                    'event_source' => 'carregamento',
                    'notes' => 'Carregamento #' . $row->id . ' finalizado',
                    'changed_at' => $now,
                    'created_at' => $now,
                ]);
            }
        }

        $invoiceNumero = DB::table('fiscal_invoices')->where('id', $row->fiscal_invoice_id)->value('numero');
        \App\Support\Webhooks\WebhookDispatcher::dispatch((int) $tenant->id, 'carregamento_finalizado', [
            'carregamento_id' => $row->id,
            'invoice_number' => $invoiceNumero,
            'motorista_nome' => DB::table('carregamentos')->where('id', $row->id)->value('motorista_nome'),
            'placa_veiculo' => DB::table('carregamentos')->where('id', $row->id)->value('placa_veiculo'),
            'total_items' => $items->count(),
            'user_name' => auth()->user()->name ?? null,
            'timestamp' => $now->toIso8601String(),
        ]);

        return redirect()->route('expedition.index', ['etapa' => 'carregamento'])->with('swal', [
            'icon' => 'success',
            'title' => 'Carregamento finalizado',
            'text' => 'Todos os equipamentos foram conferidos e carregados.',
        ]);
    }

    /**
     * Extract serial numbers from invoice XML payload.
     * Only returns serials that match the pattern MODEL.YY.SERIAL (e.g. V8X.26.679).
     *
     * @return array<int, string>
     */
    private function extractSerialsFromInvoice(FiscalInvoice $invoice): array
    {
        $payload = $invoice->payload;
        $xmlRaw = is_array($payload) ? ($payload['xml'] ?? null) : null;

        if (! is_string($xmlRaw) || trim($xmlRaw) === '') {
            return [];
        }

        try {
            $doc = new \DOMDocument();
            $loaded = $doc->loadXML($xmlRaw, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            if (! $loaded) {
                return [];
            }

            $xpath = new \DOMXPath($doc);
            $serials = [];

            $itemNodes = $xpath->query('//*[local-name()="det"]');
            if ($itemNodes === false) {
                return [];
            }

            foreach ($itemNodes as $itemNode) {
                if (! $itemNode instanceof \DOMElement) {
                    continue;
                }

                $infoAdicional = $this->xpathText($xpath, './/*[local-name()="infAdProd"]', $itemNode);
                $descricao = $this->xpathText($xpath, './/*[local-name()="prod"]/*[local-name()="xProd"]', $itemNode);

                $found = array_merge(
                    $this->matchSerials($infoAdicional),
                    $this->matchSerials($descricao)
                );

                foreach ($found as $s) {
                    $serials[$s] = true;
                }
            }

            return array_values(array_keys($serials));
        } catch (\Throwable) {
            return [];
        }
    }

    private function xpathText(\DOMXPath $xpath, string $path, ?\DOMNode $context = null): string
    {
        $nodes = $xpath->query($path, $context);
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }

        return trim((string) $nodes->item(0)?->textContent);
    }

    /**
     * @return array<int, string>
     */
    private function matchSerials(string $text): array
    {
        $normalized = mb_strtoupper(trim($text));
        if ($normalized === '') {
            return [];
        }

        $serials = [];

        // Pattern: "No SERIE V8X.26.679" or "SERIE: V8X.26.679"
        if (preg_match_all('/\bN\s*[Oo]?\s*\.?\s*SERIE\s*[:\-]?\s*([A-Z0-9][A-Z0-9.\-\/]*)/', $normalized, $m)) {
            foreach ($m[1] as $s) {
                $s = trim($s);
                if ($s !== '' && preg_match('/^[A-Z0-9]+\.[0-9]{2}\.[0-9]+$/', $s)) {
                    $serials[] = $s;
                }
            }
        }

        // Direct pattern: V8X.26.679
        if (preg_match_all('/\b[A-Z]+[0-9]*[A-Z]*\.[0-9]{2}\.[0-9]{1,8}\b/', $normalized, $m2)) {
            foreach ($m2[0] as $s) {
                $serials[] = trim($s);
            }
        }

        // Multiple serials separated by ; (e.g. "V5P.26.1039; V5P.26.1040")
        if (str_contains($normalized, ';')) {
            $parts = explode(';', $normalized);
            foreach ($parts as $part) {
                $part = trim($part);
                if (preg_match('/^[A-Z0-9]+\.[0-9]{2}\.[0-9]+$/', $part)) {
                    $serials[] = $part;
                }
            }
        }

        return array_values(array_unique($serials));
    }
}
