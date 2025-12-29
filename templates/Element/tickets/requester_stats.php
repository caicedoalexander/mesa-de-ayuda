<?php
/**
 * Top Requesters Table - Minimalist Design
 *
 * @var \Cake\ORM\ResultSet|array $topRequesters Top requesters data
 */

// No need to calculate maxCount anymore - we'll use resolution percentage directly
?>

<<<<<<< HEAD
<div class="modern-card chart-card stats-table-card" data-animate="fade-up" data-delay="800">
    <div class="chart-header">
        <h5 class="chart-title">
            <i class="bi bi-person-check"></i>
            Top Solicitantes
        </h5>
    </div>
    <div class="stats-table-content">
        <?php if (!empty($topRequesters) && count($topRequesters) > 0): ?>
            <div class="stats-table-list">
                <?php $rank = 1; foreach ($topRequesters as $requester): ?>
                    <?php
                        // Get user object (may be attached as 'requester')
                        $requesterUser = isset($requester->requester) && is_object($requester->requester) ? $requester->requester : null;
                        $requesterName = h($requester->requester_name ?? 'N/A');
                        $requesterEmail = h($requester->requester_email ?? '');
                        $requesterInitials = '';
                        $nameParts = explode(' ', $requester->requester_name ?? '');
                        if (count($nameParts) >= 2) {
                            $requesterInitials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                        } else {
                            $requesterInitials = strtoupper(substr($requesterName, 0, 2));
                        }
                        // Calculate resolution percentage for this requester
                        $totalCount = $requester->total_count ?? $requester->count ?? 0;
                        $resolvedCount = $requester->resolved_count ?? 0;
                        $progressPercent = $totalCount > 0 ? ($resolvedCount / $totalCount) * 100 : 0;
                        $rankClass = '';
                        if ($rank === 1) $rankClass = 'rank-first';
                        elseif ($rank === 2) $rankClass = 'rank-second';
                        elseif ($rank === 3) $rankClass = 'rank-third';
                    ?>

                    <div class="stats-row stats-row-detailed <?= $rankClass ?>" data-rank="<?= $rank ?>">
                        <div class="stats-rank">
                            <span class="rank-number"><?= $rank ?></span>
                        </div>

                        <?php if ($requesterUser): ?>
                            <div class="stats-avatar">
                                <?= $this->User->profileImageTag($requesterUser, ['width' => '40', 'height' => '40', 'class' => 'rounded-circle object-fit-cover']) ?>
                            </div>
                        <?php else: ?>
                            <div class="stats-avatar stats-avatar-orange">
                                <span><?= $requesterInitials ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="stats-info">
                            <div class="stats-name"><?= $requesterName ?></div>
                            <div class="stats-meta"><?= $requesterEmail ?></div>
                            <div class="stats-progress">
                                <div class="progress-track">
                                    <div class="progress-fill progress-orange" style="width: <?= $progressPercent ?>%"></div>
                                </div>
                            </div>
                        </div>

                        <div class="stats-metrics">
                            <div class="metric-item">
                                <span class="metric-value"><?= number_format($requester->total_count ?? $requester->count ?? 0) ?></span>
                                <span class="metric-label">Total</span>
                            </div>
                            <div class="metric-item">
                                <span class="metric-value"><?= number_format($requester->active_count ?? 0) ?></span>
                                <span class="metric-label">Activos</span>
                            </div>
                            <div class="metric-item metric-highlight">
                                <span class="metric-value"><?= number_format($requester->resolved_count ?? 0) ?></span>
                                <span class="metric-label">Resueltos</span>
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
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-person-lines-fill"></i> Top Solicitantes</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($topRequesters) && count($topRequesters) > 0): ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;" class="text-center">#</th>
                            <th>Solicitante</th>
                            <th style="width: 100px;" class="text-center">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($topRequesters as $requester): ?>
                            <tr>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark"><?= $rank ?></span>
                                </td>
                                <td>
                                    <strong><?= h($requester->requester_name) ?></strong>
                                    <br>
                                    <small class="text-muted"><?= h($requester->requester_email) ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?= number_format($requester->count) ?></span>
                                </td>
                            </tr>
                        <?php $rank++; endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">No hay datos de solicitantes disponibles.</p>
>>>>>>> c0d0b3845e543ad02c0c92544fb1b1ded4046e06
        <?php endif; ?>
    </div>
</div>
