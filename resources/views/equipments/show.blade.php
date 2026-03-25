<x-layouts.app :title="'Detalhe do Equipamento'">
    <section class="panel-card">
        <div class="invoice-detail-top">
            <h2 class="section-title" style="margin:0;">Equipamento {{ $equipment->serial_number }}</h2>
            <div class="filters-actions">
                <button type="button" id="btn-editar" class="page-btn">Editar</button>
                <a href="{{ route('equipments.index') }}" class="page-btn page-btn-light">Voltar</a>
            </div>
        </div>

        <div class="invoice-kv-grid" style="margin-top:var(--space-3);">
            <div><strong>Modelo:</strong> {{ $equipment->model_name }}</div>
            <div><strong>Codigo de barras:</strong> {{ $equipment->barcode }}</div>
            <div>
                <strong>Status:</strong>
                <span class="status-badge" style="--status-color: @safeColor($equipment->status_color)">
                    {{ $equipment->status_name }}
                </span>
            </div>
            <div><strong>Setor:</strong> {{ $equipment->sector_name ?? '-' }}</div>
            <div><strong>Fabricado em:</strong> {{ $equipment->manufactured_at ? \Illuminate\Support\Carbon::parse($equipment->manufactured_at)->format('d/m/Y') : '-' }}</div>
            <div><strong>Montado em:</strong> {{ $equipment->assembled_at ? \Illuminate\Support\Carbon::parse($equipment->assembled_at)->format('d/m/Y') : '-' }}</div>
            <div><strong>Atualizado em:</strong> {{ \Illuminate\Support\Carbon::parse($equipment->updated_at)->format('d/m/Y H:i') }}</div>
            @if ($equipment->notes)
                <div><strong>Observacoes:</strong> {{ $equipment->notes }}</div>
            @endif
        </div>
    </section>

    <section class="panel-card">
        <h2 class="section-title">Dados da Nota Fiscal (Entrada)</h2>

        <div class="invoice-kv-grid">
            <div><strong>Numero da NF:</strong> {{ $equipment->entry_invoice_number ?? '-' }}</div>
            <div><strong>Cliente:</strong> {{ $equipment->entry_customer_name ?? '-' }}</div>
            <div><strong>Destino:</strong> {{ $equipment->entry_destination ?? '-' }}</div>
            <div><strong>ID da NF na Nomus:</strong> {{ $equipment->entry_invoice_external_id ?? '-' }}</div>
            <div><strong>Vinculada em:</strong> {{ $equipment->entry_invoice_linked_at ? \Illuminate\Support\Carbon::parse($equipment->entry_invoice_linked_at)->format('d/m/Y H:i') : '-' }}</div>
            <div><strong>Ultima atualizacao na Nomus:</strong> {{ $equipment->invoice_nomus_updated_at ? \Illuminate\Support\Carbon::parse($equipment->invoice_nomus_updated_at)->format('d/m/Y H:i') : '-' }}</div>
        </div>

        @if ($equipment->entry_invoice_id)
            <a href="{{ route('invoices.show', ['invoice' => $equipment->entry_invoice_id]) }}" class="page-btn" style="margin-top: var(--space-2); display: inline-block;">Abrir nota fiscal completa</a>
        @endif
    </section>

    <section class="panel-card">
        <a href="{{ route('historicos.index', ['q' => $equipment->serial_number]) }}" class="page-btn page-btn-light">Ver historico completo</a>
    </section>

    {{-- Modal de edicao --}}
    <div id="edit-modal" class="modal-overlay" style="display:none;">
        <div class="modal-card" style="max-width:540px;">
            <div class="modal-header">
                <h3 class="modal-title">Editar equipamento</h3>
                <button type="button" class="modal-close" id="edit-modal-close" aria-label="Fechar">&times;</button>
            </div>
            <form method="POST" action="{{ route('equipments.update', ['equipment' => $equipment->id]) }}" class="stack-16" style="margin-top:var(--space-4);">
                @csrf
                @method('PUT')
                <div>
                    <label class="panel-label" for="edit_serial">Numero de serie</label>
                    <input id="edit_serial" name="serial_number" type="text" class="input" value="{{ $equipment->serial_number }}" required maxlength="80">
                </div>
                <div>
                    <label class="panel-label" for="edit_barcode">Codigo de barras</label>
                    <input id="edit_barcode" name="barcode" type="text" class="input" value="{{ $equipment->barcode }}" required maxlength="120">
                </div>
                <div class="form-grid-2">
                    <div>
                        <label class="panel-label" for="edit_model">Modelo</label>
                        <select id="edit_model" name="equipment_model_id" class="chart-select" required>
                            @foreach ($models as $model)
                                <option value="{{ $model->id }}" {{ $equipment->equipment_model_id == $model->id ? 'selected' : '' }}>{{ $model->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="panel-label" for="edit_sector">Setor</label>
                        <select id="edit_sector" name="current_sector_id" class="chart-select">
                            <option value="">Nenhum</option>
                            @foreach ($sectors as $sector)
                                <option value="{{ $sector->id }}" {{ $equipment->current_sector_id == $sector->id ? 'selected' : '' }}>{{ $sector->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-grid-2">
                    <div>
                        <label class="panel-label" for="edit_manufactured">Fabricado em</label>
                        <input id="edit_manufactured" name="manufactured_at" type="date" class="input" value="{{ $equipment->manufactured_at ? \Illuminate\Support\Carbon::parse($equipment->manufactured_at)->format('Y-m-d') : '' }}">
                    </div>
                    <div>
                        <label class="panel-label" for="edit_assembled">Montado em</label>
                        <input id="edit_assembled" name="assembled_at" type="date" class="input" value="{{ $equipment->assembled_at ? \Illuminate\Support\Carbon::parse($equipment->assembled_at)->format('Y-m-d') : '' }}">
                    </div>
                </div>
                <div>
                    <label class="panel-label" for="edit_notes">Observacoes</label>
                    <textarea id="edit_notes" name="notes" class="input" rows="3" maxlength="2000" style="resize:vertical;">{{ $equipment->notes }}</textarea>
                </div>
                <div class="filters-actions">
                    <button type="submit" class="page-btn">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        (function () {
            var btn = document.getElementById('btn-editar');
            var modal = document.getElementById('edit-modal');
            var closeBtn = document.getElementById('edit-modal-close');
            if (!btn || !modal) return;

            btn.addEventListener('click', function () { modal.style.display = ''; });
            closeBtn.addEventListener('click', function () { modal.style.display = 'none'; });
            modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') modal.style.display = 'none'; });
        })();
    </script>
    @endpush
</x-layouts.app>
