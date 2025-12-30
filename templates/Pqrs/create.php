<!DOCTYPE html>
<html lang="es">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PQRS - Sistema de Peticiones, Quejas, Reclamos y Sugerencias</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- Custom Styles -->
    <?= $this->Html->css(['styles']) ?>

    <style>
/**
 * PQRS Form - Modern Minimalist Design
 * Matches success page and statistics section aesthetic
 */

:root {
    --form-green: #00A85E;
    --form-orange: #CD6A15;
    --gradient-primary: linear-gradient(135deg, #00A85E 0%, #00D477 100%);
    --gradient-accent: linear-gradient(135deg, #CD6A15 0%, #F07D2D 100%);
    --gradient-celebrate: linear-gradient(135deg, #00A85E 0%, #CD6A15 100%);

    /* Neutrals */
    --gray-50: #F9FAFB;
    --gray-100: #F3F4F6;
    --gray-200: #E5E7EB;
    --gray-300: #D1D5DB;
    --gray-400: #9CA3AF;
    --gray-500: #6B7280;
    --gray-600: #4B5563;
    --gray-700: #374151;
    --gray-800: #1F2937;
    --gray-900: #111827;

    /* Shadows */
    --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.08);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    --shadow-green: 0 10px 30px -10px rgba(0, 168, 94, 0.25);
    --shadow-orange: 0 10px 30px -10px rgba(205, 106, 21, 0.25);

    /* Transitions */
    --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
    --transition-base: 250ms cubic-bezier(0.4, 0, 0.2, 1);
    --transition-smooth: 350ms cubic-bezier(0.4, 0, 0.2, 1);

    /* Border Radius */
    --radius-sm: 6px;
    --radius-md: 10px;
    --radius-lg: 14px;
    --radius-xl: 20px;
    --radius-full: 9999px;
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

/* Prevent Bootstrap row overflow */
.row {
    margin-left: 0;
    margin-right: 0;
}

.row > * {
    padding-left: calc(var(--bs-gutter-x) * 0.5);
    padding-right: calc(var(--bs-gutter-x) * 0.5);
}

/* Page Container */
.pqrs-form-page {
    min-height: 100vh;
    background: linear-gradient(180deg, #F9FAFB 0%, #FFFFFF 100%);
    font-family: 'Manrope', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    position: relative;
    overflow-x: hidden;
    overflow-y: auto;
}

/* Custom Scrollbar */
.pqrs-form-page::-webkit-scrollbar {
    width: 8px;
}

.pqrs-form-page::-webkit-scrollbar-track {
    background: transparent;
}

.pqrs-form-page::-webkit-scrollbar-thumb {
    background: rgba(0, 168, 94, 0.3);
    border-radius: 8px;
}

.pqrs-form-page::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 168, 94, 0.5);
}

/* Firefox */
.pqrs-form-page {
    scrollbar-width: thin;
    scrollbar-color: rgba(0, 168, 94, 0.3) transparent;
}

/* Decorative Background Orbs */
.pqrs-form-page::before,
.pqrs-form-page::after {
    content: '';
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.06;
    pointer-events: none;
    z-index: 0;
}

.pqrs-form-page::before {
    width: 500px;
    height: 500px;
    background: var(--form-orange);
    top: -250px;
    left: -250px;
    animation: float 25s ease-in-out infinite;
}

.pqrs-form-page::after {
    width: 400px;
    height: 400px;
    background: var(--form-green);
    bottom: -100px;
    right: -200px;
    animation: float 20s ease-in-out infinite reverse;
}

@keyframes float {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(30px, -30px) scale(1.1); }
    66% { transform: translate(-20px, 20px) scale(0.9); }
}

/* Header */
.pqrs-form-header {
    background: var(--form-orange);
    padding: 3rem 0;
    position: relative;
    z-index: 1;
    box-shadow: 0 4px 20px rgba(205, 106, 21, 0.15);
    margin-bottom: 3rem;
}

.pqrs-form-header-content {
    text-align: center;
    color: white;
}

.pqrs-form-header-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: headerIconFloat 3s ease-in-out infinite;
}

@keyframes headerIconFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.pqrs-form-header-icon i {
    font-size: 2.5rem;
    color: white;
}

