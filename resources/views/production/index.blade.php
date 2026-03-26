<x-layouts.app :title="'Montagem'" :pageClass="'montagem-page'">
    <section class="panel-card">
        <h2 class="section-title">Leitura de codigo de barras</h2>
        <form id="montagem-form" method="POST" action="{{ route('production.store', ['etapa' => 'montagem']) }}" class="operation-form stack-16" novalidate>
            @csrf
            <input type="hidden" name="q" value="{{ $filters['q'] }}">
            <input type="hidden" name="due_from" value="{{ $filters['due_from'] }}">
            <input type="hidden" name="due_until" value="{{ $filters['due_until'] }}">

            <div>
                <label class="panel-label" for="barcode">Código de barras</label>
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

            <div>
                <label class="panel-label" for="notes">Observação</label>
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

            <div class="filters-actions">
                <button id="btn-registrar-montagem" type="submit" class="page-btn">Registrar baixa</button>
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
                    <label class="panel-label" for="demanda_due_from">De</label>
                    <input
                        id="demanda_due_from"
                        name="due_from"
                        type="date"
                        class="input"
                        value="{{ $filters['due_from'] }}"
                    >
                </div>
                <div class="montagem-date-field">
                    <label class="panel-label" for="demanda_due_until">Ate</label>
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
                        <th>Próxima entrega</th>
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

    @push('scripts')
    <script src="{{ asset('js/barcode-converter.js') }}?v={{ filemtime(public_path('js/barcode-converter.js')) }}"></script>
    <script>
        (function () {
            var barcodeInput = document.getElementById('barcode');
            var notesInput = document.getElementById('notes');

            if (!barcodeInput) return;

            var form = document.getElementById('montagem-form');
            if (!form) return;

            var submitIntentional = false;
            var btnRegistrar = document.getElementById('btn-registrar-montagem');

            // Converter barcode antes de submeter
            function ensureConversion() {
                var parsed = BarcodeConverter.convert(barcodeInput.value);
                if (!parsed || !parsed.converted) return;
                barcodeInput.value = parsed.serial;
                if (notesInput && notesInput.value.trim() === '') {
                    notesInput.value = parsed.raw;
                }
            }

            // Bloquear Enter de submeter, igual entrada
            form.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    if (e.target === btnRegistrar) return;
                    e.preventDefault();
                    e.stopPropagation();
                    if (e.target === barcodeInput) {
                        ensureConversion();
                        barcodeInput.select();
                    }
                }
            });

            barcodeInput.addEventListener('change', ensureConversion);

            btnRegistrar.addEventListener('click', function () { submitIntentional = true; });

            form.addEventListener('submit', function (e) {
                if (!submitIntentional) { e.preventDefault(); return; }
                submitIntentional = false;
                ensureConversion();
            });

            // Manter foco no campo barcode
            barcodeInput.addEventListener('blur', function () {
                setTimeout(function () {
                    if (document.activeElement !== btnRegistrar) {
                        barcodeInput.focus();
                    }
                }, 100);
            });
        })();
    </script>
    @endpush
</x-layouts.app>
