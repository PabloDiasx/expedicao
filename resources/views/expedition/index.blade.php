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

            {{-- Card de resumo da NF --}}
            <div id="invoice-card" class="invoice-card invoice-card--idle">
                <div class="invoice-card-header">
                    <span id="invoice-card-icon" class="invoice-card-icon">📋</span>
                    <span id="invoice-card-title" class="invoice-card-title">Aguardando leitura...</span>
                </div>
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

    <section class="panel-card">
        <h2 class="section-title">Ultimas entradas</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Serial</th>
                        <th>Codigo de barras</th>
                        <th>NF</th>
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
                                @if($item->entry_invoice_number)
                                    <span class="status-badge" style="--status-color: #22c55e">{{ $item->entry_invoice_number }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
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
                            <td colspan="7" class="empty-cell">Nenhuma entrada registrada ainda.</td>
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
            var invoiceCustomerInput = document.getElementById('entry_customer_name');
            var invoiceNumberInput = document.getElementById('entry_invoice_number');
            var invoiceDestinationInput = document.getElementById('entry_destination');
            var conversionFeedback = document.getElementById('conversion-feedback');
            var conversionOriginal = document.getElementById('conversion-original');
            var conversionResult = document.getElementById('conversion-result');
            var invoiceCard = document.getElementById('invoice-card');
            var invoiceCardIcon = document.getElementById('invoice-card-icon');
            var invoiceCardTitle = document.getElementById('invoice-card-title');
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

            // ── Audio feedback ──
            var audioCtx = null;
            function getAudioContext() {
                if (!audioCtx) {
                    try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) {}
                }
                return audioCtx;
            }

            function playBeep(frequency, duration, type) {
                var ctx = getAudioContext();
                if (!ctx) return;
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = frequency;
                osc.type = type || 'sine';
                gain.gain.value = 0.3;
                osc.start();
                osc.stop(ctx.currentTime + (duration / 1000));
            }

            function beepSuccess() {
                playBeep(880, 150, 'sine');
                setTimeout(function () { playBeep(1100, 150, 'sine'); }, 160);
            }

            function beepWarning() {
                playBeep(440, 200, 'triangle');
                setTimeout(function () { playBeep(330, 300, 'triangle'); }, 220);
            }

            function beepNoInvoice() {
                playBeep(600, 120, 'sine');
            }

            // ── UI State ──
            function setInvoiceCardState(state, title) {
                invoiceCard.className = 'invoice-card invoice-card--' + state;
                invoiceCardTitle.textContent = title || '';
                var icons = { found: '✅', 'not-found': '⚠️', loading: '🔍', multiple: '⚠️' };
                invoiceCardIcon.textContent = icons[state] || '📋';
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
                if (loading) setInvoiceCardState('loading', 'Buscando nota fiscal...');
            }

            function clearInvoicePreview() {
                if (invoiceCustomerInput) invoiceCustomerInput.value = '';
                if (invoiceNumberInput) invoiceNumberInput.value = '';
                if (invoiceDestinationInput) invoiceDestinationInput.value = '';
                setInvoiceCardState('idle', 'Aguardando leitura...');
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
                    setInvoiceCardState('found', 'Nota fiscal encontrada');
                    beepSuccess();
                } else if (lookup.invoice.multiple === true) {
                    if (invoiceCustomerInput) invoiceCustomerInput.value = '';
                    if (invoiceNumberInput) invoiceNumberInput.value = 'Multiplas NFs';
                    if (invoiceDestinationInput) invoiceDestinationInput.value = '';
                    hiddenInvoiceNumber.value = '';
                    hiddenCustomerName.value = '';
                    hiddenDestination.value = '';
                    setInvoiceCardState('multiple', 'Mais de uma nota encontrada — revise antes de registrar');
                    beepWarning();
                } else {
                    if (invoiceCustomerInput) invoiceCustomerInput.value = '';
                    if (invoiceNumberInput) invoiceNumberInput.value = '';
                    if (invoiceDestinationInput) invoiceDestinationInput.value = '';
                    hiddenInvoiceNumber.value = '';
                    hiddenCustomerName.value = '';
                    hiddenDestination.value = '';
                    setInvoiceCardState('not-found', 'Nenhuma nota fiscal encontrada para este serial');
                    beepNoInvoice();
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
                    if (e.target === barcodeInput) runConversionAndLookup(true);
                }
            });

            barcodeInput.addEventListener('change', function () { runConversionAndLookup(false); });
            barcodeInput.addEventListener('blur', function () { runConversionAndLookup(false); });

            btnRegistrar.addEventListener('click', function () { submitIntentional = true; });

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
