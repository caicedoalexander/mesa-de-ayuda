<div class="py-3 bg-white border-end" style="width: 100%; height: 100%;">
    <div class="mb-2 ps-3">
        <h3 class="fw-normal">Vistas</h3>
    </div>
    <div class="d-flex flex-column fw-light list-group">
        <a class="list-group-item border-0 list-group-item-action py-2" href="<?= $this->Url->build(['controller' => 'Users', 'action' => 'index']) ?>">
            <span>Lista de usuarios</span>
        </a>
        <a class="list-group-item border-0 list-group-item-action py-2" href="<?= $this->Url->build(['controller' => 'Users', 'action' => 'view', $this->Identity->get('id')]) ?>">
            <span>Ver usuario</span>
        </a>
        <a class="list-group-item border-0 list-group-item-action py-2" href="<?= $this->Url->build(['controller' => 'Users', 'action' => 'edit', $this->Identity->get('id')]) ?>">
            <span>Editar usuario</span>
        </a>
    </div>
</div>
