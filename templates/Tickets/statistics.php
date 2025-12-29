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

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

<div class="statistics-container">
    <!-- Header -->
<<<<<<< HEAD
    <div class="mb-4">
        <h1 class="stats-title"><i class="bi bi-bar-chart-fill"></i> Estadísticas</h1>
=======
    <div class="mb-5">
        <h2 class="fw-normal neuro-text-primary"><i class="bi bi-bar-chart me-2" style="color: var(--neuro-success);"></i>Estadísticas</h2>
        <p class="neuro-text-secondary fw-light">Vista general del sistema de tickets</p>
>>>>>>> c0d0b3845e543ad02c0c92544fb1b1ded4046e06
    </div>

    <!-- Date Range Filter -->
    <?= $this->element('shared/statistics/date_range_filter', [
        'filters' => $filters,
        'action' => 'statistics'
    ]) ?>

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
    <div class="row mb-5">
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
    <div class="row mb-5">
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

    <!-- Channel Distribution -->
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <?= $this->element('shared/statistics/channel_distribution', [
                'channelDistribution' => $channelDistribution
            ]) ?>
        </div>
    </div>

    <!-- Comments Statistics -->
<<<<<<< HEAD
    <div class="row g-3 mb-4 pb-4">
=======
    <div class="row">
>>>>>>> c0d0b3845e543ad02c0c92544fb1b1ded4046e06
        <div class="col-md-4">
            <div class="card neuro-card neuro-hover" data-animate-in="fade-up" data-delay="700">
                <div class="card-body text-center py-4">
                    <div class="neuro-icon-wrapper mb-3">
                        <i class="bi bi-chat-dots neuro-icon" style="color: var(--neuro-primary);"></i>
                    </div>
                    <h3 class="neuro-counter mb-2" data-counter data-target="<?= $totalComments ?>" aria-live="polite" aria-atomic="true">0</h3>
                    <p class="neuro-label mb-0">Total Comentarios</p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card neuro-card neuro-hover" data-animate-in="fade-up" data-delay="800">
                <div class="card-body text-center py-4">
                    <div class="neuro-icon-wrapper mb-3">
                        <i class="bi bi-eye neuro-icon" style="color: var(--neuro-success);"></i>
                    </div>
                    <h3 class="neuro-counter mb-2" data-counter data-target="<?= $publicComments ?>" aria-live="polite" aria-atomic="true">0</h3>
                    <p class="neuro-label mb-0">Comentarios Públicos</p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card neuro-card neuro-hover" data-animate-in="fade-up" data-delay="900">
                <div class="card-body text-center py-4">
                    <div class="neuro-icon-wrapper mb-3">
                        <i class="bi bi-eye-slash neuro-icon" style="color: var(--neuro-warning);"></i>
                    </div>
                    <h3 class="neuro-counter mb-2" data-counter data-target="<?= $internalComments ?>" aria-live="polite" aria-atomic="true">0</h3>
                    <p class="neuro-label mb-0">Comentarios Internos</p>
                </div>
            </div>
        </div>
    </div>
</div>
