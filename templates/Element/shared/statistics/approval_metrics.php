<?php
/**
 * Approval Metrics Display (Compras only)
 *
 * @var array $approvalMetrics Approval metrics data
 */
?>

<div class="row mb-5">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-check2-square"></i> Métricas de Aprobación
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center p-3 border rounded bg-light">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0 text-success"><?= number_format($approvalMetrics['approved_count']) ?></h4>
                            <p class="text-muted mb-0 small">Aprobadas</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center p-3 border rounded bg-light">
                            <i class="bi bi-x-circle-fill text-danger" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0 text-danger"><?= number_format($approvalMetrics['rejected_count']) ?></h4>
                            <p class="text-muted mb-0 small">Rechazadas</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center p-3 border rounded bg-light">
                            <i class="bi bi-percent text-primary" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0 text-primary"><?= h($approvalMetrics['approval_rate']) ?>%</h4>
                            <p class="text-muted mb-0 small">Tasa de Aprobación</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
