<x-layouts.app :title="'Entrada'">
    <section class="panel-card">
        <h2 class="section-title">Leitura para entrada</h2>

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

            <div class="form-grid-3">
                <div>
                    <label class="panel-label" for="entry_customer_name">Nome Cliente</label>
                    <input
                        id="entry_customer_name"
                        type="text"
                        class="input"
                        value=""
                        readonly
                        tabindex="-1"
                        placeholder="-"
                    >
                </div>

                <div>
                    <label class="panel-label" for="entry_invoice_number">N° Nota</label>
                    <input
                        id="entry_invoice_number"
                        type="text"
                        class="input"
                        value=""
                        readonly
                        tabindex="-1"
                        placeholder="-"
                    >
                </div>

                <div>
                    <label class="panel-label" for="entry_destination">Destino</label>
                    <input
                        id="entry_destination"
                        type="text"
                        class="input"
                        value=""
                        readonly
                        tabindex="-1"
                        placeholder="-"
                    >
                </div>
            </div>

            <div class="filters-actions">
                <button type="submit" class="page-btn">Registrar entrada</button>
            </div>
        </form>
    </section>

    <section class="panel-card">
        <h2 class="section-title">Ultimas entradas</h2>
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
                                <span class="status-badge" style="--status-color: @safeColor($item->status_color)">
                                    {{ $item->status_name }}
                                </span>
                            </td>
                            <td>{{ $item->user_name ?? '-' }}</td>
                            <td>{{ $item->notes ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-cell">Nenhuma entrada registrada ainda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>

@push('scripts')
    <script src="{{ asset('js/barcode-converter.js') }}?v={{ filemtime(public_path('js/barcode-converter.js')) }}"></script>
    <script>
        (function () {
            var barcodeInput = document.getElementById('barcode');
            var notesInput = document.getElementById('notes');
            var invoiceCustomerInput = document.getElementById('entry_customer_name');
            var invoiceNumberInput = document.getElementById('entry_invoice_number');
            var invoiceDestinationInput = document.getElementById('entry_destination');
            var lookupUrl = @json(route('expedition.lookup-invoice'));
            var lookupRequestId = 0;

            if (!barcodeInput) {
                return;
            }

            var form = barcodeInput.closest('form');
            if (!form) {
                return;
            }

            var barcodeOriginalInput = document.createElement('input');
            barcodeOriginalInput.type = 'hidden';
            barcodeOriginalInput.name = 'barcode_original';
            form.appendChild(barcodeOriginalInput);

            function clearInvoicePreview() {
                if (invoiceCustomerInput) { invoiceCustomerInput.value = ''; }
                if (invoiceNumberInput) { invoiceNumberInput.value = ''; }
                if (invoiceDestinationInput) { invoiceDestinationInput.value = ''; }
            }

            function applyInvoicePreview(lookup) {
                if (!lookup || !lookup.invoice || lookup.invoice.found !== true) {
                    clearInvoicePreview();
                    return;
                }

                if (invoiceCustomerInput) { invoiceCustomerInput.value = lookup.invoice.cliente || ''; }
                if (invoiceNumberInput) { invoiceNumberInput.value = lookup.invoice.numero || ''; }
                if (invoiceDestinationInput) { invoiceDestinationInput.value = lookup.invoice.destino || ''; }
            }

            function fetchInvoicePreview(serialValue, showMultipleAlert) {
                if (!lookupUrl || !serialValue) {
                    clearInvoicePreview();
                    return;
                }

                var currentRequestId = ++lookupRequestId;
                var url = lookupUrl + '?barcode=' + encodeURIComponent(serialValue);

                fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                })
                    .then(function (response) {
                        return response.ok ? response.json() : null;
                    })
                    .then(function (payload) {
                        if (!payload || currentRequestId !== lookupRequestId) {
                            return;
                        }

                        applyInvoicePreview(payload);

                        if (payload.invoice && payload.invoice.multiple === true && showMultipleAlert && typeof window.appAlert === 'function') {
                            window.appAlert({
                                icon: 'warning',
                                title: 'Mais de uma nota encontrada',
                                text: 'Existe mais de uma NF para este serial. Revise as notas antes de registrar a entrada.',
                            });
                        }
                    })
                    .catch(function () {
                        clearInvoicePreview();
                    });
            }

            function ensureConversionAndLookup(showMultipleAlert) {
                var parsed = BarcodeConverter.convert(barcodeInput.value);
                if (!parsed) {
                    clearInvoicePreview();
                    barcodeOriginalInput.value = '';
                    return;
                }

                barcodeOriginalInput.value = parsed.raw;

                if (parsed.converted) {
                    barcodeInput.value = parsed.serial;
                    if (notesInput) {
                        notesInput.value = BarcodeConverter.appendConversionNote(notesInput.value, parsed);
                    }
                }

                fetchInvoicePreview(parsed.serial, showMultipleAlert);
            }

            form.addEventListener('submit', function () {
                ensureConversionAndLookup(true);
            });
            barcodeInput.addEventListener('change', function () {
                ensureConversionAndLookup(false);
            });
            barcodeInput.addEventListener('blur', function () {
                ensureConversionAndLookup(false);
            });

            if (String(barcodeInput.value || '').trim() !== '') {
                ensureConversionAndLookup(false);
            }
        })();
    </script>
@endpush
