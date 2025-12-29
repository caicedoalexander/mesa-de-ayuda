<?php
/**
 * SLA Metrics Display (Compras only)
 *
 * @var array $slaMetrics SLA metrics data
 */
?>

<div class="row mb-5">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-alarm"></i> Métricas SLA (3 días)
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded bg-light">
                            <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0 text-danger"><?= number_format($slaMetrics['breached_count']) ?></h4>
                            <p class="text-muted mb-0 small">SLA Vencido</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded bg-light">
                            <i class="bi bi-hourglass-split text-warning" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0 text-warning"><?= number_format($slaMetrics['at_risk_count']) ?></h4>
                            <p class="text-muted mb-0 small">En Riesgo (< 24h)</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded bg-light">
                            <i class="bi bi-clock text-info" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0 text-info"><?= number_format($slaMetrics['active_count']) ?></h4>
                            <p class="text-muted mb-0 small">SLA Activos</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded bg-light">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0 text-success"><?= h($slaMetrics['compliance_rate']) ?>%</h4>
                            <p class="text-muted mb-0 small">Tasa de Cumplimiento</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
