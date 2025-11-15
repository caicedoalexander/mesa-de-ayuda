<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Ticket> $tickets
 */
$this->assign('title', 'Tickets');
?>

<?php
// Get user info for sidebar
$user = $this->getRequest()->getAttribute('identity');
$userRole = $user ? $user->get('role') : null;
$userId = $user ? $user->get('id') : null;
?>

<div class="d-flex w-25">
    <?= $this->cell('TicketsSidebar::display', [$view, $userRole, $userId]) ?>
</div>

<div class="py-4 px-5 w-75">
    <div class="d-flex gap-3 align-items-center mb-3">
        <img src="<?= $this->Url->build('img/ticket.png') ?>" height="40">
        <h2 class="fw-normal m-0">
            <?php
            $titles = [
                'sin_asignar' => 'Tickets sin asignar',
                'todos_sin_resolver' => 'Tickets sin resolver',
                'nuevos' => 'Tickets nuevos',
                'abiertos' => 'Tickets abiertos',
                'pendientes' => 'Tickets pendientes',
                'resueltos' => 'Tickets resueltos',
                'mis_tickets' => 'Mis tickets',
            ];
            echo $titles[$view] ?? 'Tickets';
            ?>
        </h2>
    </div>

    <!-- Search Bar -->
    <div class="mb-3">
        <?= $this->Form->create(null, ['type' => 'get', 'class' => 'd-flex gap-2 align-items-center']) ?>
            <?= $this->Form->hidden('view', ['value' => $view]) ?>

            <div class="input-group flex-grow-1">
                <span class="input-group-text bg-white">
                    <i class="bi bi-search small"></i>
                </span>
                <?= $this->Form->control('search', [
                    'label' => false,
                    'class' => 'form-control rounded-0 form-control-sm py-2',
                    'placeholder' => 'Buscar por número, asunto, email...',
                    'value' => $filters['search'] ?? '',
                    'type' => 'text',
                    'style' => 'box-shadow: none; width: 400px;'
                ]) ?>
            </div>

            <?= $this->Form->button('Buscar', [
                'class' => 'btn btn-sm btn-primary',
                'escape' => false,
                'title' => 'Buscar'
            ]) ?>

            <?php if (!empty($filters['search'])): ?>
                <?= $this->Html->link('<i class="bi bi-x-lg"></i>', ['action' => 'index', '?' => ['view' => $view]], [
                    'class' => 'btn btn-outline-danger',
                    'escape' => false,
                    'title' => 'Limpiar búsqueda'
                ]) ?>
            <?php endif; ?>
        <?= $this->Form->end() ?>
    </div>

    <div class="mb-3 fs-6 d-flex align-items-center">
        <small class="me-1"> <?= $tickets->count() ?> Tickets </small>
        <small class="m-0 text-muted">(<?= $this->Paginator->counter(__('Pagina {{page}} de {{pages}}')) ?>)</small>
    </div>

    <?php if ($tickets->count() > 0): ?>
        <div class="">
            <table class="table table-hover mb-0">
                <thead class="sticky-top">
                    <tr class="">
                        <th class="w-fit pe-4 align-middle" style="width:36px">
                            <input type="checkbox" id="checkAll" class="form-check-input border border-dark rounded" />
                        </th>
                        <th class="w-fit fw-semibold align-middle">
                            Estado
                        </th>
                        <th class="w-fit fw-semibold align-middle">
                            Asunto
                        </th>
                        <th class="w-fit fw-semibold align-middle">Solicitante</th>
                        <th class="w-fit fw-semibold align-middle">Asignado a</th>
                        <th class="w-fit fw-semibold align-middle">
                            <?= $this->Paginator->sort('created', 'Solicitado') ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td class="py-1 align-middle">
                                <input type="checkbox" class="form-check-input row-check rounded border border-dark" value="<?= (int)$ticket->id ?>" />
                            </td>
                            <td class="py-1 align-middle small" style="width: 100px;">
                                <?= $this->Status->badge($ticket->status) ?>
                            </td>
                            <td class="py-1 fw-light align-middle small text-truncate" style="min-width: 300px; max-width: 300px;">
                                <?php if ($ticket->assignee_id === 7): ?>
                                    <?= $this->Html->link(
                                        h($ticket->subject),
                                        ['action' => 'view_compras', $ticket->id],
                                        ['style' => 'text-decoration: none; color: var(--gray-900);', 'class' => '']
                                    ) ?>
                                <?php else: ?>
                                    <?= $this->Html->link(
                                        h($ticket->subject),
                                        ['action' => 'view', $ticket->id],
                                        ['style' => 'text-decoration: none; color: var(--gray-900);', 'class' => '']
                                    ) ?>
                                <?php endif; ?>
                            </td>
                            <td class="py-1 text-truncate align-middle small" style="min-width: 150px; max-width: 150px;">
                                <strong class=""><?= h($ticket->requester->name) ?></strong>
                                <span class="text-muted" style="font-size: 12px; margin: auto 0">
                                    (<?= h($ticket->requester->email) ?>)
                                </span>
                            </td>
                            <td class="py-1 align-middle small" style="max-width: 150px;">
                                <?php if ($ticket->assignee_id === 7): ?>
                                    <?= $this->Form->create(null, ['url' => ['action' => 'assign', $ticket->id]]) ?>
                                    <?= $this->Form->select('agent_id', $agents, [
                                        'value' => $ticket->assignee_id,
                                        'empty' => 'Sin asignar',
                                        'class' => 'select2',
                                        'onchange' => 'this.form.submit()',
                                        'disabled' => 'true'
                                    ]) ?>
                                    <?= $this->Form->end() ?>
                                <?php else: ?>
                                    <?= $this->Form->create(null, ['url' => ['action' => 'assign', $ticket->id]]) ?>
                                    <?= $this->Form->select('agent_id', $agents, [
                                        'value' => $ticket->assignee_id,
                                        'empty' => 'Sin asignar',
                                        'class' => 'select2',
                                        'onchange' => 'this.form.submit()',
                                    ]) ?>
                                    <?= $this->Form->end() ?>
                                <?php endif; ?>
                            </td>
                            <td class="py-1 align-middle small lh-1">
                                <?= $this->TimeHuman->short($ticket->created) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?= $this->element('pagination') ?>
    <?php else: ?>
        <div class="table-container">
            <div style="padding: 40px; text-align: center; color: var(--gray-600);">
                <p style="font-size: 18px;">No hay tickets en esta vista</p>
            </div>
        </div>
    <?php endif; ?>
</div>
