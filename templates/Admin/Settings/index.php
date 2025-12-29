<?php
/**
 * @var \App\View\AppView $this
 * @var array $settings
 */
$this->assign('title', 'Configuración');
?>

<div class="p-5" style="max-width: 800px; margin: 0 auto; width: 100%;">
    <div class="mb-4">
        <h3><i class="bi bi-gear-fill"></i> Configuración del Sistema</h3>
    </div>

    <div class="mb-5 bg-white p-4 rounded shadow-sm">
        <h3 class="fw-normal">Configuración General</h3>

        <?= $this->Form->create(null, ['type' => 'post']) ?>

        <div class="form-group mb-5">
            <?= $this->Form->label('system_title', 'Título del Sistema', ['class' => 'form-label']) ?>
            <?= $this->Form->text('system_title', [
                'value' => $settings['system_title'] ?? 'Sistema de Soporte',
                'class' => 'form-control form-control-plaintext px-3 border shadow-none',
                'placeholder' => 'Sistema de Soporte'
            ]) ?>
        </div>

        <h3 class="d-flex align-items-center mb-3 mt-4 fw-normal"> <img src="<?= $this->Url->build('img/email.png') ?>"
                width="40" class="me-2">Configuración de Gmail API</h3>

        <div class="form-group mb-3">
            <?= $this->Form->label('gmail_check_interval', 'Intervalo de comprobación (minutos)', ['class' => 'form-label']) ?>
            <?= $this->Form->number('gmail_check_interval', [
                'value' => $settings['gmail_check_interval'] ?? '5',
                'class' => 'form-control form-control-plaintext px-3 border shadow-none',
                'placeholder' => '5',
                'min' => 1
            ]) ?>
            <small class="text-muted fw-light">Frecuencia con la que se revisan nuevos correos</small>
        </div>

        <div class="form-actions">
            <?= $this->Form->button('<i class="bi bi-arrow-up-circle"></i> Guardar configuración de Gmail', ['class' => 'btn btn-success shadow-sm', 'escapeTitle' => false]) ?>
        </div>

        <?= $this->Form->end() ?>
    </div>

    <div class="mt-3 mb-5 bg-white p-4 rounded shadow-sm">
        <h3 class="d-flex align-items-center mb-3 fw-normal"><img src="<?= $this->Url->build('img/google.png') ?>"
                width="40" class="me-2">Autorización de Google OAuth 2.0</h3>

        <?php if (!empty($settings['gmail_refresh_token'])): ?>
            <div class="text-success mb-3 fw-bold">
                ✓ Gmail está autorizado y conectado
            </div>

            <p class="d-flex gap-3">
                <?= $this->Html->link('<i class="bi bi-arrow-repeat"></i> Reconectar', ['action' => 'gmailAuth'], ['class' => 'btn btn-warning text-white shadow-sm', 'escapeTitle' => false]) ?>
                <?= $this->Html->link('<i class="bi bi-play-circle"></i> Probar Conexión', ['action' => 'testGmail'], ['class' => 'btn btn-danger shadow-sm', 'escapeTitle' => false]) ?>
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

    <!-- WhatsApp API Configuration -->
    <div class="mt-5 mb-5 bg-white p-4 rounded shadow-sm">
        <h3 class="d-flex align-items-center mb-3 fw-normal">
            <i class="bi bi-whatsapp text-success me-2" style="font-size: 2rem;"></i>
            Configuración de WhatsApp
        </h3>

        <?= $this->Form->create(null, ['type' => 'post', 'url' => ['action' => 'index']]) ?>

        <div class="form-group mb-3">
            <div class="form-check">
                <?= $this->Form->checkbox('whatsapp_enabled', [
                    'checked' => ($settings['whatsapp_enabled'] ?? '0') === '1',
                    'value' => '1',
                    'class' => 'form-check-input',
                    'id' => 'whatsapp_enabled'
                ]) ?>
                <?= $this->Form->label('whatsapp_enabled', 'Habilitar notificaciones de WhatsApp', [
                    'class' => 'form-check-label fw-bold'
                ]) ?>
            </div>
            <small class="text-muted">Enviar notificaciones automáticas por WhatsApp cuando se crean/actualizan
                tickets</small>
        </div>

        <div id="whatsapp-config-fields"
            style="display: <?= (($settings['whatsapp_enabled'] ?? '0') === '1') ? 'block' : 'none' ?>;">
            <div class="form-group mb-2">
                <?= $this->Form->label('whatsapp_api_url', 'URL de Evolution API', ['class' => 'form-label']) ?>
                <?= $this->Form->text('whatsapp_api_url', [
                    'value' => $settings['whatsapp_api_url'] ?? 'https://n8n-evolution-api.jx7zng.easypanel.host',
                    'class' => 'form-control form-control-plaintext px-3 border shadow-none',
                    'placeholder' => 'https://your-evolution-api.com'
                ]) ?>
                <small class="text-muted">URL base de tu instancia de Evolution API</small>
            </div>

            <div class="form-group mb-2">
                <?= $this->Form->label('whatsapp_api_key', 'API Key', ['class' => 'form-label']) ?>
                <?= $this->Form->password('whatsapp_api_key', [
                    'value' => $settings['whatsapp_api_key'] ?? '',
                    'class' => 'form-control form-control-plaintext px-3 border shadow-none',
                    'placeholder' => '••••••••••••••••'
                ]) ?>
                <small class="text-muted">Clave de autenticación de Evolution API</small>
            </div>

            <div class="form-group mb-2">
                <?= $this->Form->label('whatsapp_instance_name', 'Nombre de Instancia', ['class' => 'form-label']) ?>
                <?= $this->Form->text('whatsapp_instance_name', [
                    'value' => $settings['whatsapp_instance_name'] ?? 'AlexBot',
                    'class' => 'form-control form-control-plaintext px-3 border shadow-none',
                    'placeholder' => 'AlexBot'
                ]) ?>
                <small class="text-muted">Nombre de tu instancia de WhatsApp en Evolution API</small>
            </div>

            <div class="form-group mb-3">
                <?= $this->Form->label('whatsapp_tickets_number', 'Número de alerta de tickets', ['class' => 'form-label']) ?>
                <?= $this->Form->text('whatsapp_tickets_number', [
                    'value' => $settings['whatsapp_tickets_number'] ?? '',
                    'class' => 'form-control form-control-plaintext px-3 border shadow-none mb-3',
                    'placeholder' => '5511999999999@s.whatsapp.net'
                ]) ?>
                <?= $this->Form->label('whatsapp_compras_number', 'Número de alerta de compras', ['class' => 'form-label']) ?>
                <?= $this->Form->text('whatsapp_compras_number', [
                    'value' => $settings['whatsapp_compras_number'] ?? '',
                    'class' => 'form-control form-control-plaintext px-3 border shadow-none mb-3',
                    'placeholder' => '5511999999999@s.whatsapp.net'
                ]) ?>
                <?= $this->Form->label('whatsapp_pqrs_number', 'Número de alerta de pqrs', ['class' => 'form-label']) ?>
                <?= $this->Form->text('whatsapp_pqrs_number', [
                    'value' => $settings['whatsapp_pqrs_number'] ?? '',
                    'class' => 'form-control form-control-plaintext px-3 border shadow-none mb-3',
                    'placeholder' => '5511999999999@s.whatsapp.net'
                ]) ?>
            </div>

            <div class="alert alert-success">
                <i class="bi bi-info-circle-fill"></i>
                <strong>Formatos de número:</strong>
                <ul class="mb-0 mt-2">
                    <li><strong>Grupo:</strong> <code>ID@g.us</code> (ej: 120363424575102342@g.us)</li>
                    <li><strong>Individual:</strong> <code>código+número@s.whatsapp.net</code> (ej:
                        5219991234567@s.whatsapp.net)</li>
                </ul>
            </div>
        </div>

        <div class="form-actions d-flex gap-2">
            <?= $this->Form->button('<i class="bi bi-arrow-up-circle"></i> Guardar configuración de WhatsApp', [
                'class' => 'btn btn-success shadow-sm',
                'type' => 'submit',
                'escapeTitle' => false
            ]) ?>

            <?php if (($settings['whatsapp_enabled'] ?? '0') === '1'): ?>
                <?= $this->Html->link(
                    '<i class="bi bi-check-circle me-1"></i> Probar Conexión',
                    ['action' => 'testWhatsapp'],
                    [
                        'class' => 'btn btn-outline-success shadow-sm',
                        'escape' => false,
                        'id' => 'test-whatsapp-btn'
                    ]
                ) ?>
            <?php endif; ?>
        </div>

        <?= $this->Form->end() ?>
    </div>

    <!-- n8n AI Integration Configuration -->
    <div class="mt-5 mb-5 bg-white p-4 rounded shadow-sm">
        <h3 class="d-flex align-items-center mb-3 fw-normal">
            <img src="<?= $this->Url->build('img/n8n.png') ?>" width="40" class="me-2">
            Configuración de n8n
        </h3>

        <?= $this->Form->create(null, ['type' => 'post', 'url' => ['action' => 'index']]) ?>

        <div class="form-group mb-3">
            <div class="form-check">
                <?= $this->Form->checkbox('n8n_enabled', [
                    'checked' => ($settings['n8n_enabled'] ?? '0') === '1',
                    'value' => '1',
                    'class' => 'form-check-input',
                    'id' => 'n8n_enabled'
                ]) ?>
                <?= $this->Form->label('n8n_enabled', 'Habilitar integración con n8n', [
                    'class' => 'form-check-label fw-bold'
                ]) ?>
            </div>
            <small class="text-muted">Enviar tickets a n8n para asignación automática de tags con IA</small>
        </div>

        <div id="n8n-config-fields"
            style="display: <?= (($settings['n8n_enabled'] ?? '0') === '1') ? 'block' : 'none' ?>;">
            <div class="form-group mb-3">
                <?= $this->Form->label('n8n_webhook_url', 'URL del Webhook de n8n', ['class' => 'form-label']) ?>
                <?= $this->Form->text('n8n_webhook_url', [
                    'value' => $settings['n8n_webhook_url'] ?? '',
                    'class' => 'form-control form-control-plaintext px-3 border shadow-none',
                    'placeholder' => 'https://tu-n8n.com/webhook/ai-tags'
                ]) ?>
                <small class="text-muted">URL completa del webhook que recibirá los datos del ticket</small>
            </div>

            <div class="form-group mb-3">
                <?= $this->Form->label('n8n_api_key', 'API Key (Opcional)', ['class' => 'form-label']) ?>
                <?= $this->Form->password('n8n_api_key', [
                    'value' => $settings['n8n_api_key'] ?? '',
                    'class' => 'form-control form-control-plaintext px-3 border shadow-none',
                    'placeholder' => '••••••••••••••••'
                ]) ?>
                <small class="text-muted">Clave de autenticación para el webhook (opcional)</small>
            </div>

            <div class="form-group mb-3">
                <div class="form-check">
                    <?= $this->Form->checkbox('n8n_send_tags_list', [
                        'checked' => ($settings['n8n_send_tags_list'] ?? '1') === '1',
                        'value' => '1',
                        'class' => 'form-check-input',
                        'id' => 'n8n_send_tags_list'
                    ]) ?>
                    <?= $this->Form->label('n8n_send_tags_list', 'Enviar lista de tags disponibles', [
                        'class' => 'form-check-label'
                    ]) ?>
                </div>
                <small class="text-muted">Incluir la lista completa de tags en el payload del webhook</small>
            </div>

            <div class="form-group mb-3">
                <?= $this->Form->label('n8n_timeout', 'Timeout (segundos)', ['class' => 'form-label']) ?>
                <?= $this->Form->number('n8n_timeout', [
                    'value' => $settings['n8n_timeout'] ?? '10',
                    'class' => 'form-control form-control-plaintext px-3 border shadow-none',
                    'placeholder' => '10',
                    'min' => 1,
                    'max' => 60
                ]) ?>
                <small class="text-muted">Tiempo máximo de espera para la respuesta del webhook</small>
            </div>

            <div class="alert alert-success">
                <i class="bi bi-info-circle-fill"></i>
                <strong>Flujo de integración:</strong>
                <ol class="mb-0 mt-2 small">
                    <li>Se crea un ticket desde Gmail</li>
                    <li>El sistema envía los datos del ticket a n8n vía webhook</li>
                    <li>n8n procesa el ticket con IA para sugerir tags</li>
                    <li>n8n actualiza los tags directamente en la base de datos</li>
                </ol>
            </div>
        </div>

        <div class="form-actions d-flex gap-2">
            <?= $this->Form->button('<i class="bi bi-arrow-up-circle"></i> Guardar Configuración de n8n', [
                'class' => 'btn btn-success shadow-sm',
                'type' => 'submit',
                'escapeTitle' => false
            ]) ?>

            <?php if (($settings['n8n_enabled'] ?? '0') === '1'): ?>
                <?= $this->Html->link(
                    '<i class="bi bi-check-circle me-1"></i> Probar Conexión',
                    ['action' => 'testN8n'],
                    [
                        'class' => 'btn btn-outline-primary shadow-sm',
                        'escape' => false,
                        'id' => 'test-n8n-btn'
                    ]
                ) ?>
            <?php endif; ?>
        </div>

        <?= $this->Form->end() ?>
    </div>

    <div class="pb-5">
        <h3 class="fw-normal">Otras Opciones</h3>
        <div class="d-flex align-items-center gap-3">
            <?= $this->Html->link('<i class="bi bi-envelope"></i> Plantillas', ['action' => 'emailTemplates'], ['class' => 'btn btn-secondary shadow-sm', 'escapeTitle' => false]) ?>
            <?= $this->Html->link('<i class="bi bi-people"></i> Usuarios', ['action' => 'users'], ['class' => 'btn btn-secondary shadow-sm', 'escapeTitle' => false]) ?>
            <?= $this->Html->link('<i class="bi bi-building"></i> Organizaciones', ['action' => 'organizations'], ['class' => 'btn btn-secondary shadow-sm', 'escapeTitle' => false]) ?>
            <?= $this->Html->link('<i class="bi bi-tags"></i> Etiquetas', ['action' => 'tags'], ['class' => 'btn btn-secondary shadow-sm', 'escapeTitle' => false]) ?>
        </div>
    </div>
