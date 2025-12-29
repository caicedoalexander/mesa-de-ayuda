<?php
/**
 * Compras Statistics Template
 *
 * @var \App\View\AppView $this
 * @var int $total
 * @var int $recentCount
 * @var int $unassignedCount
 * @var int $activeAgentsCount
 * @var array $statusDistribution
 * @var array $priorityDistribution
 * @var array $channelDistribution
 * @var array $chartLabels
 * @var array $chartData
 * @var float $avgResolutionDays
 * @var float $avgResolutionHours
 * @var array $topAgents
 * @var array $slaMetrics
 * @var array $approvalMetrics
 * @var array $filters
 * @var string|null $dateFrom
 * @var string|null $dateTo
 */

$this->assign('title', 'Estadísticas de Compras');
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
        <h1 class="stats-title">Estadísticas de Compras</h1>
        <p class="stats-subtitle">Gestión de compras y procurement</p>
    </div>

    <!-- Date Range Filter (commented out for now) -->
    <!-- <?= $this->element('shared/statistics/date_range_filter', [
        'filters' => $filters,
        'action' => 'statistics'
    ]) ?> -->

    <!-- KPI Cards (includes SLA compliance in 4th card) -->
    <?= $this->element('shared/statistics/kpi_cards', [
        'total' => $total,
        'recentCount' => $recentCount,
        'unassignedCount' => $unassignedCount,
        'activeAgentsCount' => $activeAgentsCount,
        'entityType' => 'compra',
        'slaMetrics' => $slaMetrics
    ]) ?>

    <!-- SLA Metrics - PROMINENT DISPLAY (per user request) -->
    <?= $this->element('shared/statistics/sla_metrics', [
        'slaMetrics' => $slaMetrics
    ]) ?>

    <!-- Approval Metrics -->
    <?= $this->element('shared/statistics/approval_metrics', [
        'approvalMetrics' => $approvalMetrics
    ]) ?>

    <!-- Performance Metric -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="modern-card accent-gradient kpi-card" data-animate="fade-up" data-delay="700">
                <div class="kpi-icon-wrapper">
                    <i class="bi bi-speedometer kpi-icon text-blue"></i>
                </div>
                <h3 class="kpi-number mb-2"><?= $avgResolutionDays ?> días</h3>
                <p class="kpi-label mb-1">Tiempo Promedio de Resolución</p>
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
                'entityType' => 'compra'
            ]) ?>
        </div>

        <!-- Priority Chart -->
        <div class="col-md-4">
            <?= $this->element('shared/statistics/priority_chart', [
                'priorityDistribution' => $priorityDistribution,
                'entityType' => 'compra'
            ]) ?>
        </div>

        <!-- Channel Distribution -->
        <div class="col-md-4">
            <?= $this->element('shared/statistics/channel_distribution', [
                'channelDistribution' => $channelDistribution
            ]) ?>
        </div>
    </div>

    <!-- Trend Chart -->
    <?= $this->element('shared/statistics/trend_chart', [
        'chartLabels' => $chartLabels,
        'chartData' => $chartData,
        'entityType' => 'compra'
    ]) ?>

    <!-- Agent Performance Table -->
    <div class="row">
        <div class="col-md-12">
            <?= $this->element('shared/statistics/agent_performance_table', [
                'topAgents' => $topAgents,
                'entityType' => 'compra'
            ]) ?>
        </div>
    </div>
</div>
