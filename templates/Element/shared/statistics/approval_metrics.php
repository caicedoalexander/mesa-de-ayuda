<?php
/**
 * Approval Metrics Display (Compras only)
 *
 * @var array $approvalMetrics Approval metrics data
 */
?>

<div class="row g-3 mb-4">
    <div class="col-12">
        <h3 style="font-size: 0.875rem; font-weight: 600; color: var(--gray-700); margin-bottom: 0.75rem;">
            Métricas de Aprobación
        </h3>
    </div>

    <div class="col-md-4">
        <div class="modern-card kpi-card accent-green" data-animate="fade-up" data-delay="800">
            <div class="kpi-icon-wrapper">
                <i class="bi bi-check-circle-fill kpi-icon text-green"></i>
            </div>
            <h3 class="kpi-number" data-counter data-target="<?= $approvalMetrics['approved_count'] ?>" aria-live="polite">0</h3>
            <p class="kpi-label mb-0">Aprobadas</p>
        </div>
    </div>

    <div class="col-md-4">
        <div class="modern-card kpi-card" style="border-left: 4px solid var(--danger);" data-animate="fade-up" data-delay="900">
            <div class="kpi-icon-wrapper" style="background: rgba(239, 68, 68, 0.1);">
                <i class="bi bi-x-circle-fill kpi-icon text-red"></i>
            </div>
            <h3 class="kpi-number" data-counter data-target="<?= $approvalMetrics['rejected_count'] ?>" aria-live="polite">0</h3>
            <p class="kpi-label mb-0">Rechazadas</p>
        </div>
    </div>

    <div class="col-md-4">
        <div class="modern-card kpi-card accent-gradient" data-animate="scale" data-delay="1000">
            <div class="kpi-icon-wrapper">
                <i class="bi bi-percent kpi-icon text-blue"></i>
            </div>
            <h3 class="kpi-number" data-counter data-target="<?= (int)$approvalMetrics['approval_rate'] ?>" aria-live="polite">0</h3>
            <p class="kpi-label mb-0">Tasa de Aprobación %</p>
        </div>
    </div>
</div>
