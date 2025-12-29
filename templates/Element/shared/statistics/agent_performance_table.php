<?php
/**
 * Top Agents Performance Table - Minimalist Design
 *
 * @var \Cake\ORM\ResultSet|array $topAgents Top agents data
 * @var string $entityType 'ticket', 'pqrs', or 'compra'
 */

$entityLabels = [
    'ticket' => 'tickets',
    'pqrs' => 'PQRS',
    'compra' => 'compras',
];
$label = $entityLabels[$entityType] ?? 'entidades';

// No need to calculate maxCount anymore - we'll use resolution_rate directly
?>

<<<<<<< HEAD
<div class="modern-card chart-card stats-table-card" data-animate="fade-up" data-delay="700">
    <div class="chart-header">
        <h5 class="chart-title">
            <i class="bi bi-people"></i>
            Top Agentes
        </h5>
    </div>
    <div class="stats-table-content">
        <?php if (!empty($topAgents) && count($topAgents) > 0): ?>
            <div class="stats-table-list">
                <?php $rank = 1; foreach ($topAgents as $agent): ?>
                    <?php
                        // Get user object from Assignees association
                        $agentUser = isset($agent->assignee) && is_object($agent->assignee) ? $agent->assignee : null;
                        $agentName = h($agent->agent_name ?? 'N/A');
                        // Use resolution rate as progress percentage
                        $progressPercent = $agent->resolution_rate ?? 0;
                        $rankClass = '';
                        if ($rank === 1) $rankClass = 'rank-first';
                        elseif ($rank === 2) $rankClass = 'rank-second';
                        elseif ($rank === 3) $rankClass = 'rank-third';
                    ?>

                    <div class="stats-row stats-row-detailed <?= $rankClass ?>" data-rank="<?= $rank ?>">
                        <div class="stats-rank">
                            <span class="rank-number"><?= $rank ?></span>
                        </div>

                        <?php if ($agentUser): ?>
                            <div class="stats-avatar">
                                <?= $this->User->profileImageTag($agentUser, ['width' => '40', 'height' => '40', 'class' => 'rounded-circle object-fit-cover']) ?>
                            </div>
                        <?php else: ?>
                            <div class="stats-avatar stats-avatar-green">
                                <span><?= strtoupper(substr($agentName, 0, 2)) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="stats-info">
                            <div class="stats-name"><?= $agentName ?></div>
                            <div class="stats-progress">
                                <div class="progress-track">
                                    <div class="progress-fill progress-green" style="width: <?= $progressPercent ?>%"></div>
                                </div>
                            </div>
                        </div>

                        <div class="stats-metrics">
                            <div class="metric-item">
                                <span class="metric-value"><?= number_format($agent->assigned_count ?? 0) ?></span>
                                <span class="metric-label">Asignados</span>
                            </div>
                            <div class="metric-item">
                                <span class="metric-value"><?= number_format($agent->resolved_count ?? 0) ?></span>
                                <span class="metric-label">Resueltos</span>
                            </div>
                            <div class="metric-item metric-highlight">
                                <span class="metric-value metric-rate"><?= number_format($agent->resolution_rate ?? 0, 1) ?>%</span>
                                <span class="metric-label">Tasa</span>
                            </div>
                        </div>
                    </div>
                <?php $rank++; endforeach; ?>
            </div>
        <?php else: ?>
            <div class="stats-empty">
                <i class="bi bi-inbox"></i>
                <p>No hay datos disponibles</p>
            </div>
=======
<div class="neuro-card" data-animate-in="fade-up" data-delay="700">
    <div class="neuro-chart-header">
        <h5 class="neuro-chart-title">
            <i class="bi bi-trophy me-2" style="color: var(--neuro-warning);"></i>
            Top Agentes
        </h5>
    </div>
    <div class="pt-3">
        <?php if (!empty($topAgents) && count($topAgents) > 0): ?>
            <div class="table-responsive">
                <table class="neuro-table table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 60px;" class="text-center">Ranking</th>
                            <th>Agente</th>
                            <th style="width: 100px;" class="text-center">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($topAgents as $agent): ?>
                            <tr>
                                <td class="text-center">
                                    <?php if ($rank === 1): ?>
                                        <i class="bi bi-trophy-fill text-warning" style="font-size: 1.3rem;"></i>
                                    <?php elseif ($rank === 2): ?>
                                        <i class="bi bi-trophy-fill text-secondary" style="font-size: 1.2rem;"></i>
                                    <?php elseif ($rank === 3): ?>
                                        <i class="bi bi-trophy-fill text-danger" style="font-size: 1.1rem;"></i>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark"><?= $rank ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="neuro-text-primary"><?= h($agent->agent_name ?? ($agent->Assignees->first_name . ' ' . $agent->Assignees->last_name)) ?></strong>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?= number_format($agent->count) ?></span>
                                </td>
                            </tr>
                        <?php $rank++; endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="neuro-text-muted mb-0">No hay datos de agentes disponibles.</p>
>>>>>>> c0d0b3845e543ad02c0c92544fb1b1ded4046e06
        <?php endif; ?>
    </div>
</div>