.pqrs-form-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    letter-spacing: -0.02em;
}

.pqrs-form-header h4 {
    font-size: 1.25rem;
    font-weight: 500;
    margin-bottom: 1rem;
    opacity: 0.95;
}

.pqrs-form-header p {
    font-size: 1rem;
    opacity: 0.9;
    max-width: 600px;
    margin: 0 auto;
}

/* Content Container */
.pqrs-form-content {
    position: relative;
    z-index: 1;
    max-width: 900px;
    width: 100%;
    margin: 0 auto;
    padding: 0 1.5rem 3rem;
    box-sizing: border-box;
}

/* Info Card */
.info-card {
    background: white;
    border-radius: var(--radius-lg);
    border: 1px solid var(--gray-200);
    box-shadow: var(--shadow-sm);
    padding: 2rem;
    margin-bottom: 2rem;
    display: flex;
    align-items: flex-start;
    gap: 1.5rem;
    opacity: 0;
    transform: translateY(30px);
    animation: fadeUpIn 0.6s ease-out 0.1s forwards;
}

@keyframes fadeUpIn {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.info-card-icon {
    width: 56px;
    height: 56px;
    flex-shrink: 0;
    background: linear-gradient(135deg, #E6F7F0 0%, #CCF0E1 100%);
    border: 2px solid #00A85E;
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
}

.info-card-icon i {
    font-size: 1.5rem;
    color: var(--form-green);
}

.info-card-content h5 {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--gray-900);
    margin-bottom: 0.5rem;
}

.info-card-content p {
    font-size: 0.9375rem;
    color: var(--gray-600);
    line-height: 1.6;
    margin: 0;
}

/* Form Card */
.form-card {
    background: white;
    border-radius: var(--radius-xl);
    border: 1px solid var(--gray-200);
    box-shadow: var(--shadow-lg);
    padding: 3rem;
    opacity: 0;
    transform: translateY(30px);
    animation: fadeUpIn 0.6s ease-out 0.2s forwards;
}

/* Form Section */
.form-section {
    margin-bottom: 3rem;
    padding-bottom: 2rem;
    border-bottom: 2px solid var(--gray-100);
}

.form-section:last-of-type {
    border-bottom: none;
    margin-bottom: 2rem;
}

.form-section-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
}

.form-section-icon {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    background: linear-gradient(135deg, #FEF3EC 0%, #FCE7D9 100%);
    border: 2px solid var(--form-orange);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-smooth);
}

.form-section-icon i {
    font-size: 1.25rem;
    color: var(--form-orange);
    transition: all var(--transition-smooth);
}

.form-section:hover .form-section-icon {
    transform: scale(1.05);
}

.form-section-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0;
    letter-spacing: -0.01em;
}

/* PQRS Type Cards (Radio Buttons) */
.pqrs-type-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.pqrs-type-card {
    position: relative;
}

.pqrs-type-card input[type="radio"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.pqrs-type-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 1.5rem 1rem;
    background: var(--gray-50);
    border: 2px solid var(--gray-200);
    border-radius: var(--radius-lg);
    cursor: pointer;
    transition: all var(--transition-smooth);
    text-align: center;
}

