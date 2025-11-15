<?php
/**
 * @var \App\View\AppView $this
 * @var array $settings
 */
$this->assign('title', 'Editar Etiqueta');
?>
<div class="py-3 px-3 overflow-auto scroll" style="max-width: 800px; margin: 0 auto; width: 100%;">
    <div class="page-header">
        <h1>✏️ Editar Etiqueta</h1>
        <p>Modificar información de: <strong><?= h($tag->name) ?></strong></p>
    </div>

    <?= $this->Flash->render() ?>

    <?= $this->Form->create($tag) ?>
    <div class="form-card">
        <div class="form-section">
            <h3>Información de la Etiqueta</h3>

            <div class="form-row">
                <div class="form-group">
                    <?= $this->Form->label('name', 'Nombre *') ?>
                    <?= $this->Form->text('name', [
                        'class' => 'form-control',
                        'placeholder' => 'Ej: Urgente, Bug, Pregunta'
                    ]) ?>
                    <small>Nombre corto y descriptivo para la etiqueta</small>
                </div>

                <div class="form-group">
                    <?= $this->Form->label('color', 'Color *') ?>
                    <div class="color-input-wrapper">
                        <?= $this->Form->color('color', [
                            'class' => 'color-picker',
                            'id' => 'tag-color'
                        ]) ?>
                        <input type="text" id="color-hex" class="form-control color-hex-input"
                               value="<?= h($tag->color) ?>" readonly>
                    </div>
                    <small>Color para identificar visualmente la etiqueta</small>
                </div>
            </div>

            <div class="form-group">
                <?= $this->Form->label('description', 'Descripción') ?>
                <?= $this->Form->textarea('description', [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Describe cuándo usar esta etiqueta...'
                ]) ?>
                <small>Ayuda a otros usuarios a entender cuándo aplicar esta etiqueta</small>
            </div>
        </div>

        <div class="form-section">
            <h3>Vista Previa</h3>
            <div class="tag-preview">
                <span class="preview-badge" id="preview-badge" style="background-color: <?= h($tag->color) ?>">
                    <span id="preview-text"><?= h($tag->name) ?></span>
                </span>
            </div>
        </div>

        <div class="form-actions">
            <?= $this->Form->button('💾 Guardar Cambios', [
                'class' => 'btn btn-primary'
            ]) ?>
            <?= $this->Html->link('Cancelar', ['action' => 'tags'], [
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
    margin: 0 0 20px 0;
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

.color-input-wrapper {
    display: flex;
    gap: 10px;
    align-items: center;
}

.color-picker {
    width: 60px;
    height: 42px;
    border: 1px solid #ddd;
    border-radius: 6px;
    cursor: pointer;
    padding: 2px;
}

.color-hex-input {
    flex: 1;
    font-family: 'Courier New', monospace;
    text-transform: uppercase;
}

small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 12px;
}

.tag-preview {
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 30px;
    text-align: center;
}

.preview-badge {
    display: inline-block;
    padding: 8px 18px;
    border-radius: 20px;
    color: white;
    font-size: 16px;
    font-weight: 600;
    transition: all 0.3s;
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const colorPicker = document.getElementById('tag-color');
    const colorHex = document.getElementById('color-hex');
    const previewBadge = document.getElementById('preview-badge');
    const previewText = document.getElementById('preview-text');
    const nameInput = document.querySelector('input[name="name"]');

    // Update preview when color changes
    colorPicker.addEventListener('input', function() {
        const color = this.value;
        colorHex.value = color;
        previewBadge.style.backgroundColor = color;
    });

    // Update preview when name changes
    nameInput.addEventListener('input', function() {
        previewText.textContent = this.value || 'Nombre de etiqueta';
    });
});
</script>
