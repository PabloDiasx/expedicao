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
                        <th></th>
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
                            <td><a href="{{ route('equipments.show', ['equipment' => $equipment->id]) }}" class="row-link-anchor">{{ $equipment->serial_number }}</a></td>
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
                            <td>
                                <form method="POST" action="{{ route('equipments.destroy', ['equipment' => $equipment->id]) }}" class="inline-delete-form js-equipment-delete" data-serial="{{ $equipment->serial_number }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-delete-icon" title="Remover equipamento">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-cell">Nenhum equipamento localizado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @push('scripts')
    <script src="{{ asset('js/table-row-links.js') }}?v={{ filemtime(public_path('js/table-row-links.js')) }}"></script>
    <script>
        (function () {
            document.addEventListener('submit', function (e) {
                var form = e.target.closest('.js-equipment-delete');
                if (!form) return;

                e.preventDefault();
                var serial = form.dataset.serial || 'este equipamento';

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Remover equipamento?',
                        html: 'Tem certeza que deseja remover <strong>' + serial + '</strong>? Esta acao nao pode ser desfeita.',
                        showCancelButton: true,
                        confirmButtonColor: '#ef4444',
                        confirmButtonText: 'Sim, remover',
                        cancelButtonText: 'Cancelar',
                        reverseButtons: true,
                        focusCancel: true,
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            form.dataset.confirmed = '1';
                            HTMLFormElement.prototype.submit.call(form);
                        }
                    });
                } else {
                    if (confirm('Tem certeza que deseja remover o equipamento ' + serial + '?')) {
                        HTMLFormElement.prototype.submit.call(form);
                    }
                }
            });
        })();
    </script>
    @endpush
</x-layouts.app>
