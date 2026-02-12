<?php
/**
 * Shared Element: Search Bar
 *
 * Barra de búsqueda animada reutilizable para Tickets y PQRS
 *
 * @var string $searchValue Valor actual de búsqueda
 * @var string $placeholder Texto del placeholder
 * @var string $entityType 'ticket' o 'pqrs'
 * @var string $view Vista actual para preservar en la búsqueda
 */

// Variables passed directly from element() call
$searchValue = $searchValue ?? '';
$placeholder = $placeholder ?? 'Buscar...';
$entityType = $entityType ?? 'ticket';
$view = $view ?? '';
?>

<div class="flex-grow-1">
    <?= $this->Form->create(null, ['type' => 'get', 'class' => 'd-flex align-items-center justify-content-between w-100', 'id' => 'searchForm']) ?>
    <?= $this->Form->hidden('view', ['value' => $view]) ?>

    <div class="search-container">
        <div class="input-group search-input-group">
            <span class="input-group-text search-icon" id="searchIcon">
                <i class="bi bi-search"></i>
            </span>
            <?= $this->Form->control('search', [
                'label' => false,
                'class' => 'form-control search-input fw-light',
                'placeholder' => $placeholder,
                'value' => $searchValue,
                'type' => 'text',
                'id' => 'searchInput',
                'autoComplete' => 'off',
            ]) ?>
        </div>
    </div>

    <?php if (!empty($searchValue)): ?>
        <?= $this->Html->link('<i class="bi bi-x-circle-fill"></i>', ['action' => 'index', '?' => ['view' => $view]], [
            'class' => 'btn btn-link text-danger clear-search-btn',
            'escape' => false,
            'title' => 'Limpiar búsqueda'
        ]) ?>
    <?php endif; ?>

    <button type="button" id="btn-refresh-list" class="btn btn-sm btn-outline-secondary bg-white rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; padding: 0; flex-shrink: 0;" title="Recargar lista">
        <i class="bi bi-arrow-clockwise" style="font-size: 16px;"></i>
    </button>   

    <?= $this->Form->end() ?>
</div>
