<!DOCTYPE html>
<html lang="es">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar Sesión - <?= h($systemTitle ?? 'Sistema de Soporte') ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- Custom Styles -->
    <?= $this->Html->css(['styles']) ?>
</head>
<body style="max-width: 600px; margin: auto;">
    <div class="p-4 d-flex flex-column align-items-center justify-content-center" style="min-height: 100dvh;">
        <div class="px-4">
            <div class="d-flex align-items-center gap-3 mb-2">
                <img src="<?= $this->Url->build('favicon.ico') ?>" width="60">
                <h2><?= h($systemTitle ?? 'Sistema de Soporte') ?></h2>
            </div>
            <p class="text-muted">Inicia sesión para continuar</p>
        </div>

        <?= $this->Flash->render() ?>

        <div class="w-100 px-5">
            <?= $this->Form->create(null) ?>
                <div class="mb-3">
                    <label for="email" class="form-label">
                        Correo Electrónico
                    </label>
                    <?= $this->Form->email('email', [
                        'class' => 'form-control',
                        'id' => 'email',
                        'placeholder' => 'ejemplo@correo.com',
                        'required' => true,
                        'autofocus' => true
                    ]) ?>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">
                        Contraseña
                    </label>
                    <?= $this->Form->password('password', [
                        'class' => 'form-control',
                        'id' => 'password',
                        'placeholder' => '••••••••',
                        'required' => true
                    ]) ?>
                </div>

                <?= $this->Form->button('Iniciar Sesión', [
                    'class' => 'btn w-100 text-white',
                    'style' => 'background-color: #CD6A15',
                    'escape' => false
                ]) ?>
            <?= $this->Form->end() ?>
        </div>
        <div class="text-center mt-4 pt-3 border-top">
            <small class="text-muted">
                &copy; <?= date('Y') ?> <?= h($systemTitle ?? 'Sistema de Soporte') ?> - Todos los derechos reservados
            </small>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Flash Messages Auto-Hide -->
    <?= $this->Html->script('flash-messages') ?>
</body>
</html>
