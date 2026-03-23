<x-layouts.app :title="'Montagem'">
    <section class="panel-card">
        <h2 class="section-title">Leitura de codigo de barras</h2>
        <form method="POST" action="{{ route('production.store') }}" class="operation-form stack-16" novalidate>
            @csrf
            <div>
                <label class="panel-label" for="barcode">Codigo de barras</label>
                <input
                    id="barcode"
                    name="barcode"
                    type="text"
                    class="input"
                    value="{{ old('barcode') }}"
                    placeholder="Leia ou digite o codigo"
                    autocomplete="off"
                    required
                    autofocus
                >
            </div>

            <div class="form-grid-3">
                <div>
                    <label class="panel-label" for="status_id">Status de destino</label>
                    <select id="status_id" name="status_id" class="chart-select" required>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}" {{ (string) old('status_id', $defaultStatusId) === (string) $status->id ? 'selected' : '' }}>
                                {{ $status->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="panel-label" for="sector_id">Setor</label>
                    <select id="sector_id" name="sector_id" class="chart-select" required>
                        @foreach ($sectors as $sector)
                            <option value="{{ $sector->id }}" {{ (string) old('sector_id', $defaultSectorId) === (string) $sector->id ? 'selected' : '' }}>
                                {{ $sector->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="panel-label" for="device_identifier">Coletor</label>
                    <input
                        id="device_identifier"
                        name="device_identifier"
                        type="text"
                        class="input"
                        value="{{ old('device_identifier') }}"
                        maxlength="80"
                        placeholder="Ex: COLETOR-PROD-01"
                    >
                </div>
            </div>

            <div>
                <label class="panel-label" for="notes">Observacao</label>
                <textarea
                    id="notes"
                    name="notes"
                    class="input input-textarea"
                    rows="3"
                    maxlength="500"
                    placeholder="Opcional"
                >{{ old('notes') }}</textarea>
            </div>

            <div class="filters-actions">
                <button type="submit" class="page-btn">Registrar movimentacao</button>
            </div>
        </form>
    </section>

    <section class="panel-card">
        <h2 class="section-title">Ultimas movimentacoes da montagem</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Serial</th>
                        <th>Codigo de barras</th>
                        <th>Status</th>
                        <th>Setor</th>
                        <th>Usuario</th>
                        <th>Observacao</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentTransitions as $item)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($item->changed_at)->format('d/m/Y H:i') }}</td>
                            <td>{{ $item->serial_number }}</td>
                            <td>{{ $item->barcode }}</td>
                            <td>
                                <span class="status-badge" style="--status-color: {{ $item->status_color }}">
                                    {{ $item->status_name }}
                                </span>
                            </td>
                            <td>{{ $item->sector_name ?? '-' }}</td>
                            <td>{{ $item->user_name ?? '-' }}</td>
                            <td>{{ $item->notes ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-cell">Nenhuma movimentacao registrada ainda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
