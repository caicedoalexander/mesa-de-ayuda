<!-- Fixed Header -->
<div class="ticket-header py-3 px-4 border-bottom bg-white">
    <div class="d-flex justify-content-between gap-5 align-items-center small">
        <div class="d-flex flex-column">
            <h1 class="fs-5 fw-semibold m-0"><?= h($ticket->subject) ?></h1>
            <span><strong>Ticket:</strong> <?= h($ticket->ticket_number) ?></span>
        </div>
        <div class="d-flex flex-column">
            <span class="text-muted lh-1"><strong>Creado:</strong> <?= $this->TimeHuman->long($ticket->created) ?></span>
            <?php if ($ticket->resolved_at && $ticket->status === 'resuelto'): ?>
                <span class="text-success lh-1"><strong>Resuelto:</strong> <?= $this->TimeHuman->long($ticket->resolved_at) ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>
