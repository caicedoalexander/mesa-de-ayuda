<?php
/**
 * Top Requesters Table (Tickets-specific)
 *
 * @var \Cake\ORM\ResultSet|array $topRequesters Top requesters data
 */
?>

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
        <?php endif; ?>
    </div>
</div>
