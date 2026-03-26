<x-layouts.app :title="'Historicos'">
    <section class="panel-card">
        <form method="GET" action="{{ route('historicos.index') }}" class="filters-grid historicos-filters-grid">
            <div>
                <label class="panel-label" for="q">Busca</label>
                <input id="q" name="q" type="text" class="input" value="{{ $filters['q'] }}" placeholder="Serial, barcode, modelo, usuario ou cliente">
            </div>
            <div>
                <label class="panel-label" for="from">De</label>
                <input id="from" name="from" type="date" class="input" value="{{ $filters['from'] }}">
            </div>
            <div>
                <label class="panel-label" for="to">Ate</label>
                <input id="to" name="to" type="date" class="input" value="{{ $filters['to'] }}">
            </div>
            <div>
                <label class="panel-label" for="status_id">Status</label>
                <select id="status_id" name="status_id" class="chart-select">
                    <option value="">Todos</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->id }}" {{ $filters['status_id'] === (string) $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="panel-label" for="user_id">Usuario</label>
                <select id="user_id" name="user_id" class="chart-select">
                    <option value="">Todos</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" {{ $filters['user_id'] === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="panel-label" for="source">Origem</label>
                <select id="source" name="source" class="chart-select">
                    <option value="">Todas</option>
                    @foreach ($eventSources as $src)
                        <option value="{{ $src }}" {{ $filters['source'] === $src ? 'selected' : '' }}>{{ $src }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filters-actions" style="align-self:flex-end;">
                <button type="submit" class="page-btn">Filtrar</button>
                <a href="{{ route('historicos.index') }}" class="page-btn page-btn-light">Limpar</a>
            </div>
        </form>
    </section>

    <section class="panel-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Serial</th>
                        <th>Modelo</th>
                        <th>Cliente</th>
                        <th>De</th>
                        <th>Para</th>
                        <th>Setor</th>
                        <th>Usuario</th>
                        <th>Origem</th>
                        <th>NF</th>
                        <th>Observação</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($histories as $h)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($h->changed_at)->format('d/m/Y H:i') }}</td>
                            <td><a href="{{ route('equipments.show', ['equipment' => $h->equipment_id]) }}" class="row-link-anchor">{{ $h->serial_number }}</a></td>
                            <td>{{ $h->model_name ?? '-' }}</td>
                            <td>{{ $h->entry_customer_name ?? '-' }}</td>
                            <td>
                                @if ($h->from_status_name)
                                    <span class="status-badge" style="--status-color: @safeColor($h->from_status_color)">{{ $h->from_status_name }}</span>
                                @else
                                    <span style="color:var(--muted);">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="status-badge" style="--status-color: @safeColor($h->to_status_color)">{{ $h->to_status_name }}</span>
                            </td>
                            <td>{{ $h->sector_name ?? '-' }}</td>
                            <td>{{ $h->user_name ?? 'Sistema' }}</td>
                            <td>{{ $h->event_source ?? '-' }}</td>
                            <td>{{ $h->entry_invoice_number ?? '-' }}</td>
                            <td>{{ $h->notes ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="empty-cell">Nenhum historico encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
