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

<div class="modern-card chart-card" data-animate="fade-up" data-delay="700">
    <div class="chart-header">
        <h5 class="chart-title">
            Agentes
        </h5>
    </div>
    <div>
        <?php if (!empty($topAgents) && count($topAgents) > 0): ?>
            <div class="modern-table-wrapper">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;" class="text-center">Ranking</th>
                            <th>Agente</th>
                            <th style="width: 120px;" class="text-center">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($topAgents as $agent): ?>
                            <tr>
                                <td class="text-center">
                                    <?php if ($rank === 1): ?>
                                        <i class="bi bi-trophy-fill" style="font-size: 1.5rem; color: #FFD700;"></i>
                                    <?php elseif ($rank === 2): ?>
                                        <i class="bi bi-trophy-fill" style="font-size: 1.3rem; color: #C0C0C0;"></i>
                                    <?php elseif ($rank === 3): ?>
                                        <i class="bi bi-trophy-fill" style="font-size: 1.2rem; color: #CD7F32;"></i>
                                    <?php else: ?>
                                        <span class="modern-badge badge-green">#<?= $rank ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= h($agent->agent_name ?? ($agent->Assignees->first_name . ' ' . $agent->Assignees->last_name)) ?></strong>
                                </td>
                                <td class="text-center">
                                    <span class="modern-badge badge-blue"><?= number_format($agent->count) ?></span>
                                </td>
                            </tr>
                        <?php $rank++; endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center py-4 mb-0">No hay datos de agentes disponibles.</p>
        <?php endif; ?>
    </div>
</div>
