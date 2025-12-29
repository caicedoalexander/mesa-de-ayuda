<?php
/**
 * Status Distribution Doughnut Chart
 *
 * @var array $statusDistribution Status => count mapping
 * @var string $entityType 'ticket', 'pqrs', or 'compra'
 */

// Define status labels and colors per entity type
$statusConfig = [
    'ticket' => [
        'labels' => ['Nuevo', 'Abierto', 'Pendiente', 'Resuelto', 'Convertido'],
        'keys' => ['nuevo', 'abierto', 'pendiente', 'resuelto', 'convertido'],
        'colors' => ['#ffc107', '#dc3545', '#0d6efd', '#198754', '#6c757d'],
    ],
    'pqrs' => [
        'labels' => ['Nuevo', 'En Revisión', 'En Proceso', 'Resuelto', 'Cerrado'],
        'keys' => ['nuevo', 'en_revision', 'en_proceso', 'resuelto', 'cerrado'],
        'colors' => ['#ffc107', '#0dcaf0', '#0d6efd', '#198754', '#6c757d'],
    ],
    'compra' => [
        'labels' => ['Nuevo', 'En Revisión', 'Aprobado', 'En Proceso', 'Completado', 'Rechazado', 'Convertido'],
        'keys' => ['nuevo', 'en_revision', 'aprobado', 'en_proceso', 'completado', 'rechazado', 'convertido'],
        'colors' => ['#ffc107', '#0dcaf0', '#198754', '#0d6efd', '#28a745', '#dc3545', '#6c757d'],
    ],
];

$config = $statusConfig[$entityType];
$chartId = 'statusChart' . uniqid();
?>

<div class="modern-card chart-card h-100" data-animate="fade-up" data-delay="400">
    <div class="chart-header">
        <h5 class="chart-title">
            Por Estado
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
    const statusData = <?= json_encode($statusDistribution) ?>;
    const statusLabels = <?= json_encode($config['labels']) ?>;
    const statusKeys = <?= json_encode($config['keys']) ?>;
    const statusValues = statusKeys.map(key => statusData[key] || 0);
    const statusColors = <?= json_encode($config['colors']) ?>;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusValues,
                backgroundColor: statusColors,
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
