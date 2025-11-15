<?php
/**
 * @var \App\View\AppView $this
 * @var array $settings
 */
$this->assign('title', 'Editar Plantilla');
?>
<div class="py-3 px-3 overflow-auto scroll" style="max-width: 800px; margin: 0 auto; width: 100%;">
    <div class="page-header">
        <h1>Editar Plantilla de Email</h1>
        <p>Modifica la plantilla: <strong><?= h($template->template_key) ?></strong></p>
    </div>

    <?= $this->Flash->render() ?>

    <?= $this->Form->create($template) ?>
    <div class="form-card">
        <div class="form-section">
            <h3>Información General</h3>

            <div class="form-group">
                <?= $this->Form->label('template_key', 'Clave de la Plantilla') ?>
                <?= $this->Form->text('template_key', [
                    'class' => 'form-control',
                    'disabled' => true,
                    'title' => 'La clave no se puede modificar'
                ]) ?>
                <small>La clave identifica la plantilla y no se puede cambiar</small>
            </div>

            <div class="form-group">
                <?= $this->Form->label('subject', 'Asunto del Email') ?>
                <?= $this->Form->text('subject', [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: [Ticket #{{ticket_number}}] {{subject}}'
                ]) ?>
                <small>Puedes usar variables como {{ticket_number}}, {{subject}}, etc.</small>
            </div>

            <div class="form-group">
                <label>
                    <?= $this->Form->checkbox('is_active', ['id' => 'is_active']) ?>
                    Plantilla activa
                </label>
                <small>Si está desactivada, no se enviará este tipo de notificación</small>
            </div>
        </div>

        <div class="form-section">
            <h3>Contenido HTML</h3>

            <div class="form-group">
                <?= $this->Form->label('body_html', 'Cuerpo del Email (HTML)') ?>
                <?= $this->Form->textarea('body_html', [
                    'class' => 'form-control code-editor scroll',
                    'rows' => 20,
                    'style' => 'font-family: monospace; font-size: 13px;'
                ]) ?>
            </div>

            <div class="variables-help ">
                <h4>Variables Disponibles:</h4>
                <div class="variables-grid">
                    <?php
                    $vars = json_decode($template->available_variables, true);
                    if ($vars):
                        foreach ($vars as $var):
                    ?>
                        <code>{{<?= h($var) ?>}}</code>
                    <?php
                        endforeach;
                    endif;
                    ?>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <?= $this->Form->button('Guardar', [
                'class' => 'btn btn-success'
            ]) ?>
            <?= $this->Html->link('Cancelar', ['action' => 'emailTemplates'], [
                'class' => 'btn btn-secondary'
            ]) ?>
            <?= $this->Html->link('<i class="bi bi-eye"></i> Vista Previa', [
                'action' => 'previewTemplate',
                $template->id
            ], [
                'class' => 'btn btn-info',
                'target' => '_blank',
                'escape' => false
            ]) ?>
        </div>
    </div>
    <?= $this->Form->end() ?>
</div>

<style>
.content-wrapper {
    padding: 30px;
    max-width: 1200px;
    margin: 0 auto;
}

.page-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e0e0e0;
}

.page-header h1 {
    margin: 0 0 10px 0;
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
    margin: 0 0 20px 0;
    color: #333;
    font-size: 18px;
    font-weight: 600;
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

.form-control:disabled {
    background-color: #f5f5f5;
    cursor: not-allowed;
}

.code-editor {
    font-family: 'Courier New', monospace;
}

small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 12px;
}

.variables-help {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 6px;
    margin-top: 20px;
}

.variables-help h4 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 14px;
    font-weight: 600;
}

.variables-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.variables-grid code {
    background: white;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    color: #d63384;
    border: 1px solid #e0e0e0;
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

.btn-info {
    background-color: #17a2b8;
    color: white;
}

.btn-info:hover {
    background-color: #138496;
}
</style>
