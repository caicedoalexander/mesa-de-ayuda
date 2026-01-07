<?php
/**
 * Shared Element: Bulk Actions Bar
 *
 * Barra de acciones masivas reutilizable para Tickets y PQRS
 *
 * @var string $entityType 'ticket' o 'pqrs'
 * @var bool $showTagAction Mostrar acción de tags (default: false)
 */

// Variables passed directly from element() call
$entityType = $entityType ?? 'ticket';
$showTagAction = $showTagAction ?? false;
$entityLabel = $entityType === 'ticket' ? 'ticket(s)' : 'PQRS';
?>

<!-- Bulk Actions Bar -->
<div id="bulkActionsBar"
    class="alert alert-info m-0 flex-grow-1 border-0 d-none d-flex align-items-center justify-content-between bulk-actions-animated"
    style="position: relative; z-index: 1000; padding: 5px 12px;">
    <div>
        <i class="bi bi-check-circle me-2"></i>
        <strong><span id="selectedCount">0</span> <?= $entityLabel ?> seleccionado(s)</strong>
    </div>
    <div class="btn-group">
        <button type="button" class="btn btn-sm btn-primary dropdown-toggle rounded me-2"
            data-bs-toggle="dropdown" aria-expanded="false">
            Acciones rápidas
        </button>
        <ul class="dropdown-menu dropdown-menu-animated">
            <li><a class="dropdown-item" href="#" onclick="bulkAction('assign'); return false;">
                    <i class="bi bi-person-fill-add me-2"></i>Asignar a agente
                </a></li>
            <li><a class="dropdown-item" href="#" onclick="bulkAction('changePriority'); return false;">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>Cambiar prioridad
                </a></li>
            <?php if ($showTagAction): ?>
            <li>
                <hr class="dropdown-divider">
            </li>
            <li><a class="dropdown-item" href="#" onclick="bulkAction('addTag'); return false;">
                    <i class="bi bi-tag me-2"></i>Agregar etiqueta
                </a></li>
            <?php endif; ?>
            <li>
                <hr class="dropdown-divider">
            </li>
            <li><a class="dropdown-item text-danger" href="#" onclick="bulkAction('delete'); return false;">
                    <i class="bi bi-trash me-2"></i>Eliminar
                </a></li>
        </ul>
        <button type="button" class="btn btn-sm btn-danger rounded" onclick="clearSelection()">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
</div>