</div>

<script>
    // Toggle WhatsApp config fields visibility
    document.getElementById('whatsapp_enabled').addEventListener('change', function () {
        const configFields = document.getElementById('whatsapp-config-fields');
        configFields.style.display = this.checked ? 'block' : 'none';
    });

    // Toggle n8n config fields visibility
    document.getElementById('n8n_enabled').addEventListener('change', function () {
        const configFields = document.getElementById('n8n-config-fields');
        configFields.style.display = this.checked ? 'block' : 'none';
    });

    // Test WhatsApp connection
    <?php if (($settings['whatsapp_enabled'] ?? '0') === '1'): ?>
        document.getElementById('test-whatsapp-btn')?.addEventListener('click', function (e) {
            e.preventDefault();
            const btn = this;
            const originalText = btn.innerHTML;

            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Probando...';
            btn.classList.add('disabled');

            fetch(btn.href)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ ' + data.message);
                    } else {
                        alert('❌ ' + data.message);
                    }
                })
                .catch(error => {
                    alert('❌ Error al probar la conexión: ' + error.message);
                })
                .finally(() => {
                    btn.innerHTML = originalText;
                    btn.classList.remove('disabled');
                });
        });
    <?php endif; ?>

    // Test n8n connection
    <?php if (($settings['n8n_enabled'] ?? '0') === '1'): ?>
        document.getElementById('test-n8n-btn')?.addEventListener('click', function (e) {
            e.preventDefault();
            const btn = this;
            const originalText = btn.innerHTML;

            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Probando...';
            btn.classList.add('disabled');

            fetch(btn.href)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ ' + data.message);
                    } else {
                        alert('❌ ' + data.message);
                    }
                })
                .catch(error => {
                    alert('❌ Error al probar la conexión: ' + error.message);
                })
                .finally(() => {
                    btn.innerHTML = originalText;
                    btn.classList.remove('disabled');
                });
        });
    <?php endif; ?>

    // Spinner: Mostrar al guardar configuración
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                const buttonText = submitBtn.textContent.trim();
                let message = 'Guardando configuración...';

                if (buttonText.includes('Autorizar')) {
                    message = 'Autorizando con Google...';
                } else if (buttonText.includes('Guardar')) {
                    message = 'Guardando configuración...';
                } else if (buttonText.includes('Usuario')) {
                    message = 'Guardando usuario...';
                } else if (buttonText.includes('Etiqueta')) {
                    message = 'Guardando etiqueta...';
                } else if (buttonText.includes('Plantilla')) {
                    message = 'Guardando plantilla...';
                }

                LoadingSpinner.show(message);
            }
        });
    });
</script>