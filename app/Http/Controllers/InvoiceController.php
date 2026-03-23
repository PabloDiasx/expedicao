<?php

namespace App\Http\Controllers;

use App\Models\FiscalInvoice;
use App\Support\Nomus\NomusApiClient;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class InvoiceController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = FiscalInvoice::query()
            ->where('tenant_id', $tenant->id);

        $search = trim((string) ($validated['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('numero', 'like', '%'.$search.'%')
                    ->orWhere('serie', 'like', '%'.$search.'%')
                    ->orWhere('chave', 'like', '%'.$search.'%')
                    ->orWhere('cnpj_emitente', 'like', '%'.$search.'%');
            });
        }

        if (! empty($validated['from'])) {
            $query->whereDate('nomus_updated_at', '>=', Carbon::parse($validated['from'])->toDateString());
        }

        if (! empty($validated['to'])) {
            $query->whereDate('nomus_updated_at', '<=', Carbon::parse($validated['to'])->toDateString());
        }

        $invoices = $query
            ->orderByDesc('nomus_updated_at')
            ->orderByDesc('external_id')
            ->paginate(30)
            ->withQueryString();

        $invoiceSummaries = [];
        foreach ($invoices as $invoice) {
            $invoiceSummaries[$invoice->id] = $this->extractListSummary($invoice);
        }

        return view('invoices.index', [
            'invoices' => $invoices,
            'invoiceSummaries' => $invoiceSummaries,
            'filters' => [
                'q' => $search,
                'from' => isset($validated['from']) ? (string) $validated['from'] : '',
                'to' => isset($validated['to']) ? (string) $validated['to'] : '',
            ],
        ]);
    }

    public function show(
        FiscalInvoice $invoice,
        TenantContext $tenantContext,
        NomusApiClient $nomusApiClient
    ): View {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant && $invoice->tenant_id === $tenant->id, 404);

        $nomusDetails = is_array($invoice->payload) ? $invoice->payload : [];
        $danfeInfo = null;
        $apiError = null;

        try {
            $freshDetails = $nomusApiClient->getInvoice((int) $invoice->external_id);
            if ($freshDetails !== []) {
                $nomusDetails = $freshDetails;
            }
        } catch (Throwable $exception) {
            if ($nomusDetails === []) {
                $apiError = $exception->getMessage();
            }
        }

        try {
            $danfe = $nomusApiClient->getInvoiceDanfe((int) $invoice->external_id);
            $danfeInfo = [
                'has_file' => true,
                'size_bytes' => (int) floor(strlen($danfe['arquivo']) * 0.75),
            ];
        } catch (Throwable $exception) {
            if ($apiError === null && $nomusDetails === []) {
                $apiError = $exception->getMessage();
            }
        }

        $xmlData = $this->extractXmlSummary(is_array($nomusDetails) ? ($nomusDetails['xml'] ?? null) : null);

        return view('invoices.show', [
            'invoice' => $invoice,
            'nomusDetails' => $nomusDetails,
            'danfeInfo' => $danfeInfo,
            'apiError' => $apiError,
            'xmlData' => $xmlData,
        ]);
    }

    public function danfe(
        FiscalInvoice $invoice,
        TenantContext $tenantContext,
        NomusApiClient $nomusApiClient,
        Request $request
    ): Response {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant && $invoice->tenant_id === $tenant->id, 404);

        $danfe = $nomusApiClient->getInvoiceDanfe((int) $invoice->external_id);
        $binaryPdf = base64_decode($danfe['arquivo'], true);

        if (! is_string($binaryPdf) || $binaryPdf === '') {
            throw new RuntimeException('Nao foi possivel decodificar o PDF da DANFE.');
        }

        $fileName = sprintf(
            'danfe-%s-%s.pdf',
            $invoice->numero ?: 'sem-numero',
            $invoice->external_id
        );

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($binaryPdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($binaryPdf),
            'Content-Disposition' => $disposition.'; filename="'.$fileName.'"',
        ]);
    }

    /**
     * @return array{
     *     has_xml: bool,
     *     emitente: array<string, string>,
     *     destinatario: array<string, string>,
     *     totais: array<string, string>,
     *     itens: array<int, array<string, string>>,
     *     raw_xml: string|null
     * }
     */
    private function extractXmlSummary(mixed $xmlRaw): array
    {
        $result = [
            'has_xml' => false,
            'emitente' => [],
            'destinatario' => [],
            'totais' => [],
            'itens' => [],
            'raw_xml' => null,
        ];

        if (! is_string($xmlRaw) || trim($xmlRaw) === '') {
            return $result;
        }

        $xml = trim($xmlRaw);
        $result['raw_xml'] = $xml;
        $result['has_xml'] = true;

        try {
            $document = new \DOMDocument();
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            if (! $loaded) {
                return $result;
            }

            $xpath = new \DOMXPath($document);

            $result['emitente'] = [
                'nome' => $this->firstText($xpath, '//*[local-name()="emit"]/*[local-name()="xNome"]'),
                'cnpj' => $this->firstText($xpath, '//*[local-name()="emit"]/*[local-name()="CNPJ"]'),
                'ie' => $this->firstText($xpath, '//*[local-name()="emit"]/*[local-name()="IE"]'),
            ];

            $result['destinatario'] = [
                'nome' => $this->firstText($xpath, '//*[local-name()="dest"]/*[local-name()="xNome"]'),
                'cnpj' => $this->firstText($xpath, '//*[local-name()="dest"]/*[local-name()="CNPJ"]'),
                'cpf' => $this->firstText($xpath, '//*[local-name()="dest"]/*[local-name()="CPF"]'),
                'ie' => $this->firstText($xpath, '//*[local-name()="dest"]/*[local-name()="IE"]'),
            ];

            $result['totais'] = [
                'valor_produtos' => $this->firstText($xpath, '//*[local-name()="ICMSTot"]/*[local-name()="vProd"]'),
                'valor_nf' => $this->firstText($xpath, '//*[local-name()="ICMSTot"]/*[local-name()="vNF"]'),
                'valor_frete' => $this->firstText($xpath, '//*[local-name()="ICMSTot"]/*[local-name()="vFrete"]'),
                'valor_desconto' => $this->firstText($xpath, '//*[local-name()="ICMSTot"]/*[local-name()="vDesc"]'),
            ];

            $itemNodes = $xpath->query('//*[local-name()="det"]');
            if ($itemNodes !== false) {
                foreach ($itemNodes as $itemNode) {
                    if (! $itemNode instanceof \DOMElement) {
                        continue;
                    }

                    $prodPathBase = './/*[local-name()="prod"]';
                    $result['itens'][] = [
                        'item' => (string) $itemNode->getAttribute('nItem'),
                        'codigo' => $this->firstText($xpath, $prodPathBase.'/*[local-name()="cProd"]', $itemNode),
                        'descricao' => $this->firstText($xpath, $prodPathBase.'/*[local-name()="xProd"]', $itemNode),
                        'quantidade' => $this->firstText($xpath, $prodPathBase.'/*[local-name()="qCom"]', $itemNode),
                        'unidade' => $this->firstText($xpath, $prodPathBase.'/*[local-name()="uCom"]', $itemNode),
                        'valor_unitario' => $this->firstText($xpath, $prodPathBase.'/*[local-name()="vUnCom"]', $itemNode),
                        'valor_total' => $this->firstText($xpath, $prodPathBase.'/*[local-name()="vProd"]', $itemNode),
                    ];
                }
            }
        } catch (Throwable) {
            // Keep fallback with raw XML only
        }

        return $result;
    }

    private function firstText(\DOMXPath $xpath, string $path, ?\DOMNode $contextNode = null): string
    {
        $nodeList = $xpath->query($path, $contextNode);
        if ($nodeList === false || $nodeList->length === 0) {
            return '';
        }

        $text = trim((string) $nodeList->item(0)?->textContent);

        return $text;
    }

    /**
     * @return array{
     *     destinatario_nome:string,
     *     valor_total_formatado:string,
     *     data_emissao:string,
     *     situacao:string,
     *     situacao_cor:string
     * }
     */
    private function extractListSummary(FiscalInvoice $invoice): array
    {
        $nomusStatus = $this->mapNomusStatus($invoice->status);
        $fallback = [
            'destinatario_nome' => '',
            'valor_total_formatado' => '-',
            'data_emissao' => $invoice->data_processamento?->format('d/m/Y') ?? '-',
            'situacao' => $nomusStatus['label'],
            'situacao_cor' => $nomusStatus['color'],
        ];

        $payload = $invoice->payload;
        if (! is_array($payload)) {
            return $fallback;
        }

        $xmlRaw = $payload['xml'] ?? null;
        if (! is_string($xmlRaw) || trim($xmlRaw) === '') {
            return $fallback;
        }

        try {
            $document = new \DOMDocument();
            $loaded = $document->loadXML($xmlRaw, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

            if (! $loaded) {
                return $fallback;
            }

            $xpath = new \DOMXPath($document);
            $destinatarioNome = $this->firstText($xpath, '//*[local-name()="dest"]/*[local-name()="xNome"]');
            $valorTotalRaw = $this->firstText($xpath, '//*[local-name()="ICMSTot"]/*[local-name()="vNF"]');
            $dhEmi = $this->firstText($xpath, '//*[local-name()="ide"]/*[local-name()="dhEmi"]');
            $dEmi = $this->firstText($xpath, '//*[local-name()="ide"]/*[local-name()="dEmi"]');
            $statusCode = $this->firstText($xpath, '//*[local-name()="protNFe"]/*[local-name()="infProt"]/*[local-name()="cStat"]');
            $statusReason = $this->firstText($xpath, '//*[local-name()="protNFe"]/*[local-name()="infProt"]/*[local-name()="xMotivo"]');
            $resolvedStatus = $this->mapNfeStatus($statusCode, $statusReason, $nomusStatus);
            $valorTotal = $this->parseAmount($valorTotalRaw);

            return [
                'destinatario_nome' => $destinatarioNome,
                'valor_total_formatado' => $valorTotal !== null ? $this->formatCurrency($valorTotal) : '-',
                'data_emissao' => $this->formatEmissionDate($dhEmi !== '' ? $dhEmi : $dEmi) ?? $fallback['data_emissao'],
                'situacao' => $resolvedStatus['label'],
                'situacao_cor' => $resolvedStatus['color'],
            ];
        } catch (Throwable) {
            return $fallback;
        }
    }

    /**
     * @return array{label:string, color:string}
     */
    private function mapNomusStatus(?int $status): array
    {
        return match ($status) {
            4 => ['label' => 'Autorizada', 'color' => '#16a34a'],
            7 => ['label' => 'Cancelada', 'color' => '#dc2626'],
            null => ['label' => '-', 'color' => '#64748b'],
            default => ['label' => 'Status '.$status, 'color' => '#d97706'],
        };
    }

    /**
     * @param  array{label:string, color:string}  $fallbackStatus
     * @return array{label:string, color:string}
     */
    private function mapNfeStatus(string $cStat, string $xMotivo, array $fallbackStatus): array
    {
        if (in_array($cStat, ['100', '150'], true)) {
            return ['label' => 'Autorizada', 'color' => '#16a34a'];
        }

        if (in_array($cStat, ['101', '135', '151', '155'], true)) {
            return ['label' => 'Cancelada', 'color' => '#dc2626'];
        }

        if ($cStat === '110') {
            return ['label' => 'Denegada', 'color' => '#b45309'];
        }

        $motivo = mb_strtolower(trim($xMotivo));
        if ($motivo !== '') {
            if (str_contains($motivo, 'cancel')) {
                return ['label' => 'Cancelada', 'color' => '#dc2626'];
            }

            if (str_contains($motivo, 'autoriz')) {
                return ['label' => 'Autorizada', 'color' => '#16a34a'];
            }
        }

        return $fallbackStatus;
    }

    private function parseAmount(string $raw): ?float
    {
        $normalized = trim($raw);
        if ($normalized === '') {
            return null;
        }

        // Accept both "1047.40" and "1.047,40".
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function formatCurrency(float $amount): string
    {
        return 'R$ '.number_format($amount, 2, ',', '.');
    }

    private function formatEmissionDate(string $raw): ?string
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (Throwable) {
            return null;
        }
    }
}
