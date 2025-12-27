<?php
/**
 * @var \App\View\AppView $this
 * @var array $slaSettings
 */
$this->assign('title', 'Configuración de SLA');
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="bi bi-clock-history"></i>
                        Configuración de SLA (Service Level Agreement)
                    </h4>
                </div>
                <div class="card-body">
                    <?= $this->Flash->render() ?>

                    <?= $this->Form->create(null, ['type' => 'post']) ?>

                    <!-- PQRS SLA Configuration -->
                    <div class="mb-5">
                        <h5 class="border-bottom pb-2 mb-3">
                            <i class="bi bi-chat-quote text-primary"></i>
                            PQRS - SLA por Tipo de Solicitud
                        </h5>
                        <p class="text-muted">Configure los tiempos de respuesta según el tipo de PQRS (Petición, Queja, Reclamo, Sugerencia)</p>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 25%">Tipo de PQRS</th>
                                        <th style="width: 35%">Primera Respuesta (días)</th>
                                        <th style="width: 40%">Resolución Total (días)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="align-middle"><strong>📝 Petición</strong></td>
                                        <td>
                                            <div class="input-group">
                                                <?= $this->Form->control('sla_pqrs_peticion_first_response_days', [
                                                    'type' => 'number',
                                                    'min' => 1,
                                                    'max' => 30,
                                                    'value' => $slaSettings['sla_pqrs_peticion_first_response_days'] ?? 2,
                                                    'class' => 'form-control',
                                                    'label' => false,
                                                ]) ?>
                                                <span class="input-group-text">días</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <?= $this->Form->control('sla_pqrs_peticion_resolution_days', [
                                                    'type' => 'number',
                                                    'min' => 1,
                                                    'max' => 30,
                                                    'value' => $slaSettings['sla_pqrs_peticion_resolution_days'] ?? 5,
                                                    'class' => 'form-control',
                                                    'label' => false,
                                                ]) ?>
                                                <span class="input-group-text">días</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="align-middle"><strong>😠 Queja</strong></td>
                                        <td>
                                            <div class="input-group">
                                                <?= $this->Form->control('sla_pqrs_queja_first_response_days', [
                                                    'type' => 'number',
                                                    'min' => 1,
                                                    'max' => 30,
                                                    'value' => $slaSettings['sla_pqrs_queja_first_response_days'] ?? 1,
                                                    'class' => 'form-control',
                                                    'label' => false,
                                                ]) ?>
                                                <span class="input-group-text">días</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <?= $this->Form->control('sla_pqrs_queja_resolution_days', [
                                                    'type' => 'number',
                                                    'min' => 1,
                                                    'max' => 30,
                                                    'value' => $slaSettings['sla_pqrs_queja_resolution_days'] ?? 3,
                                                    'class' => 'form-control',
                                                    'label' => false,
                                                ]) ?>
                                                <span class="input-group-text">días</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="align-middle"><strong>⚠️ Reclamo</strong></td>
                                        <td>
                                            <div class="input-group">
                                                <?= $this->Form->control('sla_pqrs_reclamo_first_response_days', [
                                                    'type' => 'number',
                                                    'min' => 1,
                                                    'max' => 30,
                                                    'value' => $slaSettings['sla_pqrs_reclamo_first_response_days'] ?? 1,
                                                    'class' => 'form-control',
                                                    'label' => false,
                                                ]) ?>
                                                <span class="input-group-text">días</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <?= $this->Form->control('sla_pqrs_reclamo_resolution_days', [
                                                    'type' => 'number',
                                                    'min' => 1,
                                                    'max' => 30,
                                                    'value' => $slaSettings['sla_pqrs_reclamo_resolution_days'] ?? 3,
                                                    'class' => 'form-control',
                                                    'label' => false,
                                                ]) ?>
                                                <span class="input-group-text">días</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="align-middle"><strong>💡 Sugerencia</strong></td>
                                        <td>
                                            <div class="input-group">
                                                <?= $this->Form->control('sla_pqrs_sugerencia_first_response_days', [
                                                    'type' => 'number',
                                                    'min' => 1,
                                                    'max' => 30,
                                                    'value' => $slaSettings['sla_pqrs_sugerencia_first_response_days'] ?? 3,
                                                    'class' => 'form-control',
                                                    'label' => false,
                                                ]) ?>
                                                <span class="input-group-text">días</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <?= $this->Form->control('sla_pqrs_sugerencia_resolution_days', [
                                                    'type' => 'number',
                                                    'min' => 1,
                                                    'max' => 30,
                                                    'value' => $slaSettings['sla_pqrs_sugerencia_resolution_days'] ?? 7,
                                                    'class' => 'form-control',
                                                    'label' => false,
                                                ]) ?>
                                                <span class="input-group-text">días</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Compras SLA Configuration -->
                    <div class="mb-4">
                        <h5 class="border-bottom pb-2 mb-3">
                            <i class="bi bi-cart text-success"></i>
                            Compras - SLA Único
                        </h5>
                        <p class="text-muted">Configure los tiempos de respuesta para el módulo de Compras</p>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <label class="form-label"><strong>Primera Respuesta</strong></label>
                                        <div class="input-group">
                                            <?= $this->Form->control('sla_compras_first_response_days', [
                                                'type' => 'number',
                                                'min' => 1,
                                                'max' => 30,
                                                'value' => $slaSettings['sla_compras_first_response_days'] ?? 1,
                                                'class' => 'form-control form-control-lg',
                                                'label' => false,
                                            ]) ?>
                                            <span class="input-group-text">días</span>
                                        </div>
                                        <small class="text-muted">Tiempo para primer contacto del equipo de compras</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <label class="form-label"><strong>Resolución Total</strong></label>
                                        <div class="input-group">
                                            <?= $this->Form->control('sla_compras_resolution_days', [
                                                'type' => 'number',
                                                'min' => 1,
                                                'max' => 30,
                                                'value' => $slaSettings['sla_compras_resolution_days'] ?? 3,
                                                'class' => 'form-control form-control-lg',
                                                'label' => false,
                                            ]) ?>
                                            <span class="input-group-text">días</span>
                                        </div>
                                        <small class="text-muted">Tiempo total para completar o rechazar la compra</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Help Text -->
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Nota:</strong> Los cambios en esta configuración afectarán solo a los nuevos registros.
                        Para recalcular SLA en registros existentes, use el botón "Recalcular SLA" en cada PQRS o Compra individual.
                    </div>

                    <!-- Submit Button -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <?= $this->Html->link(
                            '<i class="bi bi-arrow-left"></i> Volver',
                            ['action' => 'index'],
                            ['class' => 'btn btn-secondary', 'escape' => false]
                        ) ?>
                        <?= $this->Form->button(
                            '<i class="bi bi-save"></i> Guardar Configuración',
                            ['type' => 'submit', 'class' => 'btn btn-primary', 'escape' => false]
                        ) ?>
                    </div>

                    <?= $this->Form->end() ?>
                </div>
            </div>
        </div>
    </div>
</div>
