<?php
/**
 * Response and Resolution Metrics (Tickets-specific)
 *
 * @var float $responseRate Response rate percentage
 * @var float $resolutionRate Resolution rate percentage
 * @var object|null $avgResponseTime Average response time
 * @var object|null $avgResolutionTime Average resolution time
 */
?>

<<<<<<< HEAD
<div class="modern-card chart-card h-100" data-animate="fade-up" data-delay="600">
    <div class="chart-header">
        <h5 class="chart-title">
            <i class="bi bi-speedometer"></i>
            Rendimiento
        </h5>
=======
<div class="card border-0 shadow-sm h-100">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-speedometer"></i> Métricas de Rendimiento</h5>
>>>>>>> c0d0b3845e543ad02c0c92544fb1b1ded4046e06
    </div>
    <div class="card-body">
        <!-- Response Rate -->
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-semibold">Tasa de Respuesta</span>
                <span class="text-muted small"><?= $responseRate ?>%</span>
            </div>
            <div class="progress" style="height: 10px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $responseRate ?>%"></div>
            </div>
        </div>

        <!-- Resolution Rate -->
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-semibold">Tasa de Resolución</span>
                <span class="text-muted small"><?= $resolutionRate ?>%</span>
            </div>
            <div class="progress" style="height: 10px;">
                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $resolutionRate ?>%"></div>
            </div>
        </div>

        <!-- Avg Response Time -->
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small">Tiempo Prom. Respuesta</span>
                <span class="fw-semibold">
                    <?php if ($avgResponseTime && $avgResponseTime->avg_hours): ?>
                        <?= round($avgResponseTime->avg_hours, 1) ?>h
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- Avg Resolution Time -->
        <div>
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small">Tiempo Prom. Resolución</span>
                <span class="fw-semibold">
                    <?php if ($avgResolutionTime && $avgResolutionTime->avg_hours): ?>
                        <?= round($avgResolutionTime->avg_hours, 1) ?>h
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
</div>
