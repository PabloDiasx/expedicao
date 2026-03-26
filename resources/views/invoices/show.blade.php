<x-layouts.app :title="$invoice->numero ? 'Nota Fiscal '.$invoice->numero : 'Nota Fiscal'">
    <section class="panel-card">
        <div class="invoice-detail-top">
            <a href="{{ route('invoices.index') }}" class="page-btn page-btn-light">Voltar</a>
            <div class="filters-actions">
                @if ($danfeInfo && $danfeInfo['has_file'])
                    <a href="{{ route('invoices.danfe', $invoice) }}" target="_blank" class="page-btn">Visualizar DANFE</a>
                    <a href="{{ route('invoices.danfe', ['invoice' => $invoice->id, 'download' => 1]) }}" class="page-btn page-btn-light">Baixar DANFE</a>
                @endif
                @if ($invoice->status === 4)
                    <button type="button" id="btn-carregar" class="page-btn" style="background:#F97316;">Carregar</button>
                @endif
            </div>
        </div>
    </section>

    {{-- Modal de carregamento --}}
    @if ($invoice->status === 4)
    <div id="carregamento-modal" class="modal-overlay" style="display:none;">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title">Novo Carregamento — NF {{ $invoice->numero }}</h3>
                <button type="button" class="modal-close" id="carregamento-modal-close" aria-label="Fechar">&times;</button>
            </div>
            <form method="POST" action="{{ route('carregamentos.store') }}" class="stack-16" style="margin-top:var(--space-4);">
                @csrf
                <input type="hidden" name="fiscal_invoice_id" value="{{ $invoice->id }}">
                <div>
                    <label class="panel-label" for="motorista_nome">Nome do motorista</label>
                    <input id="motorista_nome" name="motorista_nome" type="text" class="input" required placeholder="Nome completo">
                </div>
                <div>
                    <label class="panel-label" for="motorista_documento">Documento do motorista (CPF)</label>
                    <input id="motorista_documento" name="motorista_documento" type="text" class="input" required placeholder="000.000.000-00" maxlength="14">
                </div>
                <div>
                    <label class="panel-label" for="placa_veiculo">Placa do veículo</label>
                    <input id="placa_veiculo" name="placa_veiculo" type="text" class="input" required placeholder="ABC-1D23" maxlength="8" style="text-transform:uppercase;">
                </div>
                <div>
                    <label class="panel-label" for="motorista_empresa">Empresa do motorista</label>
                    <input id="motorista_empresa" name="motorista_empresa" type="text" class="input" placeholder="Opcional">
                </div>
                <div class="filters-actions">
                    <button type="submit" class="page-btn">Prosseguir</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    @if ($apiError)
        <section class="panel-card">
            <p class="alert-warning">
                Não foi possível buscar os detalhes mais recentes na API Nomus: {{ $apiError }}
            </p>
        </section>
    @endif

    <section class="panel-card">
        <h2 class="section-title">Dados principais</h2>
        <div class="invoice-kv-grid">
            <div><strong>Número:</strong> {{ $invoice->numero ?? '-' }}</div>
            <div><strong>CNPJ Emitente:</strong> {{ $invoice->cnpj_emitente ?? '-' }}</div>
            <div><strong>Emitente:</strong> {{ $xmlData['emitente']['nome'] ?: '-' }}</div>
            <div><strong>Destinatário:</strong> {{ $xmlData['destinatario']['nome'] ?: '-' }}</div>
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
                <div><strong>Destinatário:</strong> {{ $xmlData['destinatario']['nome'] ?: '-' }}</div>
                <div><strong>CNPJ/CPF do Destinatário:</strong> {{ $formatDocument($xmlData['destinatario']['cnpj'] ?: ($xmlData['destinatario']['cpf'] ?: null)) }}</div>
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
                                <td colspan="7" class="empty-cell">Não foram encontrados itens no XML da nota.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </section>
    @endif

    @if ($invoice->status === 4)
    @push('scripts')
    <script>
        (function () {
            var btn = document.getElementById('btn-carregar');
            var modal = document.getElementById('carregamento-modal');
            var closeBtn = document.getElementById('carregamento-modal-close');
            if (!btn || !modal) return;

            btn.addEventListener('click', function () { modal.style.display = ''; });
            closeBtn.addEventListener('click', function () { modal.style.display = 'none'; });
            modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') modal.style.display = 'none'; });

            // CPF mask: 000.000.000-00
            var docInput = document.getElementById('motorista_documento');
            if (docInput) {
                docInput.addEventListener('input', function () {
                    var v = this.value.replace(/\D/g, '').substring(0, 11);
                    if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
                    else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
                    else if (v.length > 3) v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
                    this.value = v;
                });
            }

            // Placa mask: ABC-1D23 or ABC-1234
            var placaInput = document.getElementById('placa_veiculo');
            if (placaInput) {
                placaInput.addEventListener('input', function () {
                    var v = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 7);
                    if (v.length > 3) v = v.substring(0, 3) + '-' + v.substring(3);
                    this.value = v;
                });
            }
        })();
    </script>
    @endpush
    @endif
</x-layouts.app>

