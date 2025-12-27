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

<div class="row mb-5">
    <div class="col-12">
        <div class="neuro-card neuro-chart-container" data-animate-in="fade-up" data-delay="600">
            <div class="neuro-chart-header">
                <h5 class="neuro-chart-title">
                    <i class="bi bi-graph-up me-2" style="color: var(--neuro-warning);"></i>
                    Tendencia de <?= h($label) ?> (Últimos 30 días)
                </h5>
            </div>
            <div class="neuro-chart-wrapper" data-chart-loader style="min-height: 300px;">
                <div class="neuro-chart-skeleton">
                    <div class="skeleton-circle"></div>
                </div>
                <canvas id="<?= $chartId ?>" height="80" style="opacity: 0; transition: opacity 0.5s ease;"></canvas>
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
                borderColor: '#fd7e14',
                backgroundColor: 'rgba(253, 126, 20, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
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
