<x-layouts.app :title="'Entrada'">
    {{-- DEBUG removido --}}
    <section class="panel-card">
        <h2 class="section-title">Leitura para entrada</h2>

        <form id="expedition-form" method="POST" action="{{ route('expedition.store') }}" class="operation-form stack-16" novalidate>
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

            {{-- Feedback visual da conversao --}}
            <div id="conversion-feedback" class="conversion-feedback" style="display:none;">
                <span class="conversion-label">Codigo lido:</span>
                <span id="conversion-original" class="conversion-original"></span>
                <span class="conversion-arrow">→</span>
                <span class="conversion-label">Convertido:</span>
                <span id="conversion-result" class="conversion-result"></span>
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

            {{-- Card de resumo da NF --}}
            <div id="invoice-card" class="invoice-card invoice-card--idle">
                <div class="invoice-card-body">
                    <div class="invoice-card-field">
                        <label class="panel-label">Nome Cliente</label>
                        <input
                            id="entry_customer_name"
                            name="entry_customer_name"
                            type="text"
                            class="input"
                            value=""
                            readonly
                            tabindex="-1"
                            placeholder="-"
                        >
                    </div>

                    <div class="invoice-card-field">
                        <label class="panel-label">N° Nota</label>
                        <input
                            id="entry_invoice_number"
                            name="entry_invoice_number"
                            type="text"
                            class="input"
                            value=""
                            readonly
                            tabindex="-1"
                            placeholder="-"
                        >
                    </div>

                    <div class="invoice-card-field">
                        <label class="panel-label">Destino</label>
                        <input
                            id="entry_destination"
                            name="entry_destination"
                            type="text"
                            class="input"
                            value=""
                            readonly
                            tabindex="-1"
                            placeholder="-"
                        >
                    </div>
                </div>
            </div>

            <div class="filters-actions">
                <button id="btn-registrar" type="submit" class="page-btn">Registrar entrada</button>
            </div>
        </form>
    </section>


    @push('scripts')
    <script src="{{ asset('js/barcode-converter.js') }}?v={{ filemtime(public_path('js/barcode-converter.js')) }}"></script>
    <script>
        (function () {
            var barcodeInput = document.getElementById('barcode');
            var notesInput = document.getElementById('notes');
            var invoiceCustomerInput = document.getElementById('entry_customer_name');
            var invoiceNumberInput = document.getElementById('entry_invoice_number');
            var invoiceDestinationInput = document.getElementById('entry_destination');
            var conversionFeedback = document.getElementById('conversion-feedback');
            var conversionOriginal = document.getElementById('conversion-original');
            var conversionResult = document.getElementById('conversion-result');
            var invoiceCard = document.getElementById('invoice-card');
            var btnRegistrar = document.getElementById('btn-registrar');
            var lookupUrl = @json(route('expedition.lookup-invoice'));
            var lookupRequestId = 0;
            var cachedInvoiceData = null;

            if (!barcodeInput) return;

            var form = document.getElementById('expedition-form');
            if (!form) return;

            var barcodeOriginalInput = document.createElement('input');
            barcodeOriginalInput.type = 'hidden';
            barcodeOriginalInput.name = 'barcode_original';
            form.appendChild(barcodeOriginalInput);

            var hiddenInvoiceNumber = document.createElement('input');
            hiddenInvoiceNumber.type = 'hidden';
            hiddenInvoiceNumber.name = 'cached_invoice_number';
            form.appendChild(hiddenInvoiceNumber);

            var hiddenCustomerName = document.createElement('input');
            hiddenCustomerName.type = 'hidden';
            hiddenCustomerName.name = 'cached_customer_name';
            form.appendChild(hiddenCustomerName);

            var hiddenDestination = document.createElement('input');
            hiddenDestination.type = 'hidden';
            hiddenDestination.name = 'cached_destination';
            form.appendChild(hiddenDestination);

            // ── UI State ──
            function setInvoiceCardState(state) {
                invoiceCard.className = 'invoice-card invoice-card--' + state;
            }

            function showConversionFeedback(raw, converted) {
                if (conversionFeedback && raw !== converted) {
                    conversionOriginal.textContent = raw;
                    conversionResult.textContent = converted;
                    conversionFeedback.style.display = '';
                } else if (conversionFeedback) {
                    conversionFeedback.style.display = 'none';
                }
            }

            function setPreviewLoading(loading) {
                [invoiceCustomerInput, invoiceNumberInput, invoiceDestinationInput].forEach(function (field) {
                    if (!field) return;
                    field.value = loading ? 'Buscando...' : '';
                    field.classList.toggle('is-loading', loading);
                });
                if (loading) setInvoiceCardState('loading');
            }

            function clearInvoicePreview() {
                if (invoiceCustomerInput) invoiceCustomerInput.value = '';
                if (invoiceNumberInput) invoiceNumberInput.value = '';
                if (invoiceDestinationInput) invoiceDestinationInput.value = '';
                setInvoiceCardState('idle');
                cachedInvoiceData = null;
                hiddenInvoiceNumber.value = '';
                hiddenCustomerName.value = '';
                hiddenDestination.value = '';
            }

            function applyInvoicePreview(lookup) {
                if (!lookup || !lookup.invoice) { clearInvoicePreview(); return; }
                cachedInvoiceData = lookup;

                if (lookup.invoice.found === true) {
                    if (invoiceCustomerInput) invoiceCustomerInput.value = lookup.invoice.cliente || '';
                    if (invoiceNumberInput) invoiceNumberInput.value = lookup.invoice.numero || '';
                    if (invoiceDestinationInput) invoiceDestinationInput.value = lookup.invoice.destino || '';
                    hiddenInvoiceNumber.value = lookup.invoice.numero || '';
                    hiddenCustomerName.value = lookup.invoice.cliente || '';
                    hiddenDestination.value = lookup.invoice.destino || '';
                    setInvoiceCardState('found');
                } else if (lookup.invoice.multiple === true) {
                    if (invoiceCustomerInput) invoiceCustomerInput.value = '';
                    if (invoiceNumberInput) invoiceNumberInput.value = 'Multiplas NFs';
                    if (invoiceDestinationInput) invoiceDestinationInput.value = '';
                    hiddenInvoiceNumber.value = '';
                    hiddenCustomerName.value = '';
                    hiddenDestination.value = '';
                    setInvoiceCardState('multiple');
                } else {
                    if (invoiceCustomerInput) invoiceCustomerInput.value = '';
                    if (invoiceNumberInput) invoiceNumberInput.value = '';
                    if (invoiceDestinationInput) invoiceDestinationInput.value = '';
                    hiddenInvoiceNumber.value = '';
                    hiddenCustomerName.value = '';
                    hiddenDestination.value = '';
                    setInvoiceCardState('not-found');
                }
            }

            function fetchInvoicePreview(serialValue, showMultipleAlert) {
                if (!lookupUrl || !serialValue) { clearInvoicePreview(); return; }

                var currentRequestId = ++lookupRequestId;
                var url = lookupUrl + '?barcode=' + encodeURIComponent(serialValue);
                setPreviewLoading(true);
                var controller = new AbortController();
                var timeoutId = setTimeout(function () { controller.abort(); }, 10000);

                fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                })
                .then(function (r) { clearTimeout(timeoutId); return r.ok ? r.json() : null; })
                .then(function (payload) {
                    if (!payload || currentRequestId !== lookupRequestId) return;
                    setPreviewLoading(false);
                    applyInvoicePreview(payload);
                    if (payload.invoice && payload.invoice.multiple === true && showMultipleAlert && typeof window.appAlert === 'function') {
                        window.appAlert({ icon: 'warning', title: 'Mais de uma nota encontrada', text: 'Existe mais de uma NF para este serial. Revise as notas antes de registrar a entrada.' });
                    }
                    if (btnRegistrar) btnRegistrar.focus();
                })
                .catch(function () { clearTimeout(timeoutId); setPreviewLoading(false); clearInvoicePreview(); });
            }

            function runConversionAndLookup(showMultipleAlert) {
                var parsed = BarcodeConverter.convert(barcodeInput.value);
                if (!parsed) {
                    clearInvoicePreview();
                    barcodeOriginalInput.value = '';
                    if (conversionFeedback) conversionFeedback.style.display = 'none';
                    return;
                }
                barcodeOriginalInput.value = parsed.raw;
                if (parsed.converted) {
                    barcodeInput.value = parsed.serial;
                    showConversionFeedback(parsed.raw, parsed.serial);
                } else {
                    showConversionFeedback(parsed.raw, parsed.serial);
                }
                fetchInvoicePreview(parsed.serial, showMultipleAlert);
            }

            // ── BUG FIX: Impedir Enter de submeter o form ──
            // Scanners enviam Enter apos leitura. Bloqueamos no form inteiro.
            var submitIntentional = false;

            form.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    if (e.target === btnRegistrar) return;
                    e.preventDefault();
                    e.stopPropagation();
                    if (e.target === barcodeInput) {
                        runConversionAndLookup(true);
                        // Seleciona todo o texto para que o proximo scan substitua
                        barcodeInput.select();
                    }
                }
            });

            barcodeInput.addEventListener('change', function () { runConversionAndLookup(false); });

            btnRegistrar.addEventListener('click', function () { submitIntentional = true; });

            // Manter foco sempre no campo barcode
            barcodeInput.addEventListener('blur', function () {
                setTimeout(function () {
                    if (document.activeElement !== btnRegistrar) {
                        barcodeInput.focus();
                    }
                }, 100);
            });

            form.addEventListener('submit', function (e) {
                if (!submitIntentional) { e.preventDefault(); return; }
                submitIntentional = false;
                var parsed = BarcodeConverter.convert(barcodeInput.value);
                if (parsed) {
                    barcodeOriginalInput.value = parsed.raw;
                    if (parsed.converted) barcodeInput.value = parsed.serial;
                }
            });

            if (String(barcodeInput.value || '').trim() !== '') {
                runConversionAndLookup(false);
            }
        })();
    </script>
    @endpush
</x-layouts.app>
