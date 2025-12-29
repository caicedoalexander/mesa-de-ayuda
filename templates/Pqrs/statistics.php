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

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

<div class="statistics-container">
    <!-- Header -->
    <div class="mb-5">
        <h2 class="fw-normal neuro-text-primary"><i class="bi bi-bar-chart me-2" style="color: var(--neuro-success);"></i>Estadísticas PQRS</h2>
        <p class="neuro-text-secondary fw-light">Vista general del sistema de peticiones, quejas, reclamos y sugerencias</p>
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
    <div class="row mb-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-check-circle text-success" style="font-size: 2.5rem;"></i>
                    <h3 class="mt-2 mb-0"><?= number_format($totalResolved) ?></h3>
                    <p class="text-muted mb-0 fw-light">Total Resueltos</p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-hourglass-split text-warning" style="font-size: 2.5rem;"></i>
                    <h3 class="mt-2 mb-0"><?= number_format($totalPending) ?></h3>
                    <p class="text-muted mb-0 fw-light">Pendientes</p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-clock-history text-info" style="font-size: 2.5rem;"></i>
                    <h3 class="mt-2 mb-0"><?= $avgResolutionDays ?> días</h3>
                    <p class="text-muted mb-0 fw-light">Tiempo Prom. Resolución</p>
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
