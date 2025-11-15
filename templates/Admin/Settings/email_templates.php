<?php
/**
 * @var \App\View\AppView $this
 * @var array $settings
 */
$this->assign('title', 'Plantillas de Email');
?>
<div class="py-3 px-3 overflow-auto scroll" style="max-width: 800px; margin: 0 auto; width: 100%;">
    <div class="">
        <h1>Plantillas de Email</h1>
        <p>Gestiona las plantillas de notificaciones que se envían automáticamente</p>
    </div>

    <?= $this->Flash->render() ?>

    <div class="templates-list pb-5">
        <?php if (!empty($templates)): ?>
            <?php foreach ($templates as $template): ?>
                <div class="template-card rounded-0">
                    <div class="template-header">
                        <h3>Nombre: <?= h($template->template_key) ?></h3>
                    </div>

                    <div class="template-info">
                        <p><strong>Asunto:</strong> <?= h($template->subject) ?></p>
                        <p><strong>Variables disponibles:</strong></p>
                        <div class="variables-list">
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

                    <div class="template-actions justify-content-end">
                        <?= $this->Html->link(
                            'Editar',
                            ['action' => 'editTemplate', $template->id],
                            ['class' => 'btn btn-primary rounded-0']
                        ) ?>
                        <?= $this->Html->link(
                            'Previsualizar',
                            ['action' => 'previewTemplate', $template->id],
                            ['class' => 'btn btn-outline-secondary border border-secondary rounded-0', 'target' => '_blank']
                        ) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <p>No hay plantillas configuradas.</p>
            </div>
        <?php endif; ?>
    </div>
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

.templates-list {
    display: grid;
    gap: 20px;
}

.template-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    transition: box-shadow 0.3s;
}

.template-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.template-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.template-header h3 {
    margin: 0;
    color: #333;
    font-size: 18px;
    font-weight: 600;
}

.template-info {
    margin-bottom: 20px;
}

.template-info p {
    margin: 0 0 10px 0;
    color: #555;
}

.variables-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.variables-list code {
    background: #f5f5f5;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    color: #d63384;
    font-family: 'Courier New', monospace;
}

.template-actions {
    display: flex;
    gap: 10px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    color: #999;
}
</style>
