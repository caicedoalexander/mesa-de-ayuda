<?php
/**
 * KPI Cards - Reusable statistics cards for all modules
 *
 * @var int $total Total entities
 * @var int $recentCount Recent count (7 days)
 * @var int $unassignedCount Unassigned count
 * @var int $activeAgentsCount Active agents
 * @var string $entityType 'ticket', 'pqrs', or 'compra'
 * @var array|null $slaMetrics SLA metrics (Compras only)
 */

$entityLabels = [
    'ticket' => 'Tickets',
    'pqrs' => 'PQRS',
    'compra' => 'Compras',
];
$label = $entityLabels[$entityType] ?? 'Entidades';

$entityIcons = [
    'ticket' => 'bi-ticket-perforated',
    'pqrs' => 'bi-chat-left-text',
    'compra' => 'bi-cart-check',
];
$icon = $entityIcons[$entityType] ?? 'bi-inbox';
?>

<div class="row mb-5">
    <!-- Total -->
    <div class="col-md-3">
        <div class="card neuro-card neuro-hover" data-animate-in="fade-up" data-delay="0">
            <div class="card-body text-center py-4">
                <div class="neuro-icon-wrapper mb-3">
                    <i class="bi <?= $icon ?> neuro-icon" style="color: var(--neuro-primary);"></i>
                </div>
                <h3 class="neuro-counter mb-2" data-counter data-target="<?= $total ?>" aria-live="polite" aria-atomic="true">0</h3>
                <p class="neuro-label mb-0">Total <?= h($label) ?></p>
            </div>
        </div>
    </div>

    <!-- Recent (7 days) -->
    <div class="col-md-3">
        <div class="card neuro-card neuro-hover" data-animate-in="fade-up" data-delay="100">
            <div class="card-body text-center py-4">
                <div class="neuro-icon-wrapper mb-3">
                    <i class="bi bi-clock-history neuro-icon" style="color: var(--neuro-info);"></i>
                </div>
                <h3 class="neuro-counter mb-2" data-counter data-target="<?= $recentCount ?>" aria-live="polite" aria-atomic="true">0</h3>
                <p class="neuro-label mb-0">Últimos 7 días</p>
            </div>
        </div>
    </div>

    <!-- Unassigned -->
    <div class="col-md-3">
        <div class="card neuro-card neuro-hover" data-animate-in="fade-up" data-delay="200">
            <div class="card-body text-center py-4">
                <div class="neuro-icon-wrapper mb-3">
                    <i class="bi bi-person-x-fill neuro-icon" style="color: var(--neuro-danger);"></i>
                </div>
                <h3 class="neuro-counter mb-2" data-counter data-target="<?= $unassignedCount ?>" aria-live="polite" aria-atomic="true">0</h3>
                <p class="neuro-label mb-0">Sin Asignar</p>
            </div>
        </div>
    </div>

    <!-- Active Agents OR SLA Compliance (for Compras) -->
    <div class="col-md-3">
        <div class="card neuro-card neuro-hover" data-animate-in="fade-up" data-delay="300">
            <div class="card-body text-center py-4">
                <?php if ($entityType === 'compra' && isset($slaMetrics)): ?>
                    <div class="neuro-icon-wrapper mb-3">
                        <i class="bi bi-speedometer2 neuro-icon" style="color: var(--neuro-success);"></i>
                    </div>
                    <h3 class="neuro-counter mb-2" data-counter data-target="<?= (int)$slaMetrics['compliance_rate'] ?>" aria-live="polite" aria-atomic="true">0</h3>
                    <p class="neuro-label mb-0">Cumplimiento SLA %</p>
                <?php else: ?>
                    <div class="neuro-icon-wrapper mb-3">
                        <i class="bi bi-people neuro-icon" style="color: var(--neuro-success);"></i>
                    </div>
                    <h3 class="neuro-counter mb-2" data-counter data-target="<?= $activeAgentsCount ?>" aria-live="polite" aria-atomic="true">0</h3>
                    <p class="neuro-label mb-0">Agentes Activos</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
