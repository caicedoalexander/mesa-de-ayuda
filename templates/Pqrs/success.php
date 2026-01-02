<?php
/**
 * PQRS Success Page
 * Modern, minimalist confirmation page matching the statistics section aesthetic
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Pqr|null $pqrs
 */
?>

<!-- Include Modern Statistics CSS for design consistency -->
<?= $this->Html->css('modern-statistics') ?>

<style>
/**
 * PQRS Success Page Styles
 * Extends modern-statistics.css with success-specific components
 */

:root {
    --success-green: #00A85E;
    --success-orange: #CD6A15;
    --gradient-success: linear-gradient(135deg, #00A85E 0%, #00D477 100%);
    --gradient-celebrate: linear-gradient(135deg, #00A85E 0%, #CD6A15 100%);
}

/* HTML & Body Scroll Control */
html,
body {
    overflow-x: hidden;
    overflow-y: auto;
    max-width: 100vw;
}

* {
    box-sizing: border-box;
}

/* Prevent container overflow */
.container {
    max-width: 100%;
}

/* Page Container */
.success-page {
    min-height: 100vh;
    background: linear-gradient(180deg, #F9FAFB 0%, #FFFFFF 100%);
    font-family: 'Manrope', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    position: relative;
    overflow-x: hidden;
    overflow-y: auto;
}

/* Custom Scrollbar */
.success-page::-webkit-scrollbar {
    width: 8px;
}

.success-page::-webkit-scrollbar-track {
    background: transparent;
}

.success-page::-webkit-scrollbar-thumb {
    background: rgba(0, 168, 94, 0.3);
    border-radius: 8px;
}

.success-page::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 168, 94, 0.5);
}

/* Firefox */
.success-page {
    scrollbar-width: thin;
    scrollbar-color: rgba(0, 168, 94, 0.3) transparent;
}

/* Decorative Background Orbs */
.success-page::before,
.success-page::after {
    content: '';
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.08;
    pointer-events: none;
    z-index: 0;
}

.success-page::before {
    width: 400px;
    height: 400px;
    background: var(--success-green);
    top: -200px;
    right: -200px;
    animation: float 20s ease-in-out infinite;
}

.success-page::after {
    width: 300px;
    height: 300px;
    background: var(--success-orange);
    bottom: -100px;
    left: -150px;
    animation: float 25s ease-in-out infinite reverse;
}

@keyframes float {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(30px, -30px) scale(1.1); }
    66% { transform: translate(-20px, 20px) scale(0.9); }
}

/* Header */
.success-header {
    background: var(--success-green);
    padding: 2rem 0;
    position: relative;
    z-index: 1;
    box-shadow: 0 4px 20px rgba(0, 168, 94, 0.15);
}

.success-header-content {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    background: white;
    padding: 0.75rem 1.5rem;
    border-radius: 14px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    width: fit-content;
    margin: 0 auto;
}

.success-header-content:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
}

.success-header-logo {
    height: 50px;
    width: auto;
}

.success-header-text h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    color: #111827;
    letter-spacing: -0.02em;
}

.success-header-text small {
    font-size: 0.875rem;
    color: #6B7280;
    font-weight: 500;
}

/* Content Container */
.success-content {
    position: relative;
    z-index: 1;
    max-width: 900px;
    width: 100%;
    margin: 0 auto;
    padding: 3rem 1.5rem;
    box-sizing: border-box;
}

/* Main Success Card */
.success-card-main {
    background: white;
    border-radius: 20px;
    border: 1px solid #E5E7EB;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.08);
    padding: 3rem;
    text-align: center;
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
    opacity: 0;
    transform: translateY(30px);
    animation: fadeUpIn 0.6s ease-out 0.2s forwards;
}

.success-card-main::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient-celebrate);
    box-shadow: 0 4px 15px rgba(0, 168, 94, 0.3);
}

