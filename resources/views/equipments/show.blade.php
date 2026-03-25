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

    @if ($equipment->vendedor || $equipment->cliente_venda || $equipment->destino_venda)
    <section class="panel-card">
        <h2 class="section-title">Dados de venda</h2>
        <div class="invoice-kv-grid">
            <div><strong>Vendedor:</strong> {{ $equipment->vendedor ?? '-' }}</div>
            <div><strong>Cliente:</strong> {{ $equipment->cliente_venda ?? '-' }}</div>
            <div><strong>Destino:</strong> {{ $equipment->destino_venda ?? '-' }}</div>
        </div>
    </section>
    @endif

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
                    <label class="panel-label" for="edit_vendedor">Vendedor</label>
                    <input id="edit_vendedor" name="vendedor" type="text" class="input" value="{{ $equipment->vendedor }}" maxlength="150" placeholder="Nome do vendedor">
                </div>
                <div>
                    <label class="panel-label" for="edit_cliente_venda">Cliente (venda)</label>
                    <input id="edit_cliente_venda" name="cliente_venda" type="text" class="input" value="{{ $equipment->cliente_venda }}" maxlength="150" placeholder="Para quem vai ser vendido">
                </div>
                <div>
                    <label class="panel-label" for="edit_destino_venda">Destino (venda)</label>
                    <input id="edit_destino_venda" name="destino_venda" type="text" class="input" value="{{ $equipment->destino_venda }}" maxlength="200" placeholder="Para onde vai">
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
