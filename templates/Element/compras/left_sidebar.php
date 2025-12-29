<!-- Left Sidebar - Compra Info (with independent scroll) -->
<div class="sidebar-left d-flex flex-column p-3">
    <div class="sidebar-scroll flex-grow-1 overflow-auto shadow-sm bg-white" style="border-radius: 8px;">
        <div class="p-3">
        <?php
        // Check if compra is locked (in final status)
        $isLocked = $isLocked ?? in_array($compra->status, ['completado', 'rechazado', 'convertido']);
        ?>
        <section class="mb-3">
            <h3 class="fs-6 fw-semibold mb-3">Información de la Compra</h3>

            <div class="mb-3">
                <label class="small text-muted fw-semibold mb-1">Estado:</label>
                <div>
                    <?= $this->Status->statusBadge($compra->status, 'compra') ?>
                    <?php if ($isLocked): ?>
                        <i class="bi bi-lock-fill text-muted" title="Solicitud cerrada"></i>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mb-3">
                <label class="small text-muted fw-semibold mb-1">Prioridad:</label>
                <div class="mb-2">
                    <?= $this->Status->priorityBadge($compra->priority) ?>
                </div>
                <?php if (!$isLocked): ?>
                <?= $this->Form->create(null, ['url' => ['action' => 'changePriority', $compra->id], 'class' => '']) ?>
                <?= $this->Form->select('priority', [
                    'baja' => 'Cambiar a Baja',
                    'media' => 'Cambiar a Media',
                    'alta' => 'Cambiar a Alta',
                    'urgente' => 'Cambiar a Urgente'
                ], [
                    'empty' => '-- Cambiar prioridad --',
                    'class' => 'form-select form-select-sm',
                    'onchange' => 'this.form.submit()'
                ]) ?>
                <?= $this->Form->end() ?>
                <?php endif; ?>
            </div>

        </section>

        <section class="mb-3">
            <h3 class="fs-6 fw-semibold mb-3">SLA (Service Level Agreement)</h3>

            <div class="mb-3">
                <label class="small text-muted fw-semibold mb-1">Primera Respuesta:</label>
                <div><?= $this->Compras->slaBadge($compra, 'first_response_sla_due', true) ?></div>
                <?php if ($compra->first_response_sla_due): ?>
                    <small class="text-muted">
                        Vence: <?= $compra->first_response_sla_due->format('d/m/Y H:i') ?>
                    </small>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="small text-muted fw-semibold mb-1">Resolución:</label>
                <div><?= $this->Compras->slaBadge($compra, 'resolution_sla_due', true) ?></div>
                <?php if ($compra->resolution_sla_due): ?>
                    <small class="text-muted">
                        Vence: <?= $compra->resolution_sla_due->format('d/m/Y H:i') ?>
                    </small>
                <?php endif; ?>
            </div>

            <?php if ($user && $user->role === 'admin'): ?>
                <div class="mt-2">
                    <?= $this->Html->link(
                        '<i class="bi bi-arrow-clockwise"></i> Recalcular SLA',
                        ['action' => 'recalculateSla', $compra->id],
                        [
                            'class' => 'btn btn-sm btn-outline-secondary w-100',
                            'escape' => false
                        ]
                    ) ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="mb-3">
            <h3 class="small text-muted fw-semibold mb-1">Asignación:</h3>
            <?= $this->Form->create(null, ['url' => ['action' => 'assign', $compra->id], 'class' => 'm-0', 'id' => 'assign-form']) ?>
            <?= $this->Form->select('agent_id', $comprasUsers, [
                'empty' => '-- Sin asignar --',
                'value' => $compra->assignee_id,
                'class' => 'form-select form-select-sm',
                'id' => 'agent-select',
                'disabled' => $this->User->isAssignmentDisabled($user) || $isLocked,
            ]) ?>
            <?= $this->Form->end() ?>
        </section>
        </div>
    </div>
</div>
