<x-layouts.app :title="'Equipamentos'">
    <section class="panel-card">
        <form method="GET" action="{{ route('equipments.index') }}" class="filters-grid equipments-filters-grid">
            <div>
                <label class="panel-label" for="q">Busca</label>
                <input
                    id="q"
                    name="q"
                    type="text"
                    class="input"
                    value="{{ $filters['q'] }}"
                    placeholder="Serial, codigo de barras, modelo ou cliente"
                >
            </div>

            <div>
                <label class="panel-label" for="status_id">Status</label>
                <select id="status_id" name="status_id" class="chart-select">
                    <option value="">Todos</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->id }}" {{ $filters['status_id'] === (string) $status->id ? 'selected' : '' }}>
                            {{ $status->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="panel-label" for="sector_id">Setor</label>
                <select id="sector_id" name="sector_id" class="chart-select">
                    <option value="">Todos</option>
                    @foreach ($sectors as $sector)
                        <option value="{{ $sector->id }}" {{ $filters['sector_id'] === (string) $sector->id ? 'selected' : '' }}>
                            {{ $sector->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="filters-actions equipments-filters-actions">
                <div class="equipments-actions-group">
                    <button type="submit" class="page-btn">Filtrar</button>
                    <a href="{{ route('equipments.index') }}" class="page-btn page-btn-light">Limpar</a>
                </div>
            </div>
        </form>
    </section>

    <section class="panel-card">
        <h2 class="section-title">Resumo por status</h2>
        <div class="summary-grid">
            @forelse ($statusSummary as $summary)
                <article class="summary-item">
                    <span class="summary-dot" style="background-color: @safeColor($summary->color)"></span>
                    <div>
                        <p class="summary-title">{{ $summary->name }}</p>
                        <p class="summary-value">{{ $summary->total }}</p>
                    </div>
                </article>
            @empty
                <p class="empty-state">Nenhum equipamento encontrado para este tenant.</p>
            @endforelse
        </div>
    </section>

    <section class="panel-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Serial</th>
                        <th>Modelo</th>
                        <th>Cliente</th>
                        <th>Codigo de barras</th>
                        <th>Status</th>
                        <th>Setor</th>
                        <th>Atualizado em</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($equipments as $equipment)
                        <tr
                            class="table-row-link"
                            tabindex="0"
                            data-href="{{ route('equipments.show', ['equipment' => $equipment->id]) }}"
                            role="link"
                            aria-label="Abrir detalhes do equipamento {{ $equipment->serial_number }}"
                        >
                            <td>{{ $equipment->serial_number }}</td>
                            <td>{{ $equipment->model_name }}</td>
                            <td>{{ $equipment->entry_customer_name ?? '-' }}</td>
                            <td>{{ $equipment->barcode }}</td>
                            <td>
                                <span class="status-badge" style="--status-color: @safeColor($equipment->status_color)">
                                    {{ $equipment->status_name }}
                                </span>
                            </td>
                            <td>{{ $equipment->sector_name ?? '-' }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($equipment->updated_at)->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-cell">Nenhum equipamento localizado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>

@push('scripts')
    <script>
        (function () {
            const rows = document.querySelectorAll('.table-row-link[data-href]');
            rows.forEach(function (row) {
                const href = row.getAttribute('data-href');
                if (!href) {
                    return;
                }

                row.addEventListener('click', function () {
                    window.location.href = href;
                });

                row.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        window.location.href = href;
                    }
                });
            });
        })();
    </script>
@endpush
