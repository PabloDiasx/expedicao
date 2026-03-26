<x-layouts.app :title="'Entrada'">
    <section class="panel-card">
        <h2 class="section-title">Leitura para entrada</h2>

        <form id="expedition-form" method="POST" action="{{ route('expedition.store') }}" class="operation-form stack-16" novalidate>
            @csrf
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

            {{-- Feedback visual da conversao --}}
            <div id="conversion-feedback" class="conversion-feedback" style="display:none;">
                <span class="conversion-label">Código lido:</span>
                <span id="conversion-original" class="conversion-original"></span>
                <span class="conversion-arrow">→</span>
                <span class="conversion-label">Convertido:</span>
                <span id="conversion-result" class="conversion-result"></span>
            </div>

            {{-- Resultado da NF --}}
            <div id="invoice-card" class="entrada-nf-grid" style="display:none;">
                <div class="entrada-nf-item">
                    <span class="entrada-nf-label">Cliente</span>
                    <span id="entry_customer_name" class="entrada-nf-value">-</span>
                </div>
                <div class="entrada-nf-item">
                    <span class="entrada-nf-label">N° Nota</span>
                    <span id="entry_invoice_number" class="entrada-nf-value">-</span>
                </div>
                <div class="entrada-nf-item">
                    <span class="entrada-nf-label">Destino</span>
                    <span id="entry_destination" class="entrada-nf-value">-</span>
                </div>
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
                if (loading) {
                    invoiceCard.style.display = '';
                    invoiceCustomerInput.textContent = 'Buscando...';
                    invoiceNumberInput.textContent = '';
                    invoiceDestinationInput.textContent = '';
                }
            }

            function clearInvoicePreview() {
                invoiceCard.style.display = 'none';
                invoiceCustomerInput.textContent = '-';
                invoiceNumberInput.textContent = '-';
                invoiceDestinationInput.textContent = '-';
                cachedInvoiceData = null;
                hiddenInvoiceNumber.value = '';
                hiddenCustomerName.value = '';
                hiddenDestination.value = '';
            }

            function applyInvoicePreview(lookup) {
                if (!lookup || !lookup.invoice) { clearInvoicePreview(); return; }
                cachedInvoiceData = lookup;

                invoiceCard.style.display = '';

                if (lookup.invoice.found === true) {
                    invoiceCustomerInput.textContent = lookup.invoice.cliente || '-';
                    invoiceNumberInput.textContent = lookup.invoice.numero || '-';
                    invoiceDestinationInput.textContent = lookup.invoice.destino || '-';
                    hiddenInvoiceNumber.value = lookup.invoice.numero || '';
                    hiddenCustomerName.value = lookup.invoice.cliente || '';
                    hiddenDestination.value = lookup.invoice.destino || '';
                } else if (lookup.invoice.multiple === true) {
                    invoiceCustomerInput.textContent = '';
                    invoiceNumberInput.textContent = 'Multiplas NFs';
                    invoiceDestinationInput.textContent = '';
                    hiddenInvoiceNumber.value = '';
                    hiddenCustomerName.value = '';
                    hiddenDestination.value = '';
                } else {
                    invoiceCustomerInput.textContent = 'Sem NF';
                    invoiceNumberInput.textContent = '-';
                    invoiceDestinationInput.textContent = '-';
                    hiddenInvoiceNumber.value = '';
                    hiddenCustomerName.value = '';
                    hiddenDestination.value = '';
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
                    // Foco volta pro barcode, nao pro botao
                    if (barcodeInput) barcodeInput.focus();
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
                    // Salvar codigo de barras original na observacao
                    if (notesInput && notesInput.value.trim() === '') {
                        notesInput.value = parsed.raw;
                    }
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

            // Retornar foco ao barcode apenas se saiu do form inteiro
            barcodeInput.addEventListener('blur', function () {
                setTimeout(function () {
                    if (!form.contains(document.activeElement)) {
                        barcodeInput.focus();
                    }
                }, 150);
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
