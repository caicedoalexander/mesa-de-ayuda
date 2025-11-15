<?php
/**
 * Pagination element
 * Displays pagination links for paginated results
 */
?>
<?php if ($this->Paginator->hasPrev() || $this->Paginator->hasNext()): ?>
<div class="pagination">
    <div class="pagination-info">
        <?= $this->Paginator->counter('Mostrando {{start}} - {{end}} de {{count}} registros') ?>
    </div>

    <ul class="pagination-links">
        <?= $this->Paginator->first('« Primera', ['escape' => false]) ?>
        <?= $this->Paginator->prev('‹ Anterior', ['escape' => false]) ?>
        <?= $this->Paginator->numbers([
            'modulus' => 4,
            'first' => 2,
            'last' => 2,
        ]) ?>
        <?= $this->Paginator->next('Siguiente ›', ['escape' => false]) ?>
        <?= $this->Paginator->last('Última »', ['escape' => false]) ?>
    </ul>
</div>

<style>
.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    margin-top: 20px;
}

.pagination-info {
    color: #666;
    font-size: 14px;
}

.pagination-links {
    display: flex;
    gap: 5px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.pagination-links li {
    display: inline-block;
}

.pagination-links a,
.pagination-links span {
    display: inline-block;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    text-decoration: none;
    color: #333;
    font-size: 14px;
    transition: all 0.3s;
}

.pagination-links a:hover {
    background: #f0f0f0;
    border-color: #0066cc;
    color: #0066cc;
}

.pagination-links .active a {
    background: #0066cc;
    color: white;
    border-color: #0066cc;
}

.pagination-links .disabled span {
    color: #ccc;
    border-color: #eee;
    cursor: not-allowed;
}
</style>
<?php endif; ?>
