<?php
/**
 * @var \App\View\AppView $this
 * @var array<string, int> $counts
 * @var string $view
 * @var string|null $userRole
 * @var \App\Model\Entity\User|null $currentUser
 */
?>
<div class="text-white overflow-auto sidebar-scroll" style="background-color: #273244; min-width: 300px; border-radius: 0 8px 8px 0;">
    <div class="p-4">
    <?php if ($currentUser): ?>
        <div class="text-center mb-3 px-3">
            <?= $this->User->profileImageTag($currentUser, ['width' => '80', 'height' => '80', 'class' => 'rounded-circle object-fit-cover shadow']) ?>
            <div class="mt-2">
                <strong><?= h($currentUser->name) ?></strong>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Views -->
    <h6 class="mb-2 fs-6">Vistas</h6>
    <ul class="list-group">
        <?php if (in_array($userRole, ['compras', 'admin'])): ?>
        <li class="list-group-item">
            <?= $this->Html->link(
                'Mis compras <span class="count">' . $counts['mis_compras'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'mis_compras']],
                ['class' => $view === 'mis_compras' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>
        <?php endif; ?>

        <li class="list-group-item">
            <?= $this->Html->link(
                'Sin asignar <span class="count">' . $counts['sin_asignar'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'sin_asignar']],
                ['class' => $view === 'sin_asignar' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>

        <li class="list-group-item">
            <?= $this->Html->link(
                'Todas sin resolver <span class="count">' . $counts['todos_sin_resolver'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'todos_sin_resolver']],
                ['class' => $view === 'todos_sin_resolver' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>
    </ul>

    <!-- Status Views -->
    <h6 class="mt-4 mb-2 fs-6">Estados</h6>
    <ul class="list-group">
        <li class="list-group-item">
            <?= $this->Html->link(
                '<span class="badge me-2 shadow-sm" style="background-color: #ffc107; color: white;">●</span>Nuevos <span class="count">' . $counts['nuevos'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'nuevos']],
                ['class' => $view === 'nuevos' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>

        <li class="list-group-item">
            <?= $this->Html->link(
                '<span class="badge me-2 shadow-sm" style="background-color: #0dcaf0; color: white;">●</span>En Revisión <span class="count">' . $counts['en_revision'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'en_revision']],
                ['class' => $view === 'en_revision' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>

        <li class="list-group-item">
            <?= $this->Html->link(
                '<span class="badge me-2 shadow-sm" style="background-color: #198754; color: white;">●</span>Aprobados <span class="count">' . $counts['aprobados'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'aprobados']],
                ['class' => $view === 'aprobados' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>

        <li class="list-group-item">
            <?= $this->Html->link(
                '<span class="badge me-2 shadow-sm" style="background-color: #0d6efd; color: white;">●</span>En Proceso <span class="count">' . $counts['en_proceso'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'en_proceso']],
                ['class' => $view === 'en_proceso' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>

        <li class="list-group-item">
            <?= $this->Html->link(
                '<span class="badge me-2 shadow-sm" style="background-color: #28a745; color: white;">●</span>Completados <span class="count">' . $counts['completados'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'completados']],
                ['class' => $view === 'completados' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>

        <li class="list-group-item">
            <?= $this->Html->link(
                '<span class="badge me-2 shadow-sm" style="background-color: #dc3545; color: white;">●</span>Rechazados <span class="count">' . $counts['rechazados'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'rechazados']],
                ['class' => $view === 'rechazados' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>

        <li class="list-group-item">
            <?= $this->Html->link(
                '<span class="badge me-2 shadow-sm" style="background-color: #6c757d; color: white;">⇄</span>Convertidos <span class="count">' . $counts['convertidos'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'convertidos']],
                ['class' => $view === 'convertidos' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>

        <li class="list-group-item">
            <?= $this->Html->link(
                '<i class="bi bi-exclamation-triangle text-danger"></i> SLA Vencidos <span class="count">' . $counts['vencidos_sla'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'vencidos_sla']],
                ['class' => $view === 'vencidos_sla' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>
    </ul>
    </div>
</div>
