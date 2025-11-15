<?php
/**
 * @var \App\View\AppView $this
 * @var array<string, int> $counts
 * @var string $view
 * @var string|null $userRole
 */
?>
<div class="p-4 w-100 border-end fondo">
    <div class="">
        <h1 class="fs-4 m-0 fw-normal">Vistas</h1>
    </div>
    <ul class="mt-2 list-group">
        <?php if ($userRole !== 'compras'): ?>
        <li class="list-group-item p-0 my-1 list-group-item-action">
            <?= $this->Html->link(
                'Tickets sin asignar <span class="count">' . $counts['sin_asignar'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'sin_asignar']],
                ['class' => $view === 'sin_asignar' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>
        <?php endif; ?>

        <?php if ($userRole !== 'compras'): ?>
        <li class="list-group-item p-0 my-1 list-group-item-action">
            <?= $this->Html->link(
                'Todos sin resolver <span class="count">' . $counts['todos_sin_resolver'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'todos_sin_resolver']],
                ['class' => $view === 'todos_sin_resolver' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>
        <?php endif; ?>

        <?php if ($userRole === 'compras' && isset($counts['mis_tickets'])): ?>
        <li class="list-group-item p-0 my-1 list-group-item-action">
            <?= $this->Html->link(
                'Mis Tickets Asignados <span class="count">' . $counts['mis_tickets'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'mis_tickets']],
                ['class' => $view === 'mis_tickets' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>
        <?php endif; ?>

        <?php if ($userRole !== 'compras'): ?>
        <li class="list-group-item p-0 my-1 list-group-item-action">
            <?= $this->Html->link(
                'Nuevos <span class="count">' . $counts['nuevos'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'nuevos']],
                ['class' => $view === 'nuevos' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>
        <li class="list-group-item p-0 my-1 list-group-item-action">
            <?= $this->Html->link(
                'Abiertos <span class="count">' . $counts['abiertos'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'abiertos']],
                ['class' => $view === 'abiertos' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>
        <li class="list-group-item p-0 my-1 list-group-item-action">
            <?= $this->Html->link(
                'Pendientes <span class="count">' . $counts['pendientes'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'pendientes']],
                ['class' => $view === 'pendientes' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>
        <?php endif; ?>

        <li class="list-group-item p-0 my-1 list-group-item-action">
            <?= $this->Html->link(
                'Resueltos <span class="count">' . $counts['resueltos'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'resueltos']],
                ['class' => $view === 'resueltos' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>
    </ul>
</div>
