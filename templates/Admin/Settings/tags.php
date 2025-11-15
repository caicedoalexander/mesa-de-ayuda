<?php
/**
 * @var \App\View\AppView $this
 * @var array $settings
 */
$this->assign('title', 'Gestión de Etiquetas');
?>
<div class="py-3 px-3 overflow-auto scroll" style="max-width: 800px; margin: 0 auto; width: 100%;">
    <div class="page-header">
        <div class="header-content">
            <div>
                <h1>Gestión de Etiquetas</h1>
                <p>Administra las etiquetas para organizar tickets</p>
            </div>
            <div>
                <?= $this->Html->link(
                    'Nueva Etiqueta',
                    ['action' => 'addTag'],
                    ['class' => 'btn btn-primary']
                ) ?>
            </div>
        </div>
    </div>

    <?= $this->Flash->render() ?>

    <div class="tags-container">
        <?php if (!empty($tags)): ?>
            <div class="tags-grid">
                <?php foreach ($tags as $tag): ?>
                    <div class="tag-card">
                        <div class="tag-color-bar" style="background-color: <?= h($tag->color) ?>"></div>
                        <div class="tag-content">
                            <div class="tag-header">
                                <h3>
                                    <span class="tag-badge" style="background-color: <?= h($tag->color) ?>">
                                        <?= h($tag->name) ?>
                                    </span>
                                </h3>
                                <div class="tag-stats">
                                    <span class="ticket-count">
                                        <?= $tag->ticket_count ?? 0 ?> tickets
                                    </span>
                                </div>
                            </div>

                            <?php if ($tag->description): ?>
                                <p class="tag-description"><?= h($tag->description) ?></p>
                            <?php endif; ?>

                            <div class="tag-actions">
                                <?= $this->Html->link(
                                    'Editar',
                                    ['action' => 'editTag', $tag->id],
                                    ['class' => 'btn-action btn-edit']
                                ) ?>
                                <?= $this->Form->postLink(
                                    'Eliminar',
                                    ['action' => 'deleteTag', $tag->id],
                                    [
                                        'class' => 'btn-action btn-delete',
                                        'confirm' => '¿Estás seguro de eliminar la etiqueta "' . $tag->name . '"?'
                                    ]
                                ) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>No hay etiquetas creadas</h3>
                <p>Las etiquetas te ayudan a organizar y categorizar tus tickets.</p>
                <?= $this->Html->link(
                    'Crear primera etiqueta',
                    ['action' => 'addTag'],
                    ['class' => 'btn btn-primary']
                ) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.content-wrapper {
    padding: 30px;
    max-width: 1400px;
    margin: 0 auto;
}

.page-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e0e0e0;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
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

.tags-container {
    min-height: 400px;
}

.tags-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.tag-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s;
}

.tag-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.tag-color-bar {
    height: 4px;
    width: 100%;
}

.tag-content {
    padding: 20px;
}

.tag-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.tag-header h3 {
    margin: 0;
    font-size: 16px;
}

.tag-badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    color: white;
    font-size: 14px;
    font-weight: 600;
}

.tag-stats {
    display: flex;
    align-items: center;
}

.ticket-count {
    background: #f0f0f0;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    color: #666;
}

.tag-description {
    margin: 0 0 15px 0;
    color: #666;
    font-size: 14px;
    line-height: 1.5;
}

.tag-actions {
    display: flex;
    gap: 10px;
    padding-top: 15px;
    border-top: 1px solid #f0f0f0;
}

.btn-action {
    flex: 1;
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    font-size: 13px;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    display: inline-block;
    text-align: center;
}

.btn-edit {
    background-color: #0066cc;
    color: white;
}

.btn-edit:hover {
    background-color: #0052a3;
}

.btn-delete {
    background-color: #dc3545;
    color: white;
}

.btn-delete:hover {
    background-color: #c82333;
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

.empty-state {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border: 2px dashed #e0e0e0;
    border-radius: 8px;
}

.empty-icon {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h3 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 24px;
}

.empty-state p {
    margin: 0 0 30px 0;
    color: #666;
    font-size: 16px;
}
</style>