@keyframes fadeUpIn {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Success Icon */
.success-icon-wrapper {
    width: 120px;
    height: 120px;
    margin: 0 auto 2rem;
    border-radius: 50%;
    background: linear-gradient(135deg, #E6F7F0 0%, #CCF0E1 100%);
    border: 4px solid #00A85E;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    animation: successPulse 2s ease-in-out infinite;
}

@keyframes successPulse {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(0, 168, 94, 0.4);
    }
    50% {
        transform: scale(1.05);
        box-shadow: 0 0 0 20px rgba(0, 168, 94, 0);
    }
}

.success-icon {
    font-size: 4rem;
    color: var(--success-green);
    animation: checkDraw 0.6s ease-out 0.4s both;
}

@keyframes checkDraw {
    0% {
        transform: scale(0) rotate(-180deg);
        opacity: 0;
    }
    50% {
        transform: scale(1.2) rotate(10deg);
    }
    100% {
        transform: scale(1) rotate(0);
        opacity: 1;
    }
}

/* Success Message */
.success-title {
    font-size: 2rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 1rem;
    letter-spacing: -0.03em;
    line-height: 1.2;
}

.success-subtitle {
    font-size: 1.125rem;
    color: #6B7280;
    font-weight: 500;
    margin-bottom: 2.5rem;
    line-height: 1.6;
}

/* PQRS Number Display */
.pqrs-number-display {
    background: linear-gradient(135deg, #F9FAFB 0%, #FFFFFF 100%);
    border: 2px solid #E5E7EB;
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    position: relative;
    transition: all 0.3s ease;
    cursor: pointer;
}

.pqrs-number-display:hover {
    border-color: var(--success-green);
    box-shadow: 0 10px 30px rgba(0, 168, 94, 0.15);
    transform: translateY(-2px);
}

.pqrs-number-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #6B7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.pqrs-number-value {
    font-size: 2.5rem;
    font-weight: 700;
    background: var(--gradient-celebrate);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: 0.02em;
    font-variant-numeric: tabular-nums;
    margin-bottom: 0.5rem;
}

.pqrs-number-hint {
    font-size: 0.8125rem;
    color: #9CA3AF;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    opacity: 0.8;
    transition: opacity 0.3s ease;
}

.pqrs-number-display:hover .pqrs-number-hint {
    opacity: 1;
}

/* Details Card */
.details-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #E5E7EB;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    padding: 2rem;
    margin-bottom: 2rem;
    opacity: 0;
    transform: translateY(30px);
    animation: fadeUpIn 0.6s ease-out 0.4s forwards;
}

.details-card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #F3F4F6;
}

.details-card-header i {
    color: var(--success-green);
    font-size: 1.5rem;
}

.details-card-header h3 {
    font-size: 1.125rem;
    font-weight: 700;
    color: #111827;
    margin: 0;
    letter-spacing: -0.01em;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.detail-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #6B7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.detail-value {
    font-size: 1rem;
    font-weight: 600;
    color: #111827;
    line-height: 1.4;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.875rem;
    font-weight: 700;
    background: linear-gradient(135deg, #FEF3EC 0%, #FCE7D9 100%);
    color: #CD6A15;
    border: 2px solid #FCE7D9;
}

.status-badge i {
    font-size: 0.75rem;
}

/* Next Steps Card */
.nextsteps-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #E5E7EB;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    padding: 2rem;
    margin-bottom: 2rem;
    opacity: 0;
    transform: translateY(30px);
    animation: fadeUpIn 0.6s ease-out 0.6s forwards;
}

