<?php
/**
 * PQRS Statistics Template
 *
 * @var \App\View\AppView $this
 * @var int $total
 * @var int $recentCount
 * @var int $unassignedCount
 * @var int $activeAgentsCount
 * @var int $totalResolved
 * @var int $totalPending
 * @var int $resolvedInPeriod
 * @var array $statusDistribution
 * @var array $priorityDistribution
 * @var array $typeDistribution
 * @var array $channelDistribution
 * @var array $chartLabels
 * @var array $chartData
 * @var float $avgResolutionDays
 * @var float $avgResolutionHours
 * @var array $topAgents
 * @var array $filters
 * @var string|null $dateFrom
 * @var string|null $dateTo
 */

$this->assign('title', 'Estadísticas de PQRS');
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
        <h1 class="stats-title">Estadísticas PQRS</h1>
        <p class="stats-subtitle">Peticiones, quejas, reclamos y sugerencias</p>
    </div>

    <!-- Date Range Filter (commented out for now) -->
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
        'entityType' => 'pqrs',
        'slaMetrics' => null
    ]) ?>

    <!-- Secondary KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="modern-card accent-green kpi-card" data-animate="fade-up" data-delay="400">
                <div class="kpi-icon-wrapper">
                    <i class="bi bi-check-circle kpi-icon text-green"></i>
                </div>
                <h3 class="kpi-number" data-counter data-target="<?= $totalResolved ?>" aria-live="polite">0</h3>
                <p class="kpi-label mb-0">Total Resueltos</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="modern-card accent-orange kpi-card" data-animate="fade-up" data-delay="500">
                <div class="kpi-icon-wrapper">
                    <i class="bi bi-hourglass-split kpi-icon text-orange"></i>
                </div>
                <h3 class="kpi-number" data-counter data-target="<?= $totalPending ?>" aria-live="polite">0</h3>
                <p class="kpi-label mb-0">Pendientes</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="modern-card accent-gradient kpi-card" data-animate="fade-up" data-delay="600">
                <div class="kpi-icon-wrapper">
                    <i class="bi bi-clock-history kpi-icon text-blue"></i>
                </div>
                <h3 class="kpi-number mb-2"><?= $avgResolutionDays ?> días</h3>
                <p class="kpi-label mb-1">Tiempo Prom. Resolución</p>
                <small style="color: var(--gray-500); font-size: 0.75rem;">(<?= $avgResolutionHours ?> horas)</small>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
        <!-- Status Chart -->
        <div class="col-md-4">
            <?= $this->element('shared/statistics/status_chart', [
                'statusDistribution' => $statusDistribution,
                'entityType' => 'pqrs'
            ]) ?>
        </div>

        <!-- Priority Chart -->
        <div class="col-md-4">
            <?= $this->element('shared/statistics/priority_chart', [
                'priorityDistribution' => $priorityDistribution,
                'entityType' => 'pqrs'
            ]) ?>
        </div>

        <!-- Type Distribution (PQRS-specific) -->
        <div class="col-md-4">
            <?= $this->element('shared/statistics/type_distribution', [
                'typeDistribution' => $typeDistribution
            ]) ?>
        </div>
    </div>

    <!-- Trend Chart -->
    <?= $this->element('shared/statistics/trend_chart', [
        'chartLabels' => $chartLabels,
        'chartData' => $chartData,
        'entityType' => 'pqrs'
    ]) ?>

    <!-- Tables Row -->
    <div class="row">
        <!-- Top Agents -->
        <div class="col-md-6">
            <?= $this->element('shared/statistics/agent_performance_table', [
                'topAgents' => $topAgents,
                'entityType' => 'pqrs'
            ]) ?>
        </div>

        <!-- Channel Distribution -->
        <div class="col-md-6">
            <?= $this->element('shared/statistics/channel_distribution', [
                'channelDistribution' => $channelDistribution
            ]) ?>
        </div>
    </div>
</div>
