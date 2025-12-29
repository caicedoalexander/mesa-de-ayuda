<?php
/**
 * Tickets Statistics Template
 *
 * @var \App\View\AppView $this
 * @var int $total
 * @var int $recentCount
 * @var int $unassignedCount
 * @var int $activeAgentsCount
 * @var array $statusDistribution
 * @var array $priorityDistribution
 * @var array $chartLabels
 * @var array $chartData
 * @var object|null $avgResponseTime
 * @var object|null $avgResolutionTime
 * @var float $responseRate
 * @var float $resolutionRate
 * @var \Cake\ORM\ResultSet $topAgents
 * @var \Cake\ORM\ResultSet $topRequesters
 * @var int $totalComments
 * @var int $publicComments
 * @var int $internalComments
 * @var array $filters
 */

$this->assign('title', 'Estadísticas de Tickets');
?>

<!-- Include Modern Statistics CSS -->
<?= $this->Html->css('modern-statistics') ?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

<!-- Include Modern Statistics JavaScript -->
<?= $this->Html->script('modern-statistics') ?>

<div class="statistics-container">
    <!-- Header -->
    <div class="mb-4">
        <h1 class="stats-title">Estadísticas</h1>
        <p class="stats-subtitle">Sistema de tickets</p>
    </div>

    <!-- Date Range Filter (commented out for now - can be enabled later) -->
    <!-- <?= $this->element('shared/statistics/date_range_filter', [
        'filters' => $filters,
        'action' => 'statistics'
    ]) ?> -->

    <!-- KPI Cards -->
    <?= $this->element('shared/statistics/kpi_cards', [
        'total' => $total,
        'recentCount' => $recentCount,
        'unassignedCount' => $unassignedCount,
        'activeAgentsCount' => $activeAgentsCount,
        'entityType' => 'ticket',
        'slaMetrics' => null
    ]) ?>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
        <!-- Status Chart -->
        <div class="col-md-4">
            <?= $this->element('shared/statistics/status_chart', [
                'statusDistribution' => $statusDistribution,
                'entityType' => 'ticket'
            ]) ?>
        </div>

        <!-- Priority Chart -->
        <div class="col-md-4">
            <?= $this->element('shared/statistics/priority_chart', [
                'priorityDistribution' => $priorityDistribution,
                'entityType' => 'ticket'
            ]) ?>
        </div>

        <!-- Response Metrics (Tickets-specific) -->
        <div class="col-md-4">
            <?= $this->element('Tickets/response_metrics', [
                'responseRate' => $responseRate,
                'resolutionRate' => $resolutionRate,
                'avgResponseTime' => $avgResponseTime,
                'avgResolutionTime' => $avgResolutionTime
            ]) ?>
        </div>
    </div>

    <!-- Trend Chart -->
    <?= $this->element('shared/statistics/trend_chart', [
        'chartLabels' => $chartLabels,
        'chartData' => $chartData,
        'entityType' => 'ticket'
    ]) ?>

    <!-- Tables Row -->
    <div class="row g-3 mb-4">
        <!-- Top Agents -->
        <div class="col-md-6">
            <?= $this->element('shared/statistics/agent_performance_table', [
                'topAgents' => $topAgents,
                'entityType' => 'ticket'
            ]) ?>
        </div>

        <!-- Top Requesters (Tickets-specific) -->
        <div class="col-md-6">
            <?= $this->element('Tickets/requester_stats', [
                'topRequesters' => $topRequesters
            ]) ?>
        </div>
    </div>

    <!-- Comments Statistics -->
    <div class="row g-4">
        <div class="col-md-4">
            <div class="modern-card accent-green kpi-card" data-animate="fade-up" data-delay="400">
                <div class="kpi-icon-wrapper">
                    <i class="bi bi-chat-dots kpi-icon text-blue"></i>
                </div>
                <h3 class="kpi-number" data-counter data-target="<?= $totalComments ?>" aria-live="polite" aria-atomic="true">0</h3>
                <p class="kpi-label mb-0">Total Comentarios</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="modern-card accent-orange kpi-card" data-animate="fade-up" data-delay="500">
                <div class="kpi-icon-wrapper">
                    <i class="bi bi-eye kpi-icon text-green"></i>
                </div>
                <h3 class="kpi-number" data-counter data-target="<?= $publicComments ?>" aria-live="polite" aria-atomic="true">0</h3>
                <p class="kpi-label mb-0">Comentarios Públicos</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="modern-card accent-gradient kpi-card" data-animate="fade-up" data-delay="600">
                <div class="kpi-icon-wrapper">
                    <i class="bi bi-eye-slash kpi-icon text-orange"></i>
                </div>
                <h3 class="kpi-number" data-counter data-target="<?= $internalComments ?>" aria-live="polite" aria-atomic="true">0</h3>
                <p class="kpi-label mb-0">Comentarios Internos</p>
            </div>
        </div>
    </div>
</div>
