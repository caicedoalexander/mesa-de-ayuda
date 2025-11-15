<?php
/**
 * @var \App\View\AppView $this
 * @var array $settings
 */
$this->assign('title', 'Configuración del Sistema');
?>

<div class="py-3 px-3 overflow-auto scroll" style="max-width: 800px; margin: 0 auto; width: 100%;">
    <div class="text-center mb-5 border-bottom">
        <h2><i class="bi bi-gear-fill me-2"></i>Configuración del Sistema</h2>
    </div>

    <div class="mb-5">
        <h3 class="fw-normal">Configuración General</h3>

        <?= $this->Form->create(null, ['type' => 'post']) ?>

        <div class="form-group mb-5">
            <?= $this->Form->label('system_title', 'Título del Sistema') ?>
            <?= $this->Form->text('system_title', [
                'value' => $settings['system_title'] ?? 'Sistema de Soporte',
                'class' => 'form-control rounded-0 border-dark',
                'placeholder' => 'Sistema de Soporte'
            ]) ?>
        </div>

        <h3 class="mt-3 fw-normal">Configuración de Email (SMTP)</h3>

        <div class="form-group mb-2">
            <?= $this->Form->label('smtp_host', 'Servidor SMTP') ?>
            <?= $this->Form->text('smtp_host', [
                'value' => $settings['smtp_host'] ?? 'smtp.gmail.com',
                'class' => 'form-control rounded-0 border-dark',
                'placeholder' => 'smtp.gmail.com'
            ]) ?>
        </div>

        <div class="form-group mb-2">
            <?= $this->Form->label('smtp_port', 'Puerto SMTP') ?>
            <?= $this->Form->number('smtp_port', [
                'value' => $settings['smtp_port'] ?? '587',
                'class' => 'form-control rounded-0 border-dark',
                'placeholder' => '587'
            ]) ?>
        </div>

        <div class="form-group mb-2">
            <?= $this->Form->label('smtp_username', 'Usuario SMTP (Email)') ?>
            <?= $this->Form->email('smtp_username', [
                'value' => $settings['smtp_username'] ?? '',
                'class' => 'form-control rounded-0 border-dark',
                'placeholder' => 'correo@dominio.com'
            ]) ?>
        </div>

        <div class="form-group mb-2">
            <?= $this->Form->label('smtp_password', 'Contraseña SMTP') ?>
            <?= $this->Form->password('smtp_password', [
                'value' => $settings['smtp_password'] ?? '',
                'class' => 'form-control rounded-0 border-dark',
                'placeholder' => '••••••••'
            ]) ?>
            <small class="text-muted">Para Gmail, usa una contraseña de aplicación</small>
        </div>

        <div class="form-group mb-5">
            <?= $this->Form->label('smtp_encryption', 'Cifrado') ?>
            <?= $this->Form->select('smtp_encryption', [
                'tls' => 'TLS',
                'ssl' => 'SSL',
                'none' => 'Ninguno'
            ], [
                'value' => $settings['smtp_encryption'] ?? 'tls',
                'class' => 'form-control rounded-0 border-dark'
            ]) ?>
        </div>

        <h3 class="d-flex align-items-center mb-3 fw-normal"> <img src="<?= $this->Url->build('img/gmail.png') ?>" width="40" class="me-2">Configuración de Gmail API</h3>

        <div class="form-group mb-3">
            <?= $this->Form->label('gmail_check_interval', 'Intervalo de comprobación (minutos)') ?>
            <?= $this->Form->number('gmail_check_interval', [
                'value' => $settings['gmail_check_interval'] ?? '5',
                'class' => 'form-control rounded-0 border-dark',
                'placeholder' => '5',
                'min' => 1
            ]) ?>
            <small class="text-muted">Frecuencia con la que se revisan nuevos correos</small>
        </div>

        <div class="form-actions">
            <?= $this->Form->button('Guardar Configuración', ['class' => 'btn btn-primary rounded-0 shadow-sm']) ?>
        </div>

        <?= $this->Form->end() ?>
    </div>

    <div class="settings-form mt-3 mb-5">
        <h3 class="d-flex align-items-center mb-3 fw-normal"><img src="<?= $this->Url->build('img/google.png') ?>" width="40" class="me-2">Autorización de Google OAuth 2.0</h3>

        <?php if (!empty($settings['gmail_refresh_token'])): ?>
            <div class="text-success mb-3">
                ✓ Gmail está autorizado y conectado
            </div>

            <p class="d-flex gap-3">
                <?= $this->Html->link('Reconectar', ['action' => 'gmailAuth'], ['class' => 'btn btn-warning rounded-0 text-white shadow-sm']) ?>
                <?= $this->Html->link('Probar Conexión', ['action' => 'testGmail'], ['class' => 'btn btn-danger rounded-0']) ?>
            </p>
        <?php else: ?>
            <div>
                Gmail no está autorizado. Debes autorizar la aplicación para importar correos.
            </div>

            <p>
                <strong>Pasos para configurar Gmail:</strong>
            </p>
            <ol>
                <li>Asegúrate de tener el archivo <code>client_secret.json</code> en <code>config/google/</code></li>
                <li>Haz clic en el botón de abajo para autorizar la aplicación</li>
                <li>Inicia sesión con tu cuenta de Gmail</li>
                <li>Autoriza los permisos solicitados</li>
            </ol>

            <p>
                <?= $this->Html->link('Autorizar Gmail', ['action' => 'gmailAuth'], ['class' => 'btn btn-success rounded-0 shadow-sm']) ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="pb-5">
        <h3 class="fw-normal">Otras Opciones</h3>
        <div class="d-flex align-items-center gap-3">
            <?= $this->Html->link('Gestionar Plantillas de Email', ['action' => 'emailTemplates'], ['class' => 'btn btn-secondary rounded-0 shadow-sm']) ?>
            <?= $this->Html->link('Gestionar Usuarios', ['action' => 'users'], ['class' => 'btn btn-secondary rounded-0 shadow-sm']) ?>
            <?= $this->Html->link('Gestionar Etiquetas', ['action' => 'tags'], ['class' => 'btn btn-secondary rounded-0 shadow-sm']) ?>
        </div>
    </div>
</div>