.nextsteps-card h3 {
    font-size: 1.125rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.nextsteps-card h3 i {
    color: var(--success-green);
    font-size: 1.25rem;
}

.nextsteps-list {
    list-style: none;
    padding: 0;
    margin: 0;
    counter-reset: step-counter;
}

.nextsteps-list li {
    position: relative;
    padding-left: 3rem;
    margin-bottom: 1.5rem;
    counter-increment: step-counter;
    line-height: 1.6;
    color: #374151;
    font-weight: 500;
}

.nextsteps-list li:last-child {
    margin-bottom: 0;
}

.nextsteps-list li::before {
    content: counter(step-counter);
    position: absolute;
    left: 0;
    top: 0;
    width: 32px;
    height: 32px;
    background: var(--gradient-celebrate);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
    box-shadow: 0 4px 10px rgba(0, 168, 94, 0.2);
}

.nextsteps-list li strong {
    color: #111827;
}

/* Contact Card */
.contact-card {
    background: linear-gradient(135deg, #F9FAFB 0%, #FFFFFF 100%);
    border-radius: 16px;
    border: 1px solid #E5E7EB;
    padding: 2rem;
    text-align: center;
    opacity: 0;
    transform: translateY(30px);
    animation: fadeUpIn 0.6s ease-out 0.8s forwards;
}

.contact-card-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 1rem;
    background: linear-gradient(135deg, #E6F7F0 0%, #CCF0E1 100%);
    border: 2px solid #00A85E;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.contact-card:hover .contact-card-icon {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 8px 20px rgba(0, 168, 94, 0.2);
}

.contact-card-icon i {
    font-size: 1.75rem;
    color: var(--success-green);
}

.contact-card h4 {
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 1rem;
}

.contact-card p {
    font-size: 0.9375rem;
    color: #6B7280;
    line-height: 1.8;
    margin-bottom: 0;
}

.contact-card a {
    color: var(--success-green);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    position: relative;
}

.contact-card a::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--gradient-celebrate);
    transition: width 0.3s ease;
}

.contact-card a:hover::after {
    width: 100%;
}

/* Responsive Design */
@media (max-width: 768px) {
    .success-content {
        padding: 2rem 1rem;
    }

    .success-card-main {
        padding: 2rem 1.5rem;
    }

    .success-title {
        font-size: 1.5rem;
    }

    .success-subtitle {
        font-size: 1rem;
    }

    .pqrs-number-value {
        font-size: 2rem;
    }

    .success-icon-wrapper {
        width: 100px;
        height: 100px;
    }

    .success-icon {
        font-size: 3rem;
    }

    .details-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .details-card,
    .nextsteps-card,
    .contact-card {
        padding: 1.5rem;
    }
}

/* Copy to Clipboard Feedback */
.copy-feedback {
    position: fixed;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: #111827;
    color: white;
    padding: 1rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.875rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    opacity: 0;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    pointer-events: none;
    z-index: 1000;
}

.copy-feedback.show {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
}

.copy-feedback i {
    margin-right: 0.5rem;
    color: var(--success-green);
}

/* Accessibility */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* Focus States */
.pqrs-number-display:focus-visible,
.contact-card a:focus-visible {
    outline: 3px solid var(--success-green);
    outline-offset: 4px;
    border-radius: 16px;
}
</style>

