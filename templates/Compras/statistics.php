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
 * @var array $topRequesters
 * @var array $slaMetrics
 * @var array $approvalMetrics
 * @var array $filters
 * @var string|null $dateFrom
 * @var string|null $dateTo
 */

$this->assign('title', 'Estadísticas de Compras');
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

<div class="statistics-container">
    <!-- Header -->
<<<<<<< HEAD
    <div class="mb-4">
        <h1 class="stats-title"><i class="bi bi-bar-chart-fill"></i> Estadísticas</h1>
        <p class="stats-subtitle">Gestión de compras</p>
=======
    <div class="mb-5">
        <h2 class="fw-normal neuro-text-primary"><i class="bi bi-bar-chart me-2" style="color: var(--neuro-success);"></i>Estadísticas de Compras</h2>
        <p class="neuro-text-secondary fw-light">Vista general del sistema de gestión de compras</p>
>>>>>>> c0d0b3845e543ad02c0c92544fb1b1ded4046e06
    </div>

    <!-- Date Range Filter -->
    <?= $this->element('shared/statistics/date_range_filter', [
        'filters' => $filters,
        'action' => 'statistics'
    ]) ?>

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
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-speedometer text-primary" style="font-size: 2.5rem;"></i>
                    <h3 class="mt-2 mb-0"><?= $avgResolutionDays ?> días</h3>
                    <p class="text-muted mb-0 fw-light">Tiempo Promedio de Resolución</p>
                    <small class="text-muted">(<?= $avgResolutionHours ?> horas)</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-5">
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

    <!-- Performance Tables Row -->
    <div class="row g-3 mb-4 pb-4">
        <!-- Top Agents -->
        <div class="col-md-6">
            <?= $this->element('shared/statistics/agent_performance_table', [
                'topAgents' => $topAgents,
                'entityType' => 'compra'
            ]) ?>
        </div>

        <!-- Top Requesters -->
        <div class="col-md-6">
            <?= $this->element('Tickets/requester_stats', [
                'topRequesters' => $topRequesters
            ]) ?>
        </div>
    </div>
</div>
