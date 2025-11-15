<?php
/**
 * @var \App\View\AppView $this
 * @var array $settings
 */
$this->assign('title', 'Añadir Usuario');
?>
<div class="py-3 px-3 overflow-auto scroll" style="max-width: 800px; margin: 0 auto; width: 100%;">
    <div class="page-header">
        <h1>➕ Nuevo Usuario</h1>
        <p>Crear un nuevo usuario en el sistema</p>
    </div>

    <?= $this->Flash->render() ?>

    <?= $this->Form->create($user) ?>
    <div class="form-card">
        <div class="form-section">
            <h3>Información Personal</h3>

            <div class="form-row">
                <div class="form-group">
                    <?= $this->Form->label('name', 'Nombre Completo *') ?>
                    <?= $this->Form->text('name', [
                        'class' => 'form-control',
                        'placeholder' => 'Ej: Juan Pérez',
                        'required' => true
                    ]) ?>
                </div>

                <div class="form-group">
                    <?= $this->Form->label('email', 'Correo Electrónico *') ?>
                    <?= $this->Form->email('email', [
                        'class' => 'form-control',
                        'placeholder' => 'ejemplo@correo.com',
                        'required' => true
                    ]) ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <?= $this->Form->label('phone', 'Teléfono') ?>
                    <?= $this->Form->text('phone', [
                        'class' => 'form-control',
                        'placeholder' => '+57 300 123 4567'
                    ]) ?>
                </div>

                <div class="form-group">
                    <?= $this->Form->label('organization_id', 'Organización') ?>
                    <?= $this->Form->select('organization_id', $organizations, [
                        'class' => 'form-control',
                        'empty' => '-- Seleccionar --'
                    ]) ?>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>Configuración de Cuenta</h3>

            <div class="form-row">
                <div class="form-group">
                    <?= $this->Form->label('role', 'Rol *') ?>
                    <?= $this->Form->select('role', [
                        'admin' => 'Administrador',
                        'agent' => 'Agente',
                        'requester' => 'Solicitante',
                        'compras' => 'Compras'
                    ], [
                        'class' => 'form-control',
                        'required' => true
                    ]) ?>
                    <small>Define los permisos del usuario</small>
                </div>

                <div class="form-group">
                    <label>
                        <?= $this->Form->checkbox('is_active', [
                            'id' => 'is_active',
                            'checked' => true
                        ]) ?>
                        Cuenta activa
                    </label>
                    <small>Los usuarios inactivos no pueden iniciar sesión</small>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>Contraseña</h3>

            <div class="form-row">
                <div class="form-group">
                    <?= $this->Form->label('password', 'Contraseña *') ?>
                    <?= $this->Form->password('password', [
                        'class' => 'form-control',
                        'required' => true,
                        'autocomplete' => 'new-password'
                    ]) ?>
                    <small>Mínimo 6 caracteres</small>
                </div>

                <div class="form-group">
                    <?= $this->Form->label('confirm_password', 'Confirmar Contraseña *') ?>
                    <?= $this->Form->password('confirm_password', [
                        'class' => 'form-control',
                        'required' => true,
                        'autocomplete' => 'new-password'
                    ]) ?>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <?= $this->Form->button('💾 Crear Usuario', [
                'class' => 'btn btn-primary'
            ]) ?>
            <?= $this->Html->link('Cancelar', ['action' => 'users'], [
                'class' => 'btn btn-secondary'
            ]) ?>
        </div>
    </div>
    <?= $this->Form->end() ?>
</div>

<style>
.content-wrapper {
    padding: 30px;
    max-width: 900px;
    margin: 0 auto;
}

.page-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e0e0e0;
}

.page-header h1 {
    margin: 0 0 5px 0;
    color: #333;
    font-size: 28px;
}

.page-header p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.form-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 30px;
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid #f0f0f0;
}

.form-section:last-of-type {
    border-bottom: none;
}

.form-section h3 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 18px;
    font-weight: 600;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #333;
    font-weight: 500;
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: #0066cc;
    box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
}

small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 12px;
}

.form-actions {
    display: flex;
    gap: 10px;
    padding-top: 20px;
}

.btn {
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    display: inline-block;
}

.btn-primary {
    background-color: #0066cc;
    color: white;
}

.btn-primary:hover {
    background-color: #0052a3;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #5a6268;
}
</style>
