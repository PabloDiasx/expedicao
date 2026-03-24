<x-layouts.app :title="'Entrada'">
    <section class="panel-card">
        <h2 class="section-title">Leitura para entrada</h2>

        @if (! $dispatchStatus || ! $dispatchSector)
            <div class="alert-warning">
                Configuracao incompleta: status "Carregado" ou setor "Expedicao" nao encontrados.
            </div>
        @else
            <div class="expedition-meta">
                <span class="status-badge" style="--status-color: {{ $dispatchStatus->color }}">
                    Status final: {{ $dispatchStatus->name }}
                </span>
                <span class="expedition-sector">Fluxo: Entrada</span>
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
                                <span class="status-badge" style="--status-color: {{ $item->status_color }}">
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
    <script>
        (function () {
            const barcodeInput = document.getElementById('barcode');
            const notesInput = document.getElementById('notes');
            const invoiceCustomerInput = document.getElementById('entry_customer_name');
            const invoiceNumberInput = document.getElementById('entry_invoice_number');
            const invoiceDestinationInput = document.getElementById('entry_destination');
            const lookupUrl = @json(route('expedition.lookup-invoice'));
            let lookupRequestId = 0;

            if (!barcodeInput) {
                return;
            }

            const form = barcodeInput.closest('form');
            if (!form) {
                return;
            }

            function convertScannerCodeToSerial(rawValue) {
                const normalized = String(rawValue || '')
                    .trim()
                    .toUpperCase()
                    .replace(/\s+/g, '');

                if (!normalized) {
                    return null;
                }

                if (/^[A-Z0-9]+\.[0-9]{2}\.[0-9]+$/.test(normalized)) {
                    return {
                        raw: normalized,
                        serial: normalized,
                        converted: false,
                    };
                }

                const dashedMatches = normalized.match(/^([A-Z]+[0-9]+).*-([0-9]{2})\.([0-9]{1,8})$/);
                if (dashedMatches) {
                    const model = dashedMatches[1];
                    const year = dashedMatches[2];
                    const serialNumber = String(parseInt(dashedMatches[3], 10));
                    const serial = `${model}.${year}.${serialNumber}`;

                    return {
                        raw: normalized,
                        serial,
                        converted: serial !== normalized,
                    };
                }

                const dashedTailMatches = normalized.match(/-([0-9]{2})\.([0-9]{1,8})$/);
                if (dashedTailMatches) {
                    const modelMatches = normalized.match(/^(V[0-9]{1,2})/);
                    if (modelMatches) {
                        const model = modelMatches[1];
                        const year = dashedTailMatches[1];
                        const serialNumber = String(parseInt(dashedTailMatches[2], 10));
                        const serial = `${model}.${year}.${serialNumber}`;

                        return {
                            raw: normalized,
                            serial,
                            converted: serial !== normalized,
                        };
                    }
                }

                const matches = normalized.match(/^([A-Z]+[0-9]+)[A-Z]{1,6}([0-9]{2})([0-9]{2,8})$/);
                if (!matches) {
                    return null;
                }

                const model = matches[1];
                const year = matches[2];
                const serialNumber = String(parseInt(matches[3], 10));
                const serial = `${model}.${year}.${serialNumber}`;

                return {
                    raw: normalized,
                    serial,
                    converted: serial !== normalized,
                };
            }

            function clearInvoicePreview() {
                if (invoiceCustomerInput) {
                    invoiceCustomerInput.value = '';
                }

                if (invoiceNumberInput) {
                    invoiceNumberInput.value = '';
                }

                if (invoiceDestinationInput) {
                    invoiceDestinationInput.value = '';
                }
            }

            function applyInvoicePreview(lookup) {
                if (!lookup || !lookup.invoice || lookup.invoice.found !== true) {
                    clearInvoicePreview();
                    return;
                }

                if (invoiceCustomerInput) {
                    invoiceCustomerInput.value = lookup.invoice.cliente || '';
                }

                if (invoiceNumberInput) {
                    invoiceNumberInput.value = lookup.invoice.numero || '';
                }

                if (invoiceDestinationInput) {
                    invoiceDestinationInput.value = lookup.invoice.destino || '';
                }
            }

            function appendConversionToNotes(parsed) {
                if (!notesInput || !parsed || !parsed.converted) {
                    return;
                }

                const conversionLabel = `Codigo lido: ${parsed.raw} | Serial convertido: ${parsed.serial}`;
                const currentNotes = String(notesInput.value || '').trim();

                if (currentNotes.includes(conversionLabel)) {
                    return;
                }

                notesInput.value = currentNotes === ''
                    ? conversionLabel
                    : `${currentNotes} | ${conversionLabel}`;
            }

            function fetchInvoicePreview(parsed, showMultipleAlert) {
                if (!lookupUrl || !parsed || !parsed.serial) {
                    clearInvoicePreview();
                    return;
                }

                const currentRequestId = ++lookupRequestId;
                const url = `${lookupUrl}?barcode=${encodeURIComponent(parsed.serial)}`;

                fetch(url, {
                    headers: {
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                })
                    .then(function (response) {
                        if (!response.ok) {
                            return null;
                        }

                        return response.json();
                    })
                    .then(function (payload) {
                        if (!payload || currentRequestId !== lookupRequestId) {
                            return;
                        }

                        const convertedBarcode = String(payload.barcode_convertido || '').trim();
                        if (convertedBarcode && barcodeInput.value !== convertedBarcode) {
                            barcodeInput.value = convertedBarcode;
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
                const parsed = convertScannerCodeToSerial(barcodeInput.value);
                if (!parsed) {
                    clearInvoicePreview();
                    return;
                }

                if (parsed.serial !== barcodeInput.value) {
                    barcodeInput.value = parsed.serial;
                }

                appendConversionToNotes(parsed);
                fetchInvoicePreview(parsed, showMultipleAlert);
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
