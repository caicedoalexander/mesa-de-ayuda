<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Ticket $ticket
 */
$this->assign('title', 'Ticket #' . $ticket->ticket_number);
?>

<div class="ticket-view-container">
    <?= $this->element('tickets/left_sidebar', ['ticket' => $ticket, 'agents' => $agents, 'tags' => $tags]) ?>

    <!-- Main Content Area -->
    <div class="main-content d-flex flex-column bg-white">
        <?= $this->element('tickets/header', ['ticket' => $ticket]) ?>
        <?= $this->element('tickets/comments_area', ['ticket' => $ticket]) ?>
        <?= $this->element('tickets/reply_editor', ['ticket' => $ticket]) ?>
    </div>

    <?= $this->element('tickets/right_sidebar', ['ticket' => $ticket]) ?>
</div>

<?= $this->element('tickets/styles_and_scripts') ?>
