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

<<<<<<< HEAD
<div class="modern-card chart-card h-100" data-animate="fade-up" data-delay="600">
    <div class="chart-header">
        <h5 class="chart-title">
            <i class="bi bi-tags-fill"></i>
            Por Tipo
        </h5>
=======
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-list-task"></i> Por Tipo</h5>
>>>>>>> c0d0b3845e543ad02c0c92544fb1b1ded4046e06
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
<<<<<<< HEAD

<script>
(function() {
    const ctx = document.getElementById('<?= $chartId ?>').getContext('2d');

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                data: <?= json_encode($data) ?>,
                backgroundColor: <?= json_encode($colors) ?>,
                borderWidth: 3,
                borderColor: '#fff',
                hoverBorderWidth: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'left',
                    labels: {
                        padding: 8,
                        font: {
                            size: 14,
                            family: "'Plus Jakarta Sans', sans-serif",
                            weight: '400'
                        },
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
})();
</script>
=======
>>>>>>> c0d0b3845e543ad02c0c92544fb1b1ded4046e06
