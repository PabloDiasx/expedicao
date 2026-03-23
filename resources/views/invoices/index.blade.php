<x-layouts.app :title="'Notas Fiscais'" page-class="invoices-list-page">
    <div class="invoices-page">
        <section class="panel-card">
            <form method="GET" action="{{ route('invoices.index') }}" class="filters-grid invoices-filters-grid-simple">
                <div>
                    <label class="panel-label" for="q">Busca</label>
                    <input
                        id="q"
                        name="q"
                        type="text"
                        class="input"
                        value="{{ $filters['q'] }}"
                        placeholder="Numero NF-e, serie ou CNPJ"
                    >
                </div>

                <div>
                    <label class="panel-label" for="from">De</label>
                    <input id="from" name="from" type="date" class="input" value="{{ $filters['from'] }}">
                </div>

                <div>
                    <label class="panel-label" for="to">Ate</label>
                    <input id="to" name="to" type="date" class="input" value="{{ $filters['to'] }}">
                </div>

                <div class="filters-actions">
                    <button type="submit" class="page-btn">Filtrar</button>
                    <a href="{{ route('invoices.index') }}" class="page-btn page-btn-light">Limpar</a>
                </div>
            </form>
        </section>

        <section class="panel-card invoices-table-panel">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>NF-e</th>
                            <th>Destinatario</th>
                            <th>Valor Total</th>
                            <th>Data de Emissao</th>
                            <th>Situacao</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            @php
                                $summary = $invoiceSummaries[$invoice->id] ?? [
                                    'destinatario_nome' => '',
                                    'valor_total_formatado' => '-',
                                    'data_emissao' => '-',
                                    'situacao' => '-',
                                    'situacao_cor' => '#64748b',
                                ];
                            @endphp
                            <tr
                                class="table-row-link"
                                data-href="{{ route('invoices.show', $invoice) }}"
                                tabindex="0"
                                role="link"
                                aria-label="Abrir nota fiscal {{ $invoice->numero ?? '-' }}"
                            >
                                <td>{{ $invoice->numero ?? '-' }}</td>
                                <td>{{ $summary['destinatario_nome'] !== '' ? $summary['destinatario_nome'] : '-' }}</td>
                                <td>{{ $summary['valor_total_formatado'] }}</td>
                                <td>{{ $summary['data_emissao'] }}</td>
                                <td>
                                    <span class="status-badge" style="--status-color: {{ $summary['situacao_cor'] }}">
                                        {{ $summary['situacao'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-cell">Nenhuma nota fiscal encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($invoices->hasPages())
                <div class="pagination-bar">
                    @if ($invoices->onFirstPage())
                        <span class="pagination-link pagination-disabled">Anterior</span>
                    @else
                        <a class="pagination-link" href="{{ $invoices->previousPageUrl() }}">Anterior</a>
                    @endif

                    <span class="pagination-current">
                        Pagina {{ $invoices->currentPage() }} de {{ $invoices->lastPage() }}
                    </span>

                    @if ($invoices->hasMorePages())
                        <a class="pagination-link" href="{{ $invoices->nextPageUrl() }}">Proxima</a>
                    @else
                        <span class="pagination-link pagination-disabled">Proxima</span>
                    @endif
                </div>
            @endif
        </section>
    </div>

    @push('scripts')
        <script>
            (function () {
                const rows = document.querySelectorAll('.table-row-link[data-href]');

                if (!rows.length) {
                    return;
                }

                rows.forEach(function (row) {
                    row.addEventListener('click', function (event) {
                        if (event.target.closest('a, button, input, select, textarea, label')) {
                            return;
                        }

                        window.location.href = row.dataset.href;
                    });

                    row.addEventListener('keydown', function (event) {
                        if (event.key !== 'Enter' && event.key !== ' ') {
                            return;
                        }

                        event.preventDefault();
                        window.location.href = row.dataset.href;
                    });
                });
            })();
        </script>
    @endpush
</x-layouts.app>
