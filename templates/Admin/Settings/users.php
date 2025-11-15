<?php
/**
 * @var \App\View\AppView $this
 * @var array $settings
 */
$this->assign('title', 'Usuarios');
?>
<div class="py-5" style="margin: 0 auto;">
    <div class="mb-5" style="max-width: 800px; margin: 0 auto; width: 100%">
        <div class="d-flex justify-content-between">
            <div>
                <h1><i class="bi bi-people"></i> Gestión de Usuarios</h1>
                <p>Administra los usuarios del sistema</p>
            </div>
            <div>
                <?= $this->Html->link(
                    'Nuevo Usuario',
                    ['action' => 'addUser'],
                    ['class' => 'btn btn-success rounded-0']
                ) ?>
            </div>
        </div>
    </div>

    <?= $this->Flash->render() ?>

    <div class="" style="max-width: 1000px; margin: 0 auto; width: 100%">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Organización</th>
                    <th>Estado</th>
                    <th>Registro</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="align-middle">
                                <div class="d-flex gap-2 align-items-center">
                                    <div class="bg-secondary text-white fw-bold rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                        <?= strtoupper(substr($user->name, 0, 1)) ?>
                                    </div>
                                    <strong class="lh-1"><?= h($user->name) ?></strong>
                                </div>
                            </td>
                            <td class="align-middle"><?= h($user->email) ?></td>
                            <td class="align-middle">
                                <span class="">
                                    <?php
                                    $roles = [
                                        'admin' => 'Administrador',
                                        'agent' => 'Agente',
                                        'compras' => 'Compras'
                                    ];
                                    echo $roles[$user->role] ?? $user->role;
                                    ?>
                                </span>
                            </td>
                            <td class="align-middle">
                                <?= $user->organization ? h($user->organization->name) : '<em>Sin organización</em>' ?>
                            </td>
                            <td class="align-middle">
                                <span class="status-badge <?= $user->is_active ? 'active' : 'inactive' ?>">
                                    <?= $user->is_active ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td class="align-middle"><?= $user->created->format('d/m/Y') ?></td>
                            <td class="align-middle">
                                <div class="action-buttons">
                                    <?= $this->Html->link(
                                        '✏️',
                                        ['action' => 'editUser', $user->id],
                                        ['class' => 'btn p-1 btn-outline-secondary', 'title' => 'Editar']
                                    ) ?>
                                    <?php if ($user->is_active): ?>
                                        <?= $this->Form->postLink(
                                            '🚫',
                                            ['action' => 'deactivateUser', $user->id],
                                            [
                                                'class' => 'btn btn-outline-danger p-1',
                                                'title' => 'Desactivar',
                                                'confirm' => '¿Desactivar a ' . $user->name . '?'
                                            ]
                                        ) ?>
                                    <?php else: ?>
                                        <?= $this->Form->postLink(
                                            '✓',
                                            ['action' => 'activateUser', $user->id],
                                            [
                                                'class' => 'btn p-1',
                                                'title' => 'Activar'
                                            ]
                                        ) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            No hay usuarios registrados.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($users->count() > 0): ?>
        <div class="pagination-wrapper">
            <?= $this->element('pagination') ?>
        </div>
    <?php endif; ?>
</div>