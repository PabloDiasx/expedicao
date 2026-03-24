<x-layouts.app :title="'Modelos de Equipamentos'">
    <section class="panel-card">
        <h2 class="section-title">Cadastrar modelo</h2>
        <form method="POST" action="{{ route('equipment-models.store') }}" class="stack-16" novalidate>
            @csrf
            <input type="hidden" name="is_active" value="0">

            <div class="form-grid-2">
                <div>
                    <label class="panel-label" for="name">Nome</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        class="input"
                        value="{{ old('name') }}"
                        maxlength="150"
                        placeholder="Ex: V8 Cadillac"
                        required
                    >
                </div>

                <div>
                    <label class="panel-label" for="category">Categoria</label>
                    <input
                        id="category"
                        name="category"
                        type="text"
                        class="input"
                        value="{{ old('category') }}"
                        maxlength="100"
                        placeholder="Ex: Cadillac"
                    >
                </div>
            </div>

            <div class="form-grid-2">
                <div>
                    <label class="panel-label" for="is_active_checkbox">Ativo</label>
                    <label class="remember" for="is_active_checkbox">
                        <input
                            id="is_active_checkbox"
                            name="is_active"
                            type="checkbox"
                            value="1"
                            {{ old('is_active', '1') === '1' ? 'checked' : '' }}
                        >
                        Disponivel para uso
                    </label>
                </div>
            </div>

            <div class="filters-actions">
                <button type="submit" class="page-btn">Cadastrar modelo</button>
            </div>
        </form>
    </section>

    <section class="panel-card">
        <form method="GET" action="{{ route('equipment-models.index') }}" class="filters-grid">
            <div>
                <label class="panel-label" for="q">Busca</label>
                <input
                    id="q"
                    name="q"
                    type="text"
                    class="input"
                    value="{{ $filters['q'] }}"
                    placeholder="Nome ou categoria"
                >
            </div>

            <div>
                <label class="panel-label" for="active">Status</label>
                <select id="active" name="active" class="chart-select">
                    <option value="all" {{ $filters['active'] === 'all' ? 'selected' : '' }}>Todos</option>
                    <option value="1" {{ $filters['active'] === '1' ? 'selected' : '' }}>Ativos</option>
                    <option value="0" {{ $filters['active'] === '0' ? 'selected' : '' }}>Inativos</option>
                </select>
            </div>

            <div class="filters-actions">
                <button type="submit" class="page-btn">Filtrar</button>
                <a href="{{ route('equipment-models.index') }}" class="page-btn page-btn-light">Limpar</a>
            </div>
        </form>
    </section>

    <section class="panel-card">
        <h2 class="section-title">Modelos cadastrados</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Categoria</th>
                        <th>Status</th>
                        <th>Criado em</th>
                        <th class="table-action-head" aria-label="Remover"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($models as $model)
                        <tr>
                            <td>{{ $model->name }}</td>
                            <td>{{ $model->category ?: '-' }}</td>
                            <td>
                                <span
                                    class="status-badge"
                                    style="--status-color: {{ $model->is_active ? '#16a34a' : '#dc2626' }}"
                                >
                                    {{ $model->is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td>{{ \Illuminate\Support\Carbon::parse($model->created_at)->format('d/m/Y H:i') }}</td>
                            <td class="table-action-cell">
                                <form
                                    class="table-action-form js-model-delete-form"
                                    method="POST"
                                    action="{{ route('equipment-models.destroy', ['model' => $model->id]) }}"
                                    data-model-name="{{ $model->name }}"
                                    onsubmit="return window.confirmModelDeleteForm(this);"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="delete_confirmation" value="0" class="js-delete-confirmation-flag">
                                    <button
                                        type="submit"
                                        class="table-action-delete"
                                        title="Remover modelo"
                                        aria-label="Remover modelo"
                                    >
                                        <svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none">
                                            <path d="M4 7H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                                            <path d="M9 7V5C9 4.4 9.4 4 10 4H14C14.6 4 15 4.4 15 5V7" stroke="currentColor" stroke-width="2"></path>
                                            <path d="M7 7L8 19C8 19.6 8.4 20 9 20H15C15.6 20 16 19.6 16 19L17 7" stroke="currentColor" stroke-width="2"></path>
                                            <path d="M10 11V16M14 11V16" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                                        </svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-cell">Nenhum modelo cadastrado ainda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($models->hasPages())
            <div class="pagination-bar">
                @if ($models->onFirstPage())
                    <span class="pagination-link pagination-disabled">Anterior</span>
                @else
                    <a class="pagination-link" href="{{ $models->previousPageUrl() }}">Anterior</a>
                @endif

                <span class="pagination-current">
                    Pagina {{ $models->currentPage() }} de {{ $models->lastPage() }}
                </span>

                @if ($models->hasMorePages())
                    <a class="pagination-link" href="{{ $models->nextPageUrl() }}">Proxima</a>
                @else
                    <span class="pagination-link pagination-disabled">Proxima</span>
                @endif
            </div>
        @endif
    </section>
</x-layouts.app>
