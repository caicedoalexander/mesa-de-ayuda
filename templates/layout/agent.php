<!DOCTYPE html>
<html lang="es">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        <?= $this->fetch('title') ?> - Agente - <?= h($systemTitle ?? 'Sistema de Soporte') ?>
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

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
</head>
<body>
    <!-- Agent Navigation -->
    <nav class="top-navbar agent">
        <div class="nav-container">
            <div class="nav-brand">
                <h2><?= h($systemTitle ?? 'Sistema de Soporte') ?></h2>
                <span class="role-badge">Agente</span>
            </div>
            <div class="nav-menu">
                <?= $this->Html->link('<i class="bi bi-speedometer2"></i> Dashboard', ['controller' => 'Tickets', 'action' => 'index'], ['escape' => false]) ?>
                <?= $this->Html->link('<i class="bi bi-ticket"></i> Tickets', ['controller' => 'Tickets', 'action' => 'index'], ['escape' => false]) ?>
                <?= $this->Html->link('<i class="bi bi-inbox"></i> Mis Tickets', ['controller' => 'Tickets', 'action' => 'index', '?' => ['view' => 'mis_tickets']], ['escape' => false]) ?>
                <div class="nav-user">
                    <span><i class="bi bi-person-circle"></i> <?= h($currentUser->name) ?></span>
                    <?= $this->Html->link('<i class="bi bi-box-arrow-right"></i> Salir', ['controller' => 'Users', 'action' => 'logout'], ['class' => 'btn-logout', 'escape' => false]) ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <?= $this->Flash->render() ?>
        <?= $this->fetch('content') ?>
    </div>

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

    <?= $this->fetch('script') ?>
</body>
</html>
