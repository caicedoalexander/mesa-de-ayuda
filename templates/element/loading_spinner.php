<?php
/**
 * Loading Spinner Element
 * Sistema de Soporte
 *
 * Uso:
 * <?= $this->element('loading_spinner') ?>
 *
 * Con texto personalizado:
 * <?= $this->element('loading_spinner', ['message' => 'Cargando datos...']) ?>
 */

$message = $message ?? 'Cargando...';
$size = $size ?? 'default'; // 'small', 'default', 'large'
$overlay = $overlay ?? true; // Mostrar overlay de fondo

$spinnerClass = match($size) {
    'small' => 'spinner-border-sm',
    'large' => 'spinner-border-lg',
    default => ''
};
?>

<div id="loading-spinner" class="loading-overlay" style="display: none;">
    <?php if ($overlay): ?>
    <div class="loading-backdrop"></div>
    <?php endif; ?>

    <div class="loading-content">
        <div class="spinner-border <?= $spinnerClass ?>" role="status" style="color: #00A85E;">
            <span class="visually-hidden">Cargando...</span>
        </div>
        <?php if ($message): ?>
        <div class="loading-message mt-3"><?= h($message) ?></div>
        <?php endif; ?>
    </div>
</div>

<style>
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: none;
}

.loading-overlay.show {
    display: flex !important;
    align-items: center;
    justify-content: center;
}

.loading-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(3px);
}

.loading-content {
    position: relative;
    z-index: 10000;
    text-align: center;
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.loading-message {
    color: #495057;
    font-size: 1rem;
    font-weight: 500;
    margin-top: 1rem;
}

.spinner-border-lg {
    width: 3rem;
    height: 3rem;
    border-width: 0.3em;
}

/* Animación de entrada/salida */
.loading-overlay.show {
    animation: fadeIn 0.2s ease-in;
}

.loading-overlay.hiding {
    animation: fadeOut 0.2s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes fadeOut {
    from {
        opacity: 1;
    }
    to {
        opacity: 0;
    }
}
</style>
