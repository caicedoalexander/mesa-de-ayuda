<?php
/**
 * PQRS SLA Metrics Display
 *
 * @var array $pqrsSlaMetrics SLA metrics data
 */
?>

<div class="row g-3 mb-4">
    <div class="col-12">
        <h3 style="font-size: 0.875rem; font-weight: 600; color: var(--gray-700); margin-bottom: 0.75rem;">
            Cumplimiento SLA
        </h3>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="modern-card kpi-card" style="border-left: 4px solid var(--danger);" data-animate="fade-up" data-delay="400">
            <div class="kpi-icon-wrapper" style="background: rgba(239, 68, 68, 0.1);">
                <i class="bi bi-reply-fill kpi-icon text-red"></i>
            </div>
            <h3 class="kpi-number" data-counter data-target="<?= $pqrsSlaMetrics['response_breached'] ?>" aria-live="polite">0</h3>
            <p class="kpi-label mb-0">Respuesta Vencida</p>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="modern-card kpi-card" style="border-left: 4px solid var(--brand-orange);" data-animate="fade-up" data-delay="500">
            <div class="kpi-icon-wrapper" style="background: rgba(205, 106, 21, 0.1);">
                <i class="bi bi-exclamation-triangle-fill kpi-icon text-orange"></i>
            </div>
            <h3 class="kpi-number" data-counter data-target="<?= $pqrsSlaMetrics['resolution_breached'] ?>" aria-live="polite">0</h3>
            <p class="kpi-label mb-0">Resolución Vencida</p>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="modern-card kpi-card accent-green" data-animate="fade-up" data-delay="600">
            <div class="kpi-icon-wrapper">
                <i class="bi bi-check-circle-fill kpi-icon text-green"></i>
            </div>
            <h3 class="kpi-number" data-counter data-target="<?= (int)$pqrsSlaMetrics['response_compliance_rate'] ?>" aria-live="polite">0</h3>
            <p class="kpi-label mb-0">Cumpl. Respuesta %</p>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="modern-card kpi-card accent-green" data-animate="fade-up" data-delay="700">
            <div class="kpi-icon-wrapper">
                <i class="bi bi-check2-circle kpi-icon text-green"></i>
            </div>
            <h3 class="kpi-number" data-counter data-target="<?= (int)$pqrsSlaMetrics['resolution_compliance_rate'] ?>" aria-live="polite">0</h3>
            <p class="kpi-label mb-0">Cumpl. Resolución %</p>
        </div>
    </div>
</div>
