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

$typeChartColors = [
    'peticion' => '#3B82F6',
    'queja' => '#F59E0B',
    'reclamo' => '#EF4444',
    'sugerencia' => '#00A85E',
];

$chartId = 'typeChart' . uniqid();

$labels = [];
$data = [];
$colors = [];

foreach ($typeDistribution as $type => $count) {
    $labels[] = $typeLabels[$type] ?? ucfirst($type);
    $data[] = $count;
    $colors[] = $typeChartColors[$type] ?? '#6B7280';
}
?>

<div class="modern-card chart-card h-100" data-animate="fade-up" data-delay="600">
    <div class="chart-header">
        <h5 class="chart-title">
            Por Tipo
        </h5>
    </div>
    <div class="chart-wrapper" data-chart-loader>
        <div class="chart-skeleton">
            <div class="skeleton-spinner"></div>
        </div>
        <canvas id="<?= $chartId ?>" height="250" style="opacity: 0;"></canvas>
    </div>
</div>

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
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12,
                            family: "'Plus Jakarta Sans', sans-serif",
                            weight: '600'
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
