<?php
/**
 * Trend Line Chart - Daily entity creation
 *
 * @var array $chartLabels Date labels
 * @var array $chartData Count values
 * @var string $entityType 'ticket', 'pqrs', or 'compra'
 */

$entityLabels = [
    'ticket' => 'Tickets',
    'pqrs' => 'PQRS',
    'compra' => 'Compras',
];
$label = $entityLabels[$entityType] ?? 'Entidades';

$chartId = 'trendChart' . uniqid();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="modern-card chart-card" data-animate="fade-up" data-delay="600">
            <div class="chart-header">
                <h5 class="chart-title">
                    <i class="bi bi-graph-up"></i>
                    Tendencia (30 días)
                </h5>
            </div>
            <div class="chart-wrapper" data-chart-loader style="min-height: 300px;">
                <div class="chart-skeleton">
                    <div class="skeleton-spinner"></div>
                </div>
                <canvas id="<?= $chartId ?>" height="80" style="opacity: 0;"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const ctx = document.getElementById('<?= $chartId ?>').getContext('2d');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: '<?= h($label) ?> Creados',
                data: <?= json_encode($chartData) ?>,
                borderColor: '#00A85E',
                backgroundColor: 'rgba(0, 168, 94, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.5,
                pointRadius: 5,
                pointHoverRadius: 5,
                pointBackgroundColor: '#00A85E',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
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
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        precision: 0
                    }
                }
            }
        }
    });
})();
</script>
