<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="alert alert-warning alert-dismissible fade show flash-message position-fixed end-0" role="alert">
    <i class="bi bi-exclamation-circle-fill me-2"></i>
    <strong>¡Advertencia!</strong> <?= $message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
