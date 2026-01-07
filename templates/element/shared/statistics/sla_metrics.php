<?php
/**
 * SLA Metrics Display (Compras only)
 *
 * @var array $slaMetrics SLA metrics data
 */
?>

<div class="row g-3 mb-4">
    <div class="col-12">
        <h3 style="font-size: 0.875rem; font-weight: 600; color: var(--gray-700); margin-bottom: 0.75rem;">
            Métricas SLA (3 días)
        </h3>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="modern-card kpi-card" style="border-left: 4px solid var(--danger);" data-animate="fade-up" data-delay="400">
            <div class="kpi-icon-wrapper" style="background: rgba(239, 68, 68, 0.1);">
                <i class="bi bi-exclamation-triangle-fill kpi-icon text-red"></i>
            </div>
            <h3 class="kpi-number" data-counter data-target="<?= $slaMetrics['breached_count'] ?>" aria-live="polite">0</h3>
            <p class="kpi-label mb-0">SLA Vencido</p>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="modern-card kpi-card" style="border-left: 4px solid var(--brand-orange);" data-animate="fade-up" data-delay="500">
            <div class="kpi-icon-wrapper" style="background: rgba(205, 106, 21, 0.1);">
                <i class="bi bi-hourglass-split kpi-icon text-orange"></i>
            </div>
            <h3 class="kpi-number" data-counter data-target="<?= $slaMetrics['at_risk_count'] ?>" aria-live="polite">0</h3>
            <p class="kpi-label mb-0">En Riesgo (< 24h)</p>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="modern-card kpi-card" style="border-left: 4px solid var(--info);" data-animate="fade-up" data-delay="600">
            <div class="kpi-icon-wrapper" style="background: rgba(59, 130, 246, 0.1);">
                <i class="bi bi-clock kpi-icon text-blue"></i>
            </div>
            <h3 class="kpi-number" data-counter data-target="<?= $slaMetrics['active_count'] ?>" aria-live="polite">0</h3>
            <p class="kpi-label mb-0">SLA Activos</p>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="modern-card kpi-card accent-green" data-animate="fade-up" data-delay="700">
            <div class="kpi-icon-wrapper">
                <i class="bi bi-check-circle-fill kpi-icon text-green"></i>
            </div>
            <h3 class="kpi-number" data-counter data-target="<?= (int)$slaMetrics['compliance_rate'] ?>" aria-live="polite">0</h3>
            <p class="kpi-label mb-0">Tasa de Cumplimiento %</p>
        </div>
    </div>
</div>
