<x-layouts.app :title="'Expedicao'">
    <section class="panel-card">
        <h2 class="section-title">Leitura para expedicao</h2>

        @if (! $dispatchStatus || ! $dispatchSector)
            <div class="alert-warning">
                Configuracao incompleta: status "Expedido" ou setor "Expedicao" nao encontrados.
            </div>
        @else
            <div class="expedition-meta">
                <span class="status-badge" style="--status-color: {{ $dispatchStatus->color }}">
                    Status final: {{ $dispatchStatus->name }}
                </span>
                <span class="expedition-sector">Setor: {{ $dispatchSector->name }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('expedition.store') }}" class="operation-form stack-16" novalidate>
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

            <div class="form-grid-2">
                <div>
                    <label class="panel-label" for="device_identifier">Coletor</label>
                    <input
                        id="device_identifier"
                        name="device_identifier"
                        type="text"
                        class="input"
                        value="{{ old('device_identifier') }}"
                        maxlength="80"
                        placeholder="Ex: COLETOR-EXP-01"
                    >
                </div>

                <div>
                    <label class="panel-label" for="notes">Observacao</label>
                    <input
                        id="notes"
                        name="notes"
                        type="text"
                        class="input"
                        value="{{ old('notes') }}"
                        maxlength="500"
                        placeholder="Opcional"
                    >
                </div>
            </div>

            <div class="filters-actions">
                <button type="submit" class="page-btn">Confirmar expedicao</button>
            </div>
        </form>
    </section>

    <section class="panel-card">
        <h2 class="section-title">Ultimas expedicoes</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Serial</th>
                        <th>Codigo de barras</th>
                        <th>Status</th>
                        <th>Usuario</th>
                        <th>Observacao</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentDispatches as $item)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($item->changed_at)->format('d/m/Y H:i') }}</td>
                            <td>{{ $item->serial_number }}</td>
                            <td>{{ $item->barcode }}</td>
                            <td>
                                <span class="status-badge" style="--status-color: {{ $item->status_color }}">
                                    {{ $item->status_name }}
                                </span>
                            </td>
                            <td>{{ $item->user_name ?? '-' }}</td>
                            <td>{{ $item->notes ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-cell">Nenhuma expedicao registrada ainda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
