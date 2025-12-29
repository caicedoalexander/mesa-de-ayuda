<?php
/**
 * Top Agents Performance Table
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
?>

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
        <?php endif; ?>
    </div>
</div>
