<x-layouts.app :title="$invoice->numero ? 'Nota Fiscal '.$invoice->numero : 'Nota Fiscal'">
    <section class="panel-card">
        <div class="invoice-detail-top">
            <a href="{{ route('invoices.index') }}" class="page-btn page-btn-light">Voltar</a>
            @if ($danfeInfo && $danfeInfo['has_file'])
                <div class="filters-actions">
                    <a href="{{ route('invoices.danfe', $invoice) }}" target="_blank" class="page-btn">Visualizar DANFE</a>
                    <a href="{{ route('invoices.danfe', ['invoice' => $invoice->id, 'download' => 1]) }}" class="page-btn page-btn-light">Baixar DANFE</a>
                </div>
            @else
                <span class="muted">DANFE indisponivel para esta nota no momento.</span>
            @endif
        </div>
    </section>

    @if ($apiError)
        <section class="panel-card">
            <p class="alert-warning">
                Nao foi possivel buscar os detalhes mais recentes na API Nomus: {{ $apiError }}
            </p>
        </section>
    @endif

    <section class="panel-card">
        <h2 class="section-title">Dados principais</h2>
        <div class="invoice-kv-grid">
            <div><strong>Numero:</strong> {{ $invoice->numero ?? '-' }}</div>
            <div><strong>CNPJ Emitente:</strong> {{ $invoice->cnpj_emitente ?? '-' }}</div>
            <div><strong>Emitente:</strong> {{ $xmlData['emitente']['nome'] ?: '-' }}</div>
            <div><strong>Destinatario:</strong> {{ $xmlData['destinatario']['nome'] ?: '-' }}</div>
            <div>
                <strong>Valor NF:</strong>
                @if ($xmlData['totais']['valor_nf'])
                    R$ {{ number_format((float) $xmlData['totais']['valor_nf'], 2, ',', '.') }}
                @else
                    -
                @endif
            </div>
            <div><strong>Atualizado:</strong> {{ $invoice->nomus_updated_at ? $invoice->nomus_updated_at->format('d/m/Y H:i:s') : '-' }}</div>
        </div>
    </section>

    @if ($xmlData['has_xml'])
        @php
            $parseDecimal = static function (?string $value): ?float {
                if (! is_string($value) || trim($value) === '') {
                    return null;
                }

                $normalized = trim($value);
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
            };

            $formatCurrency = static function (?string $value) use ($parseDecimal): string {
                $amount = $parseDecimal($value);

                return $amount === null ? '-' : 'R$ '.number_format($amount, 2, ',', '.');
            };

            $formatDocument = static function (?string $value): string {
                if (! is_string($value) || trim($value) === '') {
                    return '-';
                }

                $digits = preg_replace('/\D+/', '', $value) ?? '';
                if (strlen($digits) === 14) {
                    return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits) ?? $value;
                }

                if (strlen($digits) === 11) {
                    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits) ?? $value;
                }

                return trim($value);
            };

            $formatQuantity = static function (?string $value) use ($parseDecimal): string {
                $amount = $parseDecimal($value);
                if ($amount === null) {
                    return '-';
                }

                if (abs($amount - round($amount)) < 0.00001) {
                    return (string) (int) round($amount);
                }

                return rtrim(rtrim(number_format($amount, 4, ',', '.'), '0'), ',');
            };
        @endphp

        <section class="panel-card">
            <h2 class="section-title">Resumo da Nota Fiscal</h2>

            <div class="invoice-kv-grid">
                <div><strong>Emitente:</strong> {{ $xmlData['emitente']['nome'] ?: '-' }}</div>
                <div><strong>CNPJ do Emitente:</strong> {{ $formatDocument($xmlData['emitente']['cnpj'] ?? null) }}</div>
                <div><strong>Destinatario:</strong> {{ $xmlData['destinatario']['nome'] ?: '-' }}</div>
                <div><strong>CNPJ/CPF do Destinatario:</strong> {{ $formatDocument($xmlData['destinatario']['cnpj'] ?: ($xmlData['destinatario']['cpf'] ?: null)) }}</div>
                <div><strong>Valor dos Produtos:</strong> {{ $formatCurrency($xmlData['totais']['valor_produtos'] ?? null) }}</div>
                <div><strong>Valor Total da Nota:</strong> {{ $formatCurrency($xmlData['totais']['valor_nf'] ?? null) }}</div>
            </div>

            <h3 class="invoice-subtitle">Itens da nota</h3>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>N&deg;Serie</th>
                            <th>Descricao</th>
                            <th>Quantidade</th>
                            <th>Unidade</th>
                            <th>Valor Unitario</th>
                            <th>Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($xmlData['itens'] as $item)
                            <tr>
                                <td>{{ $item['item'] ?: '-' }}</td>
                                <td>{{ $item['numero_serie'] ?: '-' }}</td>
                                <td>{{ $item['descricao'] ?: '-' }}</td>
                                <td>{{ $formatQuantity($item['quantidade'] ?: null) }}</td>
                                <td>{{ $item['unidade'] ?: '-' }}</td>
                                <td>{{ $formatCurrency($item['valor_unitario'] ?: null) }}</td>
                                <td>{{ $formatCurrency($item['valor_total'] ?: null) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="empty-cell">Nao foram encontrados itens no XML da nota.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </section>
    @endif
</x-layouts.app>

