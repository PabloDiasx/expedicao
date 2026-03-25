<x-layouts.app :title="'Montagem'" :pageClass="'montagem-page'">
    <section class="panel-card">
        <form method="GET" action="{{ route('production.index') }}" class="filters-grid montagem-filters-grid">
            <input type="hidden" name="etapa" value="montagem">
            <input type="hidden" name="due_from" value="{{ $filters['due_from'] }}">
            <input type="hidden" name="due_until" value="{{ $filters['due_until'] }}">

            <div>
                <label class="panel-label" for="q">Busca</label>
                <input
                    id="q"
                    name="q"
                    type="text"
                    class="input"
                    value="{{ $filters['q'] }}"
                    placeholder="Modelo ou pedido"
                >
            </div>

            <div class="filters-actions">
                <button type="submit" class="page-btn">Filtrar</button>
                <a href="{{ route('production.index', ['etapa' => 'montagem']) }}" class="page-btn page-btn-light">Limpar</a>
            </div>
        </form>
    </section>

    <section class="panel-card">
        <h2 class="section-title">Leitura de codigo de barras</h2>
        <form method="POST" action="{{ route('production.store', ['etapa' => 'montagem']) }}" class="operation-form stack-16" novalidate>
            @csrf
            <input type="hidden" name="q" value="{{ $filters['q'] }}">
            <input type="hidden" name="due_from" value="{{ $filters['due_from'] }}">
            <input type="hidden" name="due_until" value="{{ $filters['due_until'] }}">

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
                        placeholder="Ex: COLETOR-MONT-01"
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
                <button type="submit" class="page-btn">Registrar baixa</button>
            </div>
        </form>
    </section>

    <section class="panel-card">
        <div class="montagem-demand-header">
            <h2 class="section-title montagem-demand-title">Demanda de montagem</h2>
            <form method="GET" action="{{ route('production.index') }}" class="filters-actions montagem-date-filter">
                <input type="hidden" name="etapa" value="montagem">
                <input type="hidden" name="q" value="{{ $filters['q'] }}">
                <div class="montagem-date-field">
                    <label class="panel-label" for="demanda_due_from">Data inicial</label>
                    <input
                        id="demanda_due_from"
                        name="due_from"
                        type="date"
                        class="input"
                        value="{{ $filters['due_from'] }}"
                    >
                </div>
                <div class="montagem-date-field">
                    <label class="panel-label" for="demanda_due_until">Data final</label>
                    <input
                        id="demanda_due_until"
                        name="due_until"
                        type="date"
                        class="input"
                        value="{{ $filters['due_until'] }}"
                    >
                </div>
                <button type="submit" class="page-btn">Atualizar</button>
            </form>
        </div>
        <div class="table-wrap montagem-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Modelo</th>
                        <th>Qtd Pedido</th>
                        <th>Qtd Montada</th>
                        <th>Faltante</th>
                        <th>Proxima entrega</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($equipmentRows as $row)
                        <tr>
                            <td>{{ $row['model_name'] }}</td>
                            <td>{{ $row['ordered'] }}</td>
                            <td>{{ $row['assembled'] }}</td>
                            <td>{{ $row['remaining'] }}</td>
                            <td>{{ $row['next_delivery'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-cell">Nenhuma demanda pendente para o filtro atual.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel-card">
        <h2 class="section-title">Ultimas baixas de montagem</h2>
        <div class="table-wrap montagem-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Serial</th>
                        <th>Pedido</th>
                        <th>Item</th>
                        <th>Coletor</th>
                        <th>Usuario</th>
                        <th>Observacao</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentScans as $item)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($item->scanned_at)->format('d/m/Y H:i') }}</td>
                            <td>{{ $item->serial_number }}</td>
                            <td>{{ $item->codigo_pedido }}</td>
                            <td>{{ $item->item_code }}</td>
                            <td>{{ $item->device_identifier ?? '-' }}</td>
                            <td>{{ $item->user_name ?? '-' }}</td>
                            <td>{{ $item->notes ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-cell">Nenhuma baixa registrada ainda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @push('scripts')
    <script src="{{ asset('js/barcode-converter.js') }}?v={{ filemtime(public_path('js/barcode-converter.js')) }}"></script>
    <script>
        (function () {
            var barcodeInput = document.getElementById('barcode');
            var notesInput = document.getElementById('notes');

            if (!barcodeInput) {
                return;
            }

            var form = barcodeInput.closest('form');
            if (!form) {
                return;
            }

            function ensureConversionBeforeSubmit() {
                var parsed = BarcodeConverter.convert(barcodeInput.value);
                if (!parsed || !parsed.converted) {
                    return;
                }

                barcodeInput.value = parsed.serial;

                if (notesInput) {
                    notesInput.value = BarcodeConverter.appendConversionNote(notesInput.value, parsed);
                }
            }

            form.addEventListener('submit', ensureConversionBeforeSubmit);
            barcodeInput.addEventListener('change', ensureConversionBeforeSubmit);
        })();
    </script>
    @endpush
</x-layouts.app>