<div class="success-page">
    <!-- Header -->
    <div class="success-header">
        <div class="container">
            <div class="success-header-content">
                <img class="success-header-logo" src="<?= $this->Url->build('img/logos/servicioalcliente.svg') ?>" alt="Servicio al Cliente">
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="success-content">
        <?php if ($pqrs): ?>
            <!-- Main Success Card -->
            <div class="success-card-main">
                <!-- Success Icon -->
                <div class="success-icon-wrapper">
                    <i class="bi bi-check-circle-fill success-icon"></i>
                </div>

                <!-- Success Message -->
                <h1 class="success-title">¡Solicitud Recibida Exitosamente!</h1>
                <p class="success-subtitle">
                    Su PQRS ha sido registrada en nuestro sistema y será atendida a la brevedad posible
                </p>

                <!-- PQRS Number -->
                <div class="pqrs-number-display"
                     onclick="copyToClipboard('<?= h($pqrs->pqrs_number) ?>')"
                     role="button"
                     tabindex="0"
                     aria-label="Copiar número de seguimiento">
                    <div class="pqrs-number-label">
                        <i class="bi bi-ticket-detailed"></i>
                        Número de Seguimiento
                    </div>
                    <div class="pqrs-number-value"><?= h($pqrs->pqrs_number) ?></div>
                    <div class="pqrs-number-hint">
                        <i class="bi bi-clipboard"></i>
                        Haga clic para copiar
                    </div>
                </div>
            </div>

            <!-- Details Card -->
            <div class="details-card">
                <div class="details-card-header">
                    <i class="bi bi-info-circle-fill"></i>
                    <h3>Detalles de su Solicitud</h3>
                </div>
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">Tipo</span>
                        <span class="detail-value">
                            <?php
                            $typeLabels = [
                                'peticion' => '📝 Petición',
                                'queja' => '⚠️ Queja',
                                'reclamo' => '❌ Reclamo',
                                'sugerencia' => '💡 Sugerencia'
                            ];
                            echo h($typeLabels[$pqrs->type] ?? $pqrs->type);
                            ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Estado</span>
                        <span class="status-badge">
                            <i class="bi bi-circle-fill"></i>
                            Nuevo
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Asunto</span>
                        <span class="detail-value"><?= h($pqrs->subject) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Fecha de Registro</span>
                        <span class="detail-value">
                            <i class="bi bi-calendar-check"></i>
                            <?= $pqrs->created->format('d/m/Y H:i') ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Next Steps Card -->
            <div class="nextsteps-card">
                <h3>
                    <i class="bi bi-list-check"></i>
                    ¿Qué Sucede Ahora?
                </h3>
                <ol class="nextsteps-list">
                    <li>
                        Hemos enviado un correo de confirmación a <strong><?= h($pqrs->requester_email) ?></strong>
                    </li>
                    <li>
                        Nuestro equipo revisará su solicitud y la asignará al departamento correspondiente
                    </li>
                    <li>
                        Recibirá notificaciones por correo electrónico sobre cada actualización del estado de su PQRS
                    </li>
                    <li>
                        Guarde su número de seguimiento <strong><?= h($pqrs->pqrs_number) ?></strong> para futuras consultas
                    </li>
                </ol>
            </div>

            <!-- Contact Card -->
            <div class="contact-card">
                <div class="contact-card-icon">
                    <i class="bi bi-headset"></i>
                </div>
                <h4>¿Necesita Ayuda Adicional?</h4>
                <p>
                    Horario de Atención: Lunes a Viernes, 8:00 AM - 5:00 PM<br>
                    Correo Electrónico: <a href="mailto:servicioalcliente@operadoracafetera.com">servicioalcliente@operadoracafetera.com</a>
                </p>
            </div>

        <?php else: ?>
            <!-- Fallback if no PQRS data -->
            <div class="success-card-main">
                <div class="success-icon-wrapper">
                    <i class="bi bi-check-circle-fill success-icon"></i>
                </div>
                <h1 class="success-title">¡Gracias por Contactarnos!</h1>
                <p class="success-subtitle">
                    Hemos recibido su solicitud y la procesaremos a la brevedad
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Copy Feedback Toast -->
    <div class="copy-feedback" id="copyFeedback" role="alert" aria-live="polite">
        <i class="bi bi-check-circle-fill"></i>
        Número copiado al portapapeles
    </div>
</div>

<script>
/**
 * Copy to clipboard functionality
 */
function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            showCopyFeedback();
        }).catch(err => {
            console.error('Failed to copy:', err);
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
}

/**
 * Fallback copy method for older browsers
 */
function fallbackCopy(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    document.body.appendChild(textArea);
    textArea.select();

    try {
        document.execCommand('copy');
        showCopyFeedback();
    } catch (err) {
        console.error('Fallback copy failed:', err);
    }

    document.body.removeChild(textArea);
}

/**
 * Show copy feedback toast
 */
function showCopyFeedback() {
    const feedback = document.getElementById('copyFeedback');
    feedback.classList.add('show');

    setTimeout(() => {
        feedback.classList.remove('show');
    }, 3000);
}

/**
 * Keyboard accessibility for copy button
 */
document.addEventListener('DOMContentLoaded', () => {
    const copyButton = document.querySelector('.pqrs-number-display');
    if (copyButton) {
        copyButton.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const pqrsNumber = copyButton.querySelector('.pqrs-number-value').textContent;
                copyToClipboard(pqrsNumber.trim());
            }
        });
    }
});
</script>
