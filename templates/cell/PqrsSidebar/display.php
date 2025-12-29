<?php
/**
 * @var \App\View\AppView $this
 * @var array<string, int> $counts
 * @var array<string, int> $typeCounts
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
        <li class="list-group-item">
            <?= $this->Html->link(
                'Mis PQRS <span class="count">' . $counts['mis_pqrs'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'mis_pqrs']],
                ['class' => $view === 'mis_pqrs' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>
        
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
                '<span class="badge me-2 shadow-sm" style="background-color: #ffc107; color: white;">●</span>Nuevas <span class="count">' . $counts['nuevas'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'nuevas']],
                ['class' => $view === 'nuevas' ? 'active' : '', 'escape' => false]
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
                '<span class="badge me-2 shadow-sm" style="background-color: #0d6efd; color: white;">●</span>En Proceso <span class="count">' . $counts['en_proceso'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'en_proceso']],
                ['class' => $view === 'en_proceso' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>

        <li class="list-group-item">
            <?= $this->Html->link(
                '<span class="badge me-2 shadow-sm" style="background-color: #198754; color: white;">●</span>Resueltas <span class="count">' . $counts['resueltas'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'resueltas']],
                ['class' => $view === 'resueltas' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>

        <li class="list-group-item">
            <?= $this->Html->link(
                '<span class="badge me-2 shadow-sm" style="background-color: #6c757d; color: white;">●</span>Cerradas <span class="count">' . $counts['cerradas'] . '</span>',
                ['action' => 'index', '?' => ['view' => 'cerradas']],
                ['class' => $view === 'cerradas' ? 'active' : '', 'escape' => false]
            ) ?>
        </li>
    </ul>

    <!-- Type Views -->
    <h6 class="mt-4 mb-2 fs-6">Tipos</h6>
    <ul class="list-group">
        <li class="list-group-item">
            <?= $this->Html->link(
                '<i class="bi bi-file-earmark-text"></i> Peticiones <span class="count">' . $typeCounts['peticion'] . '</span>',
                ['action' => 'index', '?' => ['filter_type' => 'peticion']],
                ['escape' => false]
            ) ?>
        </li>

        <li class="list-group-item">
            <?= $this->Html->link(
                '<i class="bi bi-exclamation-triangle"></i> Quejas <span class="count">' . $typeCounts['queja'] . '</span>',
                ['action' => 'index', '?' => ['filter_type' => 'queja']],
                ['escape' => false]
            ) ?>
        </li>

        <li class="list-group-item">
            <?= $this->Html->link(
                '<i class="bi bi-exclamation-circle"></i> Reclamos <span class="count">' . $typeCounts['reclamo'] . '</span>',
                ['action' => 'index', '?' => ['filter_type' => 'reclamo']],
                ['escape' => false]
            ) ?>
        </li>

        <li class="list-group-item">
            <?= $this->Html->link(
                '<i class="bi bi-lightbulb"></i> Sugerencias <span class="count">' . $typeCounts['sugerencia'] . '</span>',
                ['action' => 'index', '?' => ['filter_type' => 'sugerencia']],
                ['escape' => false]
            ) ?>
        </li>
    </ul>
    </div>
</div>
