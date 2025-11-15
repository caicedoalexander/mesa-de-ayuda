<!-- Right Sidebar - User Info (with independent scroll) -->
<div class="sidebar-right d-flex flex-column border-start bg-light">
    <div class="px-4 py-3 text-start bg-white border-bottom">
        <div class="avatar-large text-white rounded-circle d-flex align-items-center justify-content-center fw-bold mb-2" style="width: 60px; height: 60px; font-size: 28px; background-color: #CD6A15">
            <?= strtoupper(substr($ticket->requester->name, 0, 2)) ?>
        </div>
        <div class="fw-semibold"><?= h($ticket->requester->name) ?></div>
        <small class="text-muted"><?= h($ticket->requester->email) ?></small>
    </div>

    <div class="sidebar-scroll flex-grow-1 overflow-auto p-3">
        <section class="mb-3">
            <h3 class="fs-6 mb-3">Información del Usuario</h3>

            <?php if ($ticket->requester->phone): ?>
            <div class="mb-2">
                <label class="small text-uppercase text-muted fw-semibold mb-1">Teléfono</label>
                <div class="small"><?= h($ticket->requester->phone) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($ticket->organization): ?>
            <div class="mb-2">
                <label class="small text-uppercase text-muted fw-semibold mb-1">Organización</label>
                <div class="small"><?= h($ticket->organization->name) ?></div>
            </div>
            <?php endif; ?>

            <div class="mb-2">
                <label class="small text-uppercase text-muted fw-semibold mb-1">Rol</label>
                <div class="small text-capitalize"><?= h($ticket->requester->role) ?></div>
            </div>

            <div class="mb-2">
                <label class="small text-uppercase text-muted fw-semibold mb-1">Usuario desde</label>
                <div class="small"><?= $this->TimeHuman->long($ticket->requester->created) ?></div>
            </div>
        </section>

        <?php if (!empty($ticket->ticket_followers)): ?>
        <section class="mb-3">
            <h3 class="fs-6 fw-semibold mb-3">Seguidores</h3>
            <?php foreach ($ticket->ticket_followers as $follower): ?>
                <div class="d-flex align-items-center gap-2 py-2">
                    <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 28px; height: 28px; font-size: 11px;">
                        <?= strtoupper(substr($follower->user->name, 0, 1)) ?>
                    </div>
                    <small><?= h($follower->user->name) ?></small>
                </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <section class="mb-3">
            <h3 class="fs-6 fw-semibold mb-3">Historial de Cambios</h3>
            <?php if (!empty($ticket->ticket_history)): ?>
            <div class="timeline">
                <?php foreach ($ticket->ticket_history as $history): ?>
                <div class="timeline-item mb-3">
                    <div class="d-flex gap-2">
                        <div class="timeline-icon flex-shrink-0">
                            <?php
                            // Icon based on field changed
                            $icon = 'circle-fill';
                            $iconColor = 'text-secondary';
                            if ($history->field_name === 'status') {
                                $icon = 'arrow-repeat';
                                $iconColor = 'text-primary';
                            } elseif ($history->field_name === 'assignee_id') {
                                $icon = 'person-fill';
                                $iconColor = 'text-success';
                            } elseif ($history->field_name === 'priority') {
                                $icon = 'exclamation-triangle-fill';
                                $iconColor = 'text-warning';
                            }
                            ?>
                            <i class="bi bi-<?= $icon ?> <?= $iconColor ?>" style="font-size: 12px;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="small mb-1">
                                <?php if ($history->user): ?>
                                    <strong><?= h($history->user->name) ?></strong>
                                <?php else: ?>
                                    <strong>Sistema</strong>
                                <?php endif; ?>
                            </div>
                            <?php if ($history->description): ?>
                                <div class="small text-muted mb-1"><?= h($history->description) ?></div>
                            <?php else: ?>
                                <div class="small text-muted mb-1">
                                    <strong><?= h(ucfirst(str_replace('_', ' ', $history->field_name))) ?>:</strong>
                                    <?php if ($history->old_value): ?>
                                        <span class="text-decoration-line-through"><?= h($history->old_value) ?></span>
                                        →
                                    <?php endif; ?>
                                    <span><?= h($history->new_value) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="small text-muted fw-bold">
                                <?= $this->TimeHuman->short($history->created) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted small">No hay historial de cambios para este ticket.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
