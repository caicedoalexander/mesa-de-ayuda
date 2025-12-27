<!-- Left Sidebar - PQRS Info (with independent scroll) -->
<div class="sidebar-left d-flex flex-column p-3">
    <div class="sidebar-scroll flex-grow-1 overflow-auto shadow-sm bg-white" style="border-radius: 8px;">
        <div class="p-3">
        <?php
        // Check if PQRS is locked (in final status)
        $isLocked = $isLocked ?? in_array($pqrs->status, ['resuelto', 'cerrado']);
        ?>
        <section class="mb-4">
            <h3 class="fs-6 fw-semibold mb-3">Información del PQRS</h3>

            <div class="mb-3">
                <label class="small text-muted fw-semibold mb-1">Tipo:</label>
                <div><?= $this->Pqrs->typeBadge($pqrs->type) ?></div>
            </div>

            <div class="mb-3">
                <label class="small text-muted text-muted fw-semibold mb-1">Estado:</label>
                <div>
                    <?= $this->Pqrs->statusBadge($pqrs->status) ?>
                    <?php if ($isLocked): ?>
                        <i class="bi bi-lock-fill text-muted" title="Solicitud cerrada"></i>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mb-3">
                <label class="small text-muted text-muted fw-semibold mb-1">Prioridad:</label>
                <?= $this->Form->create(null, ['url' => ['action' => 'changePriority', $pqrs->id], 'class' => 'm-0']) ?>
                <?= $this->Form->select('priority', [
                    'baja' => '🟢 Baja',
                    'media' => '🟡 Media',
                    'alta' => '🟠 Alta',
                    'urgente' => '🔴 Urgente'
                ], [
                    'value' => $pqrs->priority,
                    'class' => 'form-select form-select-sm',
                    'disabled' => $isLocked,
                    'onchange' => 'this.form.submit()'
                ]) ?>
                <?= $this->Form->end() ?>
            </div>

            <div>
                <label class="small text-muted text-muted fw-semibold">Canal:</label>
                <div class="small text-uppercase"><?= h($pqrs->channel) ?></div>
            </div>
        </section>

        <section class="mb-4">
            <h3 class="fs-6 fw-semibold mb-3">SLA (Service Level Agreement)</h3>

            <div class="mb-3">
                <label class="small text-muted fw-semibold mb-1">Primera Respuesta:</label>
                <div><?= $this->Pqrs->firstResponseSlaBadge($pqrs, true) ?></div>
                <?php if ($pqrs->first_response_sla_due): ?>
                    <small class="text-muted">
                        Vence: <?= $pqrs->first_response_sla_due->format('d/m/Y H:i') ?>
                    </small>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="small text-muted fw-semibold mb-1">Resolución:</label>
                <div><?= $this->Pqrs->resolutionSlaBadge($pqrs, true) ?></div>
                <?php if ($pqrs->resolution_sla_due): ?>
                    <small class="text-muted">
                        Vence: <?= $pqrs->resolution_sla_due->format('d/m/Y H:i') ?>
                    </small>
                <?php endif; ?>
            </div>

            <?php if ($user && $user->role === 'admin'): ?>
                <div class="mt-2">
                    <?= $this->Html->link(
                        '<i class="bi bi-arrow-clockwise"></i> Recalcular SLA',
                        ['action' => 'recalculateSla', $pqrs->id],
                        [
                            'class' => 'btn btn-sm btn-outline-secondary w-100',
                            'escape' => false
                        ]
                    ) ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="mb-4">
            <h3 class="fs-6 fw-semibold mb-3">Asignación</h3>
            <?= $this->Form->create(null, ['url' => ['action' => 'assign', $pqrs->id], 'class' => 'm-0', 'id' => 'assign-form']) ?>
            <?= $this->Form->select('assignee_id', $agents, [
                'empty' => '-- Sin asignar --',
                'value' => $pqrs->assignee_id,
                'class' => 'form-select form-select-sm',
                'disabled' => $isLocked,
                'id' => 'agent-select'
            ]) ?>
            <?= $this->Form->end() ?>
        </section>
        </div>
    </div>
</div>