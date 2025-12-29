<?php
declare(strict_types=1);

namespace App\Controller\Traits;

/**
 * Statistics Controller Trait
 *
 * Provides unified statistics action for all ticket-like modules (Tickets, PQRS, Compras).
 * Handles statistics rendering with consistent data normalization and view structure.
 */
trait StatisticsControllerTrait
{
    /**
     * Unified statistics action for all modules
     *
     * @param string $entityType Entity type: 'ticket', 'pqrs', or 'compra'
     * @param array<string, mixed> $options Configuration options ['defaultRange' => '30days', etc.]
     * @return void Renders statistics view
     */
    protected function renderStatistics(string $entityType, array $options = []): void
    {
        // Parse filters from query params
        $filters = $this->parseStatisticsFilters($options['defaultRange'] ?? '30days');

        // Get statistics and trend data based on entity type
        switch ($entityType) {
            case 'ticket':
                $stats = $this->statisticsService->getTicketStats($filters);
                $agentPerformance = $this->statisticsService->getTicketAgentPerformance($filters);
                $recentActivity = $this->statisticsService->getRecentActivity();
                $trends = $this->statisticsService->getTicketTrendData(30);

                // Merge data
                $stats = array_merge($stats, $agentPerformance, $recentActivity);
                $viewFile = 'statistics';
                break;

            case 'pqrs':
                $stats = $this->statisticsService->getPqrsStats($filters);
                $trends = $this->statisticsService->getPqrsTrendData(30);
                $viewFile = 'statistics';
                break;

            case 'compra':
                $stats = $this->statisticsService->getComprasStats($filters);
                $trends = $this->statisticsService->getComprasTrendData(30);
                $viewFile = 'statistics';
                break;

            default:
                throw new \InvalidArgumentException("Invalid entity type: {$entityType}");
        }

        // Normalize data for view
        $viewData = $this->normalizeStatisticsData($stats, $trends, $entityType, $filters);

        $this->set($viewData);
        $this->viewBuilder()->setTemplate($viewFile);
    }

    /**
     * Parse date range filters from request
     *
     * @param string $defaultRange Default range if not specified
     * @return array<string, string|null> Filters array
     */
    private function parseStatisticsFilters(string $defaultRange = '30days'): array
    {
        $range = $this->request->getQuery('range', $defaultRange);
        $startDate = $this->request->getQuery('start_date');
        $endDate = $this->request->getQuery('end_date');

        return [
            'date_range' => $range,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    /**
     * Normalize statistics data for consistent view structure
     *
     * @param array<string, mixed> $stats Statistics data
     * @param array<string, mixed> $trends Trend data
     * @param string $entityType Entity type
     * @param array<string, string|null> $filters Applied filters
     * @return array<string, mixed> Normalized view data
     */
    private function normalizeStatisticsData(array $stats, array $trends, string $entityType, array $filters): array
    {
        // Common metrics (all modules)
        $viewData = [
            'entityType' => $entityType,
            'filters' => $filters,
        ];

        // Map statistics based on entity type
        switch ($entityType) {
            case 'ticket':
                $viewData = array_merge($viewData, [
                    // KPI Cards
                    'total' => $stats['total_tickets'] ?? 0,
                    'recentCount' => $stats['recent_tickets'] ?? 0,
                    'unassignedCount' => $stats['unassigned_tickets'] ?? 0,
                    'activeAgentsCount' => $stats['active_agents'] ?? 0,

                    // Charts
                    'statusDistribution' => $stats['tickets_by_status'] ?? [],
                    'priorityDistribution' => $stats['tickets_by_priority'] ?? [],
                    'channelDistribution' => $stats['channel_counts'] ?? [],
                    'chartLabels' => $trends['chart_labels'] ?? [],
                    'chartData' => $trends['chart_data'] ?? [],

                    // Performance metrics
                    'avgResponseTime' => $stats['avg_response_time'] ?? null,
                    'avgResolutionTime' => $stats['avg_resolution_time'] ?? null,
                    'responseRate' => $stats['response_rate'] ?? 0,
                    'resolutionRate' => $stats['resolution_rate'] ?? 0,

                    // Tables
                    'topAgents' => $stats['tickets_by_agent'] ?? [],
                    'topRequesters' => $stats['top_requesters'] ?? [],

                    // Comment stats
                    'totalComments' => $stats['total_comments'] ?? 0,
                    'publicComments' => $stats['public_comments'] ?? 0,
                    'internalComments' => $stats['internal_comments'] ?? 0,
                ]);
                break;

            case 'pqrs':
                $viewData = array_merge($viewData, [
                    // KPI Cards
                    'total' => $stats['total_pqrs'] ?? 0,
                    'recentCount' => $stats['recent_pqrs'] ?? 0,
                    'unassignedCount' => $stats['total_unassigned'] ?? 0,
                    'activeAgentsCount' => $stats['active_agents_count'] ?? 0,
                    'totalResolved' => $stats['total_resolved'] ?? 0,
                    'totalPending' => $stats['total_pending'] ?? 0,
                    'resolvedInPeriod' => $stats['resolved_in_period'] ?? 0,

                    // Charts
                    'statusDistribution' => $stats['status_counts'] ?? [],
                    'priorityDistribution' => $stats['priority_counts'] ?? [],
                    'typeDistribution' => $stats['type_counts'] ?? [],
                    'channelDistribution' => $stats['channel_counts'] ?? [],
                    'chartLabels' => $trends['chart_labels'] ?? [],
                    'chartData' => $trends['chart_data'] ?? [],

                    // Performance
                    'avgResolutionDays' => $stats['avg_resolution_days'] ?? 0,
                    'avgResolutionHours' => $stats['avg_resolution_hours'] ?? 0,
                    'topAgents' => $stats['top_agents'] ?? [],

                    // Filters display
                    'dateFrom' => $stats['date_from'] ?? null,
                    'dateTo' => $stats['date_to'] ?? null,
                ]);
                break;

            case 'compra':
                $viewData = array_merge($viewData, [
                    // KPI Cards
                    'total' => $stats['total_compras'] ?? 0,
                    'recentCount' => $stats['recent_compras'] ?? 0,
                    'unassignedCount' => $stats['unassigned_compras'] ?? 0,
                    'activeAgentsCount' => $stats['active_agents_count'] ?? 0,

                    // Charts
                    'statusDistribution' => $stats['status_counts'] ?? [],
                    'priorityDistribution' => $stats['priority_counts'] ?? [],
                    'channelDistribution' => $stats['channel_counts'] ?? [],
                    'chartLabels' => $trends['chart_labels'] ?? [],
                    'chartData' => $trends['chart_data'] ?? [],

                    // SLA Metrics
                    'slaMetrics' => $stats['sla_metrics'] ?? null,

                    // Approval Metrics
                    'approvalMetrics' => $stats['approval_metrics'] ?? null,

                    // Performance
                    'avgResolutionDays' => $stats['avg_resolution_days'] ?? 0,
                    'avgResolutionHours' => $stats['avg_resolution_hours'] ?? 0,
                    'topAgents' => $stats['top_agents'] ?? [],
                    'topRequesters' => $stats['top_requesters'] ?? [],
                ]);
                break;
        }

        return $viewData;
    }
}
