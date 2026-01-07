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

<div class="row g-3 mb-4">
    <!-- Total -->
    <div class="col-md-3 col-sm-6">
        <div class="modern-card accent-green kpi-card" data-animate="fade-up" data-delay="0">
            <div class="kpi-icon-wrapper">
                <i class="bi <?= $icon ?> kpi-icon text-green"></i>
            </div>
            <h3 class="kpi-number" data-counter data-target="<?= $total ?>" aria-live="polite" aria-atomic="true">0</h3>
            <p class="kpi-label mb-0">Total <?= h($label) ?></p>
        </div>
    </div>

    <!-- Recent (7 days) -->
    <div class="col-md-3 col-sm-6">
        <div class="modern-card accent-orange kpi-card" data-animate="fade-up" data-delay="100">
            <div class="kpi-icon-wrapper">
                <i class="bi bi-clock-history kpi-icon text-orange"></i>
            </div>
            <h3 class="kpi-number" data-counter data-target="<?= $recentCount ?>" aria-live="polite" aria-atomic="true">0</h3>
            <p class="kpi-label mb-0">Últimos 7 días</p>
        </div>
    </div>

    <!-- Unassigned -->
    <div class="col-md-3 col-sm-6">
        <div class="modern-card accent-gradient kpi-card" data-animate="fade-up" data-delay="200">
            <div class="kpi-icon-wrapper">
                <i class="bi bi-person-x-fill kpi-icon text-red"></i>
            </div>
            <h3 class="kpi-number" data-counter data-target="<?= $unassignedCount ?>" aria-live="polite" aria-atomic="true">0</h3>
            <p class="kpi-label mb-0">Sin Asignar</p>
        </div>
    </div>

    <!-- Active Agents OR SLA Compliance (for Compras) -->
    <div class="col-md-3 col-sm-6">
        <div class="modern-card kpi-card" data-animate="fade-up" data-delay="300">
            <?php if ($entityType === 'compra' && isset($slaMetrics)): ?>
                <div class="kpi-icon-wrapper">
                    <i class="bi bi-speedometer2 kpi-icon text-green"></i>
                </div>
                <h3 class="kpi-number" data-counter data-target="<?= (int)$slaMetrics['compliance_rate'] ?>" aria-live="polite" aria-atomic="true">0</h3>
                <p class="kpi-label mb-0">Cumplimiento SLA %</p>
            <?php else: ?>
                <div class="kpi-icon-wrapper">
                    <i class="bi bi-people kpi-icon text-blue"></i>
                </div>
                <h3 class="kpi-number" data-counter data-target="<?= $activeAgentsCount ?>" aria-live="polite" aria-atomic="true">0</h3>
                <p class="kpi-label mb-0">Agentes Activos</p>
            <?php endif; ?>
        </div>
    </div>
</div>
