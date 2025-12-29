<?php
/**
 * Top Requesters Table (Tickets-specific)
 *
 * @var \Cake\ORM\ResultSet|array $topRequesters Top requesters data
 */
?>

<div class="modern-card chart-card" data-animate="fade-up" data-delay="800">
    <div class="chart-header">
        <h5 class="chart-title">
            Solicitantes
        </h5>
    </div>
    <div>
        <?php if (!empty($topRequesters) && count($topRequesters) > 0): ?>
            <div class="modern-table-wrapper">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;" class="text-center">Ranking</th>
                            <th>Solicitante</th>
                            <th style="width: 120px;" class="text-center">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($topRequesters as $requester): ?>
                            <tr>
                                <td class="text-center">
                                    <?php if ($rank === 1): ?>
                                        <i class="bi bi-star-fill" style="font-size: 1.5rem; color: #FFD700;"></i>
                                    <?php elseif ($rank === 2): ?>
                                        <i class="bi bi-star-fill" style="font-size: 1.3rem; color: #C0C0C0;"></i>
                                    <?php elseif ($rank === 3): ?>
                                        <i class="bi bi-star-fill" style="font-size: 1.2rem; color: #CD7F32;"></i>
                                    <?php else: ?>
                                        <span class="modern-badge badge-orange">#<?= $rank ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong style="color: var(--gray-900);"><?= h($requester->requester_name) ?></strong>
                                        <div style="font-size: 0.8125rem; color: var(--gray-500); margin-top: 2px;">
                                            <?= h($requester->requester_email) ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="modern-badge badge-green"><?= number_format($requester->count) ?></span>
                                </td>
                            </tr>
                        <?php $rank++; endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center py-4 mb-0">No hay datos de solicitantes disponibles.</p>
        <?php endif; ?>
    </div>
</div>
