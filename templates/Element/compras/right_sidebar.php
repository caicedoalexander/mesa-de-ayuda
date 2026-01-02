<!-- Right Sidebar - User Info (with independent scroll) -->
<div class="sidebar-right d-flex flex-column p-3">
    <div class="bg-white p-3 text-start shadow-sm" style="border-radius: 8px;">
        <div class="avatar-large text-white rounded-circle d-flex align-items-center justify-content-center fw-bold mb-2"
            style="width: 60px; height: 60px; font-size: 28px; background-color: #CD6A15">
            <?= $this->User->initials($compra->requester->name ?? 'N/A') ?>
        </div>
        <div class="fw-semibold"><?= h($compra->requester->name ?? 'N/A') ?></div>
        <small class="text-muted"><?= h($compra->requester->email ?? 'N/A') ?></small>
    </div>

    <div class="sidebar-scroll flex-grow-1 overflow-auto mt-3 bg-white shadow-sm" style="border-radius: 8px;">
        <div class="p-3">
        <section class="mb-3">
            <h3 class="fs-6 mb-3">Información del Solicitante</h3>

            <?php if ($compra->requester->phone ?? null): ?>
                <div class="mb-2">
                    <label class="small text-uppercase text-muted fw-semibold mb-1">Teléfono</label>
                    <div class="small"><?= h($compra->requester->phone) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($compra->requester->organization_id ?? null): ?>
                <div class="mb-2">
                    <label class="small text-uppercase text-muted fw-semibold mb-1">Organización</label>
                    <div class="small"><?= h($compra->requester->organization->name ?? 'N/A') ?></div>
                </div>
            <?php endif; ?>

            <div class="mb-2">
                <label class="small text-muted fw-semibold mb-1">Usuario desde:</label>
                <div class="small"><?= $this->TimeHuman->long($compra->requester->created) ?></div>
            </div>
        </section>

        <?php if (isset($user) && in_array($user->role, ['admin', 'compras']) && !in_array($compra->status, ['completado', 'rechazado'])): ?>
            <section class="mb-3">
                <div class="card">
                    <div class="card-header bg-primary bg-opacity-50 text-dark small fw-bold text-center">
                        <i class="bi bi-ticket-fill"></i> Convertir a Ticket
                    </div>
                    <div class="card-body p-3">
                        <p class="small text-muted mb-3 fw-light">
                            Convierte esta compra en un ticket de soporte. La compra será marcada como convertida.
                        </p>
                        <?= $this->Form->create(null, [
                            'url' => ['action' => 'convertToTicket', $compra->id]
                        ]) ?>
                            <?= $this->Form->button(
                                '<i class="bi bi-arrow-left-circle me-1"></i> Convertir',
                                [
                                    'class' => 'btn btn-primary btn-sm w-100 shadow-sm',
                                    'escapeTitle' => false,
                                    'onclick' => 'return confirm("¿Estás seguro de convertir esta compra a un ticket? La compra será marcada como completada.")'
                                ]
                            ) ?>
                        <?= $this->Form->end() ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="mb-3">
            <h3 class="fs-6 fw-semibold mb-3">Historial de cambios</h3>
            <!-- PERFORMANCE FIX: Lazy load history on scroll -->
            <div id="history-container" data-entity-type="compra" data-entity-id="<?= $compra->id ?>" data-loaded="false">
                <div id="history-loader" class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="small text-muted mt-2">Cargando historial...</p>
                </div>
                <div id="history-content" style="display: none;"></div>
            </div>
        </section>
        </div>
    </div>
</div>
