<!-- Left Sidebar - Ticket Info (with independent scroll) -->
    <div class="sidebar-left d-flex flex-column bg-white">
        <div class="sidebar-scroll flex-grow-1 border-end overflow-auto p-3">
            <section class="mb-4">
                <h3 class="fs-6 fw-semibold mb-3">Información del Ticket</h3>

                <div class="mb-3">
                    <label class="small text-uppercase text-muted fw-semibold mb-1">Estado:</label>
                    <div><?= $this->Status->badge($ticket->status) ?></div>
                </div>

                <div class="mb-3">
                    <label class="small text-uppercase text-muted fw-semibold mb-1">Prioridad:</label>
                    <?= $this->Form->create(null, ['url' => ['action' => 'changePriority', $ticket->id], 'class' => 'm-0']) ?>
                    <?= $this->Form->select('priority', [
                        'baja' => '🟢 Baja',
                        'media' => '🟡 Media',
                        'alta' => '🟠 Alta',
                        'urgente' => '🔴 Urgente'
                    ], [
                        'value' => $ticket->priority,
                        'class' => 'form-select form-select-sm',
                        'onchange' => 'this.form.submit()'
                    ]) ?>
                    <?= $this->Form->end() ?>
                </div>

                <div class="mb-3">
                    <label class="small text-uppercase text-muted fw-semibold mb-1">Canal:</label>
                    <div class="small text-uppercase"><?= h($ticket->channel) ?></div>
                </div>
            </section>

            <!--
                <section class="mb-4">
                    <h3 class="fs-6 fw-semibold mb-3">Solicitante</h3>
                    <div>
                        <strong class="d-block"><?= h($ticket->requester->name) ?></strong>
                        <small class="text-muted"><?= h($ticket->requester->email) ?></small>
                        <?php if ($ticket->requester->phone): ?>
                            <br><small class="text-muted">📞 <?= h($ticket->requester->phone) ?></small>
                        <?php endif; ?>
                    </div>
                </section>
            -->

            <section class="mb-4">
                <h3 class="fs-6 fw-semibold mb-3">Asignación</h3>
                <?php if ($ticket->assignee): ?>
                    <div class="mb-2">
                        <strong class="d-block"><?= h($ticket->assignee->name) ?></strong>
                        <small class="text-muted"><?= h($ticket->assignee->email) ?></small>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-2">Sin asignar</p>
                <?php endif; ?>

            </section>

            <?php if (!empty($ticket->tags) || !empty($tags) && !empty($ticket->assignee_id) !== 7): ?>
            <section class="mb-4">
                <h3 class="fs-6 fw-semibold mb-3">Etiquetas</h3>
                <?php if (!empty($ticket->tags)): ?>
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <?php foreach ($ticket->tags as $tag): ?>
                            <span class="badge rounded-pill" style="background-color: <?= h($tag->color) ?>;">
                                <?= h($tag->name) ?>
                                <?= $this->Form->postLink('x', ['action' => 'removeTag', $ticket->id, $tag->id], [
                                    'confirm' => '¿Eliminar etiqueta?',
                                    'class' => 'text-white text-decoration-none ms-1 fw-bold opacity-75'
                                ]) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($tags)): ?>
                    <?= $this->Form->create(null, ['url' => ['action' => 'addTag', $ticket->id]]) ?>
                    <?= $this->Form->control('tag_id', [
                        'options' => $tags,
                        'empty' => '-- Agregar etiqueta --',
                        'label' => false,
                        'class' => 'form-select form-select-sm mb-2'
                    ]) ?>
                    <?= $this->Form->button('Agregar', ['class' => 'btn btn-outline-secondary btn-sm w-100 my-2']) ?>
                    <?= $this->Form->end() ?>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </div>
    </div>