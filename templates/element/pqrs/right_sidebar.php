<!-- Right Sidebar - User Info (with independent scroll) -->
<div class="sidebar-right d-flex flex-column p-3">
    <div class="bg-white p-3 text-start shadow-sm" style="border-radius: 8px;">
        <div class="avatar-large text-white rounded-circle d-flex align-items-center justify-content-center fw-bold mb-2"
            style="width: 60px; height: 60px; font-size: 28px; background-color: #CD6A15">
            <?= strtoupper(substr($pqrs->requester_name, 0, 2)) ?>
        </div>
        <div class="fw-semibold"><?= h($pqrs->requester_name) ?></div>
        <small class="text-muted"><?= h($pqrs->requester_email) ?></small>
    </div>

    <div class="sidebar-scroll flex-grow-1 overflow-auto my-3 bg-white shadow-sm" style="border-radius: 8px;">
        <div class="p-3">
        <section class="mb-3">
            <h3 class="fs-6 mb-3">Información del solicitante</h3>

            <?php if ($pqrs->requester_phone): ?>
            <div class="mb-2">
                <label class="small text-muted fw-semibold mb-1">Teléfono</label>
                <div class="small"><?= h($pqrs->requester_phone) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($pqrs->requester_id_number): ?>
            <div class="mb-2">
                <label class="small text-uppercase text-muted fw-semibold mb-1">Identificación</label>
                <div class="small"><?= h($pqrs->requester_id_number) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($pqrs->requester_city): ?>
            <div class="mb-2">
                <label class="small text-uppercase text-muted fw-semibold mb-1">Ciudad</label>
                <div class="small"><?= h($pqrs->requester_city) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($pqrs->requester_address): ?>
            <div class="mb-2">
                <label class="small text-uppercase text-muted fw-semibold mb-1">Dirección</label>
                <div class="small"><?= h($pqrs->requester_address) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($pqrs->organization): ?>
            <div class="mb-2">
                <label class="small text-uppercase text-muted fw-semibold mb-1">Organización</label>
                <div class="small"><?= h($pqrs->organization->name) ?></div>
            </div>
            <?php endif; ?>

            <div class="mb-2">
                <label class="small text-uppercase text-muted fw-semibold mb-1">IP</label>
                <div class="small"><?= h($pqrs->ip_address) ?></div>
            </div>
        </section>

        <section class="mb-3">
            <h3 class="fs-6 fw-semibold mb-3">Historial de cambios</h3>
            <!-- PERFORMANCE FIX: Lazy load history on scroll -->
            <div id="history-container" data-entity-type="pqrs" data-entity-id="<?= $pqrs->id ?>" data-loaded="false">
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
