<!-- Scrollable Comments Area -->
<div class="comments-scroll flex-grow-1 overflow-auto p-3 px-4">
    <!-- Original Message -->
    <div class="card rounded-0 p-3 mb-3 bg-light">
        <div class="d-flex gap-2 mb-2 align-items-center">
            <div class="avatar text-white rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 40px; height: 40px; background-color: #CD6A15;">
                <?= strtoupper(substr($ticket->requester->name, 0, 1)) ?>
            </div>
            <div class="flex-grow-1">
                <strong class="d-block"><?= h($ticket->requester->name) ?></strong>
                <small class="text-muted"><?= $this->TimeHuman->time($ticket->created) ?></small>
            </div>
        </div>
        <div class="lh-base small">
            <?= $ticket->description ?>
        </div>

        <?php
        // Filter ticket attachments: show non-inline files and orphan inline files
        $ticketAttachments = array_filter($ticket->attachments ?? [], function($a) use ($ticket) {
            // Skip if belongs to a comment
            if ($a->comment_id !== null) {
                return false;
            }

            // Include all non-inline attachments
            if (!$a->is_inline) {
                return true;
            }

            // For inline attachments, only show if not referenced in HTML (orphan)
            return $a->content_id && strpos($ticket->description, $a->content_id) === false;
        });
        ?>
        <?= $this->element('tickets/attachment_list', ['attachments' => $ticketAttachments]) ?>
    </div>

    <!-- Comments Thread -->
    <?php if (!empty($ticket->ticket_comments)): ?>
        <?php foreach ($ticket->ticket_comments as $comment): ?>
            <div class="card d-flex rounded-0 p-3 mb-3 <?= $comment->is_system_comment ? 'bg-warning bg-opacity-10 border-warning' : ($comment->comment_type === 'internal' ? 'bg-info bg-opacity-10 border-primary' : 'bg-light') ?>">
                <div class="d-flex mb-2 gap-2">
                    <div class="avatar bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center fw-normal shadow-sm flex-shrink-0" style="width: 40px; height: 40px;">
                        <?php if ($comment->is_system_comment): ?>
                            <i class="bi bi-robot"></i>
                        <?php else: ?>
                            <?= strtoupper(substr($comment->user->name, 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1 d-flex flex-column gap-0">
                        <div class="d-flex justify-content-between">
                            <?php if ($comment->is_system_comment): ?>
                                <strong>Sistema</strong>
                            <?php else: ?>
                                <strong><?= h($comment->user->name) ?></strong>
                            <?php endif; ?>
                            <div>
                                <?php if ($comment->comment_type === 'internal'): ?>
                                    <span class="badge bg-secondary rounded-0 ms-2">Nota interna</span>
                                <?php endif; ?>
                                <?php if ($comment->user_id === 1 && !$comment->is_system_comment): ?>
                                    <span class="badge border border-secondary text-secondary rounded-0 ms-2">Agente</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <small class="text-muted"><?= $this->TimeHuman->time($comment->created) ?></small>
                    </div>
                </div>
                <div class="lh-base small">
                    <?= $comment->body ?>
                </div>

                <?php
                // Filter comment attachments: show non-inline files and orphan inline files
                $commentAttachments = array_filter($ticket->attachments ?? [], function($a) use ($comment) {
                    // Must belong to this comment
                    if ($a->comment_id !== $comment->id) {
                        return false;
                    }

                    // Include all non-inline attachments
                    if (!$a->is_inline) {
                        return true;
                    }

                    // For inline attachments, only show if not referenced in HTML (orphan)
                    return $a->content_id && strpos($comment->body, $a->content_id) === false;
                });
                ?>
                <?= $this->element('tickets/attachment_list', ['attachments' => $commentAttachments]) ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>