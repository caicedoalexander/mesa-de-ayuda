<?php
/**
 * Priority Distribution Doughnut Chart
 *
 * @var array $priorityDistribution Priority => count mapping
 * @var string $entityType 'ticket', 'pqrs', or 'compra'
 */

$priorityLabels = ['Baja', 'Media', 'Alta', 'Urgente'];
$priorityKeys = ['baja', 'media', 'alta', 'urgente'];
$priorityColors = ['#6c757d', '#0dcaf0', '#ffc107', '#dc3545'];

$chartId = 'priorityChart' . uniqid();
?>

<div class="neuro-card neuro-chart-container h-100" data-animate-in="fade-up" data-delay="500">
    <div class="neuro-chart-header">
        <h5 class="neuro-chart-title">
            <i class="bi bi-exclamation-triangle me-2" style="color: var(--neuro-warning);"></i>
            Por Prioridad
        </h5>
    </div>
    <div class="neuro-chart-wrapper" data-chart-loader>
        <div class="neuro-chart-skeleton">
            <div class="skeleton-circle"></div>
        </div>
        <canvas id="<?= $chartId ?>" height="250" style="opacity: 0; transition: opacity 0.5s ease;"></canvas>
    </div>
</div>

<script>
(function() {
    const ctx = document.getElementById('<?= $chartId ?>').getContext('2d');
    const priorityData = <?= json_encode($priorityDistribution) ?>;
    const priorityLabels = <?= json_encode($priorityLabels) ?>;
    const priorityKeys = <?= json_encode($priorityKeys) ?>;
    const priorityValues = priorityKeys.map(key => priorityData[key] || 0);
    const priorityColors = <?= json_encode($priorityColors) ?>;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: priorityLabels,
            datasets: [{
                data: priorityValues,
                backgroundColor: priorityColors,
                borderWidth: 3,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
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