.pqrs-type-label:hover {
    background: white;
    border-color: var(--form-orange);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.pqrs-type-card input[type="radio"]:checked + .pqrs-type-label {
    background: linear-gradient(135deg, #FEF3EC 0%, #FCE7D9 100%);
    border-color: var(--form-orange);
    box-shadow: var(--shadow-orange);
}

.pqrs-type-icon {
    font-size: 2rem;
    transition: all var(--transition-smooth);
}

.pqrs-type-card input[type="radio"]:checked + .pqrs-type-label .pqrs-type-icon {
    transform: scale(1.2);
}

.pqrs-type-name {
    font-size: 1rem;
    font-weight: 600;
    color: var(--gray-900);
    transition: color var(--transition-base);
}

.pqrs-type-card input[type="radio"]:checked + .pqrs-type-label .pqrs-type-name {
    color: var(--form-orange);
}

/* Type Descriptions */
.type-descriptions {
    background: var(--gray-50);
    border-radius: var(--radius-md);
    padding: 1.25rem;
    font-size: 0.875rem;
    color: var(--gray-600);
    line-height: 1.8;
}

.type-descriptions strong {
    color: var(--gray-900);
    font-weight: 600;
}

/* Form Inputs */
.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
    letter-spacing: 0.01em;
}

.form-label.required::after {
    content: " *";
    color: var(--form-orange);
    font-weight: 700;
}

.form-input,
.form-select,
.form-textarea {
    width: 100%;
    padding: 0.875rem 1rem;
    font-size: 0.9375rem;
    font-weight: 500;
    color: var(--gray-900);
    background: white;
    border: 2px solid var(--gray-200);
    border-radius: var(--radius-md);
    transition: all var(--transition-smooth);
    font-family: 'Manrope', sans-serif;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: var(--form-green);
    box-shadow: 0 0 0 4px rgba(0, 168, 94, 0.1);
}

.form-input::placeholder,
.form-textarea::placeholder {
    color: var(--gray-400);
    font-weight: 400;
}

.form-textarea {
    resize: vertical;
    min-height: 150px;
    line-height: 1.6;
}

/* Input Helper Text */
.form-text {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.8125rem;
    color: var(--gray-500);
    line-height: 1.4;
}

/* File Upload */
.file-upload-wrapper {
    position: relative;
}

.file-upload-zone {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2.5rem 1.5rem;
    background: var(--gray-50);
    border: 2px dashed var(--gray-300);
    border-radius: var(--radius-lg);
    cursor: pointer;
    transition: all var(--transition-smooth);
}

.file-upload-zone:hover {
    background: white;
    border-color: var(--form-green);
}

.file-upload-zone.drag-over {
    background: linear-gradient(135deg, #E6F7F0 0%, #CCF0E1 100%);
    border-color: var(--form-green);
    border-style: solid;
    box-shadow: var(--shadow-green);
}

.file-upload-icon {
    width: 64px;
    height: 64px;
    margin-bottom: 1rem;
    background: white;
    border: 2px solid var(--gray-200);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-smooth);
}

.file-upload-zone:hover .file-upload-icon {
    background: var(--form-green);
    border-color: var(--form-green);
}

.file-upload-icon i {
    font-size: 1.75rem;
    color: var(--gray-400);
    transition: all var(--transition-smooth);
}

.file-upload-zone:hover .file-upload-icon i {
    color: white;
}

.file-upload-text {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
}

.file-upload-hint {
    font-size: 0.8125rem;
    color: var(--gray-500);
}

.file-input-hidden {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

/* Privacy Notice */
.privacy-notice {
    background: linear-gradient(135deg, #E6F7F0 0%, #CCF0E1 100%);
    border: 2px solid rgba(0, 168, 94, 0.2);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 2rem;
}

.privacy-notice-icon {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    background: white;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
}

.privacy-notice-icon i {
    font-size: 1.25rem;
    color: var(--form-green);
}

.privacy-notice-content {
    flex: 1;
}

.privacy-notice-content strong {
    color: var(--gray-900);
    font-weight: 700;
}

.privacy-notice-content p {
    font-size: 0.875rem;
    color: var(--gray-700);
    line-height: 1.6;
    margin: 0;
}

/* Submit Button */
.submit-button {
    width: 100%;
    padding: 1.25rem 2rem;
    font-size: 1.125rem;
    font-weight: 700;
    color: white;
    background: var(--gradient-celebrate);
    border: none;
    border-radius: var(--radius-lg);
    cursor: pointer;
    transition: all var(--transition-smooth);
    box-shadow: 0 4px 15px rgba(0, 168, 94, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.submit-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 168, 94, 0.4);
}

.submit-button:active {
    transform: translateY(0);
}

.submit-button i {
    font-size: 1.25rem;
}

/* Footer Info */
.footer-info {
    text-align: center;
    padding: 2rem 0;
    color: var(--gray-500);
}

.footer-info p {
    font-size: 0.9375rem;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.footer-info i {
    color: var(--form-orange);
}

/* Character Counter */
.char-counter {
    display: flex;
    justify-content: flex-end;
    margin-top: 0.5rem;
    font-size: 0.8125rem;
    color: var(--gray-500);
    font-weight: 500;
}

.char-counter.warning {
    color: var(--form-orange);
}

.char-counter.valid {
    color: var(--form-green);
}

/* Form Validation States */
.was-validated .form-input:invalid,
.was-validated .form-select:invalid,
.was-validated .form-textarea:invalid {
    border-color: #EF4444;
}

.was-validated .form-input:valid,
.was-validated .form-select:valid,
.was-validated .form-textarea:valid {
    border-color: var(--form-green);
}

.invalid-feedback {
    display: none;
    margin-top: 0.5rem;
    font-size: 0.8125rem;
    color: #EF4444;
    font-weight: 500;
}

.was-validated .form-input:invalid ~ .invalid-feedback,
.was-validated .form-select:invalid ~ .invalid-feedback,
.was-validated .form-textarea:invalid ~ .invalid-feedback {
    display: block;
}

/* Responsive Design */
@media (max-width: 768px) {
    .pqrs-form-header {
        padding: 2rem 0;
    }

    .pqrs-form-header h1 {
        font-size: 2rem;
    }

    .pqrs-form-header h4 {
        font-size: 1.125rem;
    }

    .pqrs-form-content {
        padding: 0 1rem 2rem;
    }

    .form-card {
        padding: 2rem 1.5rem;
    }

    .pqrs-type-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .info-card {
        padding: 1.5rem;
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .pqrs-type-grid {
        grid-template-columns: 1fr;
    }
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
.pqrs-type-label:focus-within {
    outline: 3px solid var(--form-orange);
    outline-offset: 2px;
}

.form-input:focus-visible,
.form-select:focus-visible,
.form-textarea:focus-visible {
    outline: 3px solid var(--form-green);
    outline-offset: 2px;
}
    </style>
</head>
<body class="pqrs-form-page">
    <!-- Header -->
    <div class="pqrs-form-header">
        <div class="container">
            <div class="pqrs-form-header-content">
                <div class="pqrs-form-header-icon">
                    <i class="bi bi-chat-square-text-fill"></i>
                </div>
                <h1>PQRS</h1>
                <h4>Peticiones, Quejas, Reclamos y Sugerencias</h4>
                <p>Estamos aquí para escucharte. Tu opinión es importante para nosotros.</p>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="pqrs-form-content">
        <?= $this->Flash->render() ?>

        <!-- Loading Spinner -->
        <?= $this->element('loading_spinner', ['message' => 'Enviando PQRS...']) ?>

        <!-- Info Card -->
        <div class="info-card">
            <div class="info-card-icon">
                <i class="bi bi-info-circle-fill"></i>
            </div>
            <div class="info-card-content">
                <h5>Información</h5>
                <p>Complete el siguiente formulario para enviar su petición, queja, reclamo o sugerencia. Recibirá un número de seguimiento para consultar el estado de su solicitud.</p>
            </div>
        </div>

        <!-- Form Card -->
        <div class="form-card">
            <?= $this->Form->create($pqrs, [
                'type' => 'file',
                'class' => 'needs-validation',
                'novalidate' => true,
                'id' => 'pqrsForm'
            ]) ?>

            <!-- PQRS Type Section -->
            <div class="form-section">
                <div class="form-section-header">
                    <div class="form-section-icon">
                        <i class="bi bi-bookmark-fill"></i>
                    </div>
                    <h3 class="form-section-title">Tipo de Solicitud</h3>
                </div>

                <div class="pqrs-type-grid">
                    <div class="pqrs-type-card">
                        <input type="radio" name="type" id="type-peticion" value="peticion" required>
                        <label class="pqrs-type-label" for="type-peticion">
                            <span class="pqrs-type-icon">📝</span>
                            <span class="pqrs-type-name">Petición</span>
                        </label>
                    </div>

                    <div class="pqrs-type-card">
                        <input type="radio" name="type" id="type-queja" value="queja" required>
                        <label class="pqrs-type-label" for="type-queja">
                            <span class="pqrs-type-icon">⚠️</span>
                            <span class="pqrs-type-name">Queja</span>
                        </label>
                    </div>

                    <div class="pqrs-type-card">
                        <input type="radio" name="type" id="type-reclamo" value="reclamo" required>
                        <label class="pqrs-type-label" for="type-reclamo">
                            <span class="pqrs-type-icon">❌</span>
                            <span class="pqrs-type-name">Reclamo</span>
                        </label>
                    </div>

                    <div class="pqrs-type-card">
                        <input type="radio" name="type" id="type-sugerencia" value="sugerencia" required>
                        <label class="pqrs-type-label" for="type-sugerencia">
                            <span class="pqrs-type-icon">💡</span>
                            <span class="pqrs-type-name">Sugerencia</span>
                        </label>
                    </div>
                </div>

                <div class="type-descriptions">
                    <p><strong>Petición:</strong> Solicitud de información o servicio</p>
                    <p><strong>Queja:</strong> Insatisfacción con un servicio</p>
                    <p><strong>Reclamo:</strong> Exigencia de un derecho o corrección</p>
                    <p><strong>Sugerencia:</strong> Propuesta de mejora</p>
                </div>

                <div class="form-group mt-4">
                    <label for="priority" class="form-label">Prioridad</label>
                    <?= $this->Form->select('priority', $priorities, [
                        'class' => 'form-select',
                        'id' => 'priority',
                        'default' => 'media'
                    ]) ?>
                </div>
            </div>

            <!-- Personal Data Section -->
            <div class="form-section">
                <div class="form-section-header">
                    <div class="form-section-icon">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <h3 class="form-section-title">Datos Personales</h3>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="requester-name" class="form-label required">Nombre Completo</label>
                            <?= $this->Form->text('requester_name', [
                                'class' => 'form-input',
                                'id' => 'requester-name',
                                'placeholder' => 'Ingrese su nombre completo',
                                'required' => true
                            ]) ?>
                            <div class="invalid-feedback">Por favor ingrese su nombre completo</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="requester-id-number" class="form-label">Cédula/DNI/ID</label>
                            <?= $this->Form->text('requester_id_number', [
                                'class' => 'form-input',
                                'id' => 'requester-id-number',
                                'placeholder' => 'Número de identificación'
                            ]) ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="requester-email" class="form-label required">Correo Electrónico</label>
                            <?= $this->Form->email('requester_email', [
                                'class' => 'form-input',
                                'id' => 'requester-email',
                                'placeholder' => 'ejemplo@correo.com',
                                'required' => true
                            ]) ?>
                            <small class="form-text">Recibirá notificaciones en este correo</small>
                            <div class="invalid-feedback">Por favor ingrese un correo electrónico válido</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="requester-phone" class="form-label">Teléfono</label>
                            <?= $this->Form->text('requester_phone', [
                                'class' => 'form-input',
                                'id' => 'requester-phone',
                                'placeholder' => '+57 300 123 4567'
                            ]) ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="requester-address" class="form-label">Dirección</label>
                            <?= $this->Form->text('requester_address', [
                                'class' => 'form-input',
                                'id' => 'requester-address',
                                'placeholder' => 'Calle, número, barrio'
                            ]) ?>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="requester-city" class="form-label">Ciudad</label>
                            <?= $this->Form->text('requester_city', [
                                'class' => 'form-input',
                                'id' => 'requester-city',
                                'placeholder' => 'Ciudad'
                            ]) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Request Details Section -->
            <div class="form-section">
                <div class="form-section-header">
                    <div class="form-section-icon">
                        <i class="bi bi-file-text-fill"></i>
                    </div>
                    <h3 class="form-section-title">Detalles de la Solicitud</h3>
                </div>

                <div class="form-group">
                    <label for="subject" class="form-label required">Asunto</label>
                    <?= $this->Form->text('subject', [
                        'class' => 'form-input',
                        'id' => 'subject',
                        'placeholder' => 'Resuma brevemente su solicitud',
                        'required' => true,
                        'maxlength' => 255
                    ]) ?>
                    <div class="invalid-feedback">Por favor ingrese el asunto de su solicitud</div>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label required">Descripción Detallada</label>
                    <?= $this->Form->textarea('description', [
                        'class' => 'form-textarea',
                        'id' => 'description',
                        'rows' => 6,
                        'placeholder' => 'Describa detalladamente su petición, queja, reclamo o sugerencia. Incluya toda la información relevante.',
                        'required' => true
                    ]) ?>
                    <div class="char-counter" id="charCounter">
                        <span id="charCount">0</span> / 20 caracteres mínimos
                    </div>
                    <div class="invalid-feedback">La descripción debe tener al menos 20 caracteres</div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-paperclip"></i> Archivos Adjuntos (Opcional)
                    </label>
                    <div class="file-upload-wrapper">
                        <div class="file-upload-zone" id="fileUploadZone">
                            <div class="file-upload-icon">
                                <i class="bi bi-cloud-upload"></i>
                            </div>
                            <div class="file-upload-text">Haga clic o arrastre archivos aquí</div>
                            <div class="file-upload-hint">PDF, DOC, DOCX, JPG, PNG, ZIP - Máx. 10MB</div>
                        </div>
                        <?= $this->Form->file('attachments[]', [
                            'class' => 'file-input-hidden',
                            'id' => 'attachments',
                            'multiple' => true,
                            'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png,.zip'
                        ]) ?>
                    </div>
                    <small class="form-text">Formatos permitidos: PDF, DOC, DOCX, JPG, PNG, ZIP. Máximo 10MB por archivo.</small>
                </div>
            </div>

            <!-- Privacy Notice -->
            <div class="privacy-notice">
                <div class="privacy-notice-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div class="privacy-notice-content">
                    <p><strong>Protección de Datos:</strong> Sus datos personales serán tratados de acuerdo con nuestra política de privacidad y únicamente serán utilizados para dar respuesta a su solicitud.</p>
                </div>
            </div>

            <!-- Submit Button -->
            <?= $this->Form->button(
                '<i class="bi bi-send-fill"></i> Enviar PQRS',
                [
                    'type' => 'submit',
                    'class' => 'submit-button',
                    'escapeTitle' => false,
                    'id' => 'submitButton'
                ]
            ) ?>

            <?= $this->Form->end() ?>
        </div>

        <!-- Footer Info -->
        <div class="footer-info">
            <p>
                <i class="bi bi-clock"></i>
                Horario de atención: Lunes a Viernes, 8:00 AM - 5:00 PM
            </p>
            <p>
                <i class="bi bi-envelope"></i>
                Para consultas: servicioalcliente@operadoracafetera.com
            </p>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Loading Spinner -->
    <?= $this->Html->script('loading-spinner') ?>

    <!-- Form Scripts -->
    <script>
        // Form validation
        (function () {
            'use strict'
            const form = document.getElementById('pqrsForm');

            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                } else {
                    // Show loading spinner
                    if (typeof LoadingSpinner !== 'undefined') {
                        LoadingSpinner.show('Enviando PQRS...');
                    }
                }
                form.classList.add('was-validated');
            }, false);
        })();

        // Character counter for description
        const descriptionField = document.getElementById('description');
        const charCounter = document.getElementById('charCounter');
        const charCount = document.getElementById('charCount');
        const minLength = 20;

        descriptionField.addEventListener('input', function() {
            const currentLength = this.value.length;
            charCount.textContent = currentLength;

            if (currentLength < minLength) {
                charCounter.classList.remove('valid');
                charCounter.classList.add('warning');
                this.setCustomValidity('La descripción debe tener al menos ' + minLength + ' caracteres');
            } else {
                charCounter.classList.remove('warning');
                charCounter.classList.add('valid');
                this.setCustomValidity('');
            }
        });

        // File upload drag and drop
        const fileUploadZone = document.getElementById('fileUploadZone');
        const fileInput = document.getElementById('attachments');

        fileUploadZone.addEventListener('click', function() {
            fileInput.click();
        });

        fileUploadZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });

        fileUploadZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
        });

        fileUploadZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateFileUploadText(files);
            }
        });

        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                updateFileUploadText(this.files);
            }
        });

        function updateFileUploadText(files) {
            const fileUploadText = fileUploadZone.querySelector('.file-upload-text');
            const count = files.length;
            fileUploadText.textContent = count === 1
                ? '1 archivo seleccionado'
                : count + ' archivos seleccionados';
        }

        // Smooth scroll to first invalid field
        const form = document.getElementById('pqrsForm');
        form.addEventListener('invalid', function(e) {
            e.preventDefault();
            const firstInvalid = form.querySelector(':invalid');
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => firstInvalid.focus(), 500);
            }
        }, true);
    </script>
</body>
</html>
