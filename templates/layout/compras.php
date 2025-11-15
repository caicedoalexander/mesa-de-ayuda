<!DOCTYPE html>
<html lang="es">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        <?= $this->fetch('title') ?> - <?= h($systemTitle ?? 'Sistema de Soporte') ?>
    </title>
    <?= $this->Html->meta('icon') ?>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <!-- Custom Styles -->
    <?= $this->Html->css(['styles']) ?>

    <!-- jQuery (required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap 5 JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/es.js"></script>

    <!-- Select2 Custom Initialization -->
    <?= $this->Html->script('select2-init') ?>
    <!-- Flash Messages Auto-Hide -->
    <?= $this->Html->script('flash-messages') ?>
    
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
</head>
<body>
    <!-- Admin Navigation -->
    <nav class="top-navbar" style="max-height: 53px;">
        <div class="d-flex justify-content-between align-items-center ps-2 pe-3">
            <div class="d-flex gap-2 align-items-center">
                <div class="bg-white d-flex justify-content-center aling-items-center rounded-circle" style="width: 42px; height: 42px;">
                    <img class="my-auto" src="<?= $this->Url->build('img/logo.png') ?>" alt="Logo" height="45">
                </div>
                <h2 class="fs-5 m-0"><?= h($systemTitle ?? 'Sistema de Soporte') ?></h2>
            </div>
            <div class="nav-menu d-flex align-items-center gap-1 py-2">
                <?= $this->Html->link('<i class="bi bi-speedometer2"></i> Dashboard', ['prefix' => false, 'controller' => 'Tickets', 'action' => 'index'], ['escape' => false]) ?>
                <?= $this->Html->link('<i class="bi bi-ticket"></i> Tickets', ['prefix' => false, 'controller' => 'Tickets', 'action' => 'index'], ['escape' => false]) ?>
                <?= $this->Html->link('<i class="bi bi-gear"></i> Ajustes de usuario', ['prefix' => 'Admin', 'controller' => 'Settings', 'action' => 'editUser', $currentUser->id], ['escape' => false]) ?>
                <div class="nav-user">
                    <span class="small"><i class="bi bi-person-circle me-1"></i> <?= h($currentUser->name) ?></span>
                    <?= $this->Html->link('<i class="bi bi-box-arrow-right"></i> Salir', ['prefix' => false, 'controller' => 'Users', 'action' => 'logout'], ['class' => 'btn-logout', 'escape' => false]) ?>
                </div>
            </div>
        </div>
    </nav>

    <div style="max-height: calc(100vh - 53px);">
        <?= $this->Flash->render() ?>
        <div class="d-flex" style="height: calc(100vh - 53px);">
            <?= $this->fetch('content') ?>
        </div>
    </div>
</body>
</html>
