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
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Serial</th>
                        <th>Modelo</th>
                        <th>Cliente</th>
                        <th>Código de barras</th>
                        <th>Status</th>
                        <th>Setor</th>
                        <th>Observação</th>
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
                                <div class="status-dropdown">
                                    <button
                                        type="button"
                                        class="status-badge status-badge--clickable"
                                        style="--status-color: @safeColor($equipment->status_color)"
                                        data-equipment-id="{{ $equipment->id }}"
                                        data-current-status="{{ $equipment->status_id }}"
                                    >
                                        {{ $equipment->status_name }}
                                    </button>
                                    <div class="status-dropdown-menu">
                                        @foreach ($statuses as $status)
                                            <form method="POST" action="{{ route('equipments.update-status', ['equipment' => $equipment->id]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    type="submit"
                                                    name="status_id"
                                                    value="{{ $status->id }}"
                                                    class="status-dropdown-item {{ $equipment->status_id == $status->id ? 'status-dropdown-item--active' : '' }}"
                                                    style="--status-color: @safeColor($status->color)"
                                                >
                                                    <span class="status-dropdown-dot"></span>
                                                    {{ $status->name }}
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                            <td>{{ $equipment->sector_name ?? '-' }}</td>
                            <td>{{ $equipment->notes ?? '-' }}</td>
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
                            <td colspan="9" class="empty-cell">Nenhum equipamento localizado.</td>
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
            // ── Status dropdown ──
            var activeDropdown = null;

            function closeAll() {
                if (activeDropdown) {
                    activeDropdown.classList.remove('status-dropdown--open');
                    activeDropdown = null;
                }
            }

            function positionMenu(badge, menu) {
                var rect = badge.getBoundingClientRect();
                var menuHeight = menu.scrollHeight;
                var spaceBelow = window.innerHeight - rect.bottom - 8;
                var spaceAbove = rect.top - 8;

                menu.style.left = rect.left + 'px';

                if (spaceBelow >= menuHeight || spaceBelow >= spaceAbove) {
                    menu.style.top = rect.bottom + 4 + 'px';
                    menu.style.bottom = 'auto';
                } else {
                    menu.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
                    menu.style.top = 'auto';
                }
            }

            document.addEventListener('click', function (e) {
                var badge = e.target.closest('.status-badge--clickable');
                if (badge) {
                    e.preventDefault();
                    e.stopPropagation();
                    var dropdown = badge.closest('.status-dropdown');
                    if (activeDropdown === dropdown) {
                        closeAll();
                    } else {
                        closeAll();
                        var menu = dropdown.querySelector('.status-dropdown-menu');
                        dropdown.classList.add('status-dropdown--open');
                        positionMenu(badge, menu);
                        activeDropdown = dropdown;
                    }
                    return;
                }

                if (!e.target.closest('.status-dropdown-menu')) {
                    closeAll();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeAll();
            });

            // ── Delete confirmation ──
            document.addEventListener('submit', function (e) {
                var form = e.target.closest('.js-equipment-delete');
                if (!form) return;

                e.preventDefault();
                var serial = form.dataset.serial || 'este equipamento';

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Remover equipamento?',
                        html: 'Tem certeza que deseja remover <strong>' + serial + '</strong>? Esta ação não pode ser desfeita.',
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
