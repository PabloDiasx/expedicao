<x-layouts.app :title="'Nota Fiscal #'.$invoice->external_id">
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
            <div><strong>ID Nomus:</strong> {{ $invoice->external_id }}</div>
            <div><strong>Numero:</strong> {{ $invoice->numero ?? '-' }}</div>
            <div><strong>Serie:</strong> {{ $invoice->serie ?? '-' }}</div>
            <div><strong>CNPJ Emitente:</strong> {{ $invoice->cnpj_emitente ?? '-' }}</div>
            <div><strong>Chave:</strong> {{ $invoice->chave ?? '-' }}</div>
            <div><strong>Atualizado:</strong> {{ $invoice->nomus_updated_at ? $invoice->nomus_updated_at->format('d/m/Y H:i:s') : '-' }}</div>
        </div>
    </section>

    @if (! empty($nomusDetails))
        @php
            $detailsWithoutXml = $nomusDetails;
            if (is_array($detailsWithoutXml)) {
                unset($detailsWithoutXml['xml']);
            }
        @endphp

        <section class="panel-card">
            <h2 class="section-title">Campos completos da nota</h2>
            <div class="invoice-kv-grid">
                @foreach ($detailsWithoutXml as $key => $value)
                    <div>
                        <strong>{{ $key }}:</strong>
                        @if (is_array($value) || is_object($value))
                            <code>{{ json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code>
                        @else
                            {{ (string) $value }}
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($xmlData['has_xml'])
        <section class="panel-card">
            <h2 class="section-title">Informacoes extraidas da DANFE/XML</h2>

            <div class="invoice-kv-grid">
                <div><strong>Emitente:</strong> {{ $xmlData['emitente']['nome'] ?: '-' }}</div>
                <div><strong>CNPJ Emitente:</strong> {{ $xmlData['emitente']['cnpj'] ?: '-' }}</div>
                <div><strong>Destinatario:</strong> {{ $xmlData['destinatario']['nome'] ?: '-' }}</div>
                <div><strong>CNPJ/CPF Destinatario:</strong> {{ $xmlData['destinatario']['cnpj'] ?: ($xmlData['destinatario']['cpf'] ?: '-') }}</div>
                <div><strong>Valor Produtos:</strong> {{ $xmlData['totais']['valor_produtos'] ?: '-' }}</div>
                <div><strong>Valor NF:</strong> {{ $xmlData['totais']['valor_nf'] ?: '-' }}</div>
            </div>

            <h3 class="invoice-subtitle">Itens da nota</h3>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Codigo</th>
                            <th>Descricao</th>
                            <th>Qtd</th>
                            <th>Unidade</th>
                            <th>Vlr Unit.</th>
                            <th>Vlr Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($xmlData['itens'] as $item)
                            <tr>
                                <td>{{ $item['item'] ?: '-' }}</td>
                                <td>{{ $item['codigo'] ?: '-' }}</td>
                                <td>{{ $item['descricao'] ?: '-' }}</td>
                                <td>{{ $item['quantidade'] ?: '-' }}</td>
                                <td>{{ $item['unidade'] ?: '-' }}</td>
                                <td>{{ $item['valor_unitario'] ?: '-' }}</td>
                                <td>{{ $item['valor_total'] ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="empty-cell">Nao foram encontrados itens no XML da nota.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <details class="invoice-raw-xml">
                <summary>Ver XML bruto completo</summary>
                <pre>{{ $xmlData['raw_xml'] }}</pre>
            </details>
        </section>
    @endif

    @if ($danfeInfo && $danfeInfo['has_file'])
        <section class="panel-card">
            <h2 class="section-title">Preview da DANFE</h2>
            <p class="muted">
                Tamanho aproximado do PDF: {{ number_format($danfeInfo['size_bytes'] / 1024, 1, ',', '.') }} KB
            </p>
            <iframe
                class="danfe-frame"
                src="{{ route('invoices.danfe', $invoice) }}"
                title="DANFE"
                loading="lazy"
            ></iframe>
        </section>
    @endif
</x-layouts.app>
