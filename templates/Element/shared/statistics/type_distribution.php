<?php
/**
 * Type Distribution Display (PQRS only)
 *
 * @var array $typeDistribution Type => count mapping
 */

$typeLabels = [
    'peticion' => 'Petición',
    'queja' => 'Queja',
    'reclamo' => 'Reclamo',
    'sugerencia' => 'Sugerencia',
];

$typeColors = [
    'peticion' => 'bg-info',
    'queja' => 'bg-warning',
    'reclamo' => 'bg-danger',
    'sugerencia' => 'bg-success',
];

$total = array_sum($typeDistribution);
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-list-task"></i> Por Tipo</h5>
    </div>
    <div class="card-body">
        <?php foreach ($typeDistribution as $type => $count): ?>
            <?php
            $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
            $label = $typeLabels[$type] ?? ucfirst($type);
            $colorClass = $typeColors[$type] ?? 'bg-secondary';
            ?>
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-semibold"><?= h($label) ?></span>
                    <span class="text-muted small"><?= number_format($count) ?> (<?= $percentage ?>%)</span>
                </div>
                <div class="progress" style="height: 20px;">
                    <div class="progress-bar <?= $colorClass ?>"
                         role="progressbar"
                         style="width: <?= $percentage ?>%"
                         aria-valuenow="<?= $percentage ?>"
                         aria-valuemin="0"
                         aria-valuemax="100">
                        <?php if ($percentage > 10): ?><?= $percentage ?>%<?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
