<?php
/**
 * Shared Element: Comments List
 *
 * Lista de comentarios reutilizable para Tickets y PQRS
 *
 * @var array $params Parámetros del element
 * - object $entity Entidad completa (Ticket o Pqrs)
 * - string $entityType 'ticket' o 'pqrs'
 * - array $comments Lista de comentarios
 * - string $description Descripción original de la entidad
 * - array $attachments Lista de todos los attachments de la entidad
 */

// Variables passed directly from element() call
// $entity, $entityType, $comments, $description, $attachments
$entityType = $entityType ?? 'ticket';
$comments = $comments ?? [];
$description = $description ?? ($entity->description ?? '');
$attachments = $attachments ?? ($entity->compras_attachments ?? []);

// Nombres de campos según entityType
if ($entityType === 'ticket') {
    $commentIdField = 'comment_id';
} elseif ($entityType === 'pqrs') {
    $commentIdField = 'pqrs_comment_id';
} else { // compra
    $commentIdField = 'compras_comment_id';
}

// ✨ ALWAYS use shared/attachment_list for consistency
$attachmentElementPath = 'shared/attachment_list';

// Datos del requester
if ($entityType === 'ticket' || $entityType === 'compra') {
    $requesterName = h($entity->requester->name ?? 'Desconocido');
    $requesterUser = $entity->requester ?? null;
} else { // pqrs
    $requesterName = h($entity->requester_name ?? 'Anónimo');
    $requesterUser = null; // PQRS no tiene objeto User
}
?>

<!-- Scrollable Comments Area -->
<div class="comments-scroll flex-grow-1 overflow-auto py-3 px-2 mx-2">
    <!-- Original Message -->
    <div class="card border-0 p-3 mb-3" style="background-color: transparent !important;">
        <div class="d-flex gap-2 mb-2 align-items-start">
            <?php if (($entityType === 'ticket' || $entityType === 'compra') && $requesterUser): ?>
                <?= $this->User->profileImageTag($requesterUser, ['width' => '40', 'height' => '40', 'class' => 'rounded-circle flex-shrink-0 object-fit-cover']) ?>
            <?php else: ?>
                <!-- Avatar genérico para PQRS -->
                <div class="avatar text-white rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                    style="width: 40px; height: 40px; background-color: #CD6A15;">
                    <?= strtoupper(substr($requesterName, 0, 2)) ?>
                </div>
            <?php endif; ?>

            <div class="d-flex flex-grow-1 flex-column">
                <div class="d-flex gap-2 align-items-center">
                    <strong class="d-block"><?= $requesterName ?></strong>
                    <i>•</i>
                    <small class="text-muted"><?= $this->TimeHuman->time($entity->created) ?></small>
                </div>

                <?php if (in_array($entityType, ['ticket', 'compra'])): ?>
                    <?php
                    // Show email recipients if available (for tickets and compras from Gmail)
                    $emailTo = $entity->email_to_array ?? [];
                    $emailCc = $entity->email_cc_array ?? [];
                    if (!empty($emailTo) || !empty($emailCc)):
                        // Combine all recipients for collapsed view (names only)
                        $allRecipients = array_merge($emailTo, $emailCc);
                        $namesOnly = array_map(function($recipient) {
                            return h($recipient['name']);
                        }, $allRecipients);
                        $namesString = implode(', ', $namesOnly);

                        // Prepare detailed lists
                        $toListDetailed = array_map(function($recipient) {
                            $name = h($recipient['name']);
                            $email = h($recipient['email']);
                            return $name !== $email ? "{$name} &lt;{$email}&gt;" : $email;
                        }, $emailTo);

                        $ccListDetailed = array_map(function($recipient) {
                            $name = h($recipient['name']);
                            $email = h($recipient['email']);
                            return $name !== $email ? "{$name} &lt;{$email}&gt;" : $email;
                        }, $emailCc);

                        // Generate unique ID for this entity's recipients section
                        $recipientsId = 'recipients-' . $entity->id;
                    ?>
                        <div class="small">
                            <!-- Collapsed View (Default) -->
                            <div id="<?= $recipientsId ?>-collapsed" class="recipients-collapsed">
                                <div class="d-flex flex-column gap-0" style="font-size: 12px;">
                                    <span class="">
                                        <strong>Para:</strong> <?= $namesString ?>
                                    </span>
                                    <a href="#" class="text-nowrap" style="font-size: 12px;" onclick="toggleRecipients('<?= $recipientsId ?>'); return false;">
                                        Mostrar más
                                    </a>
                                </div>
                            </div>

                            <!-- Expanded View (Hidden by default) -->
                            <div id="<?= $recipientsId ?>-expanded" class="recipients-expanded" style="display: none;">
                                <?php if (!empty($emailTo)): ?>
                                    <div class="mb-0" style="font-size: 12px;">
                                        <strong>Para:</strong> <?= implode(', ', $toListDetailed) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($emailCc)): ?>
                                    <div class="mb-0" style="font-size: 12px;">
                                        <strong>CC:</strong> <?= implode(', ', $ccListDetailed) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mb-0" style="font-size: 12px;">
                                    <a href="#" class="text-nowrap" style="font-size: 12px;" onclick="toggleRecipients('<?= $recipientsId ?>'); return false;">
                                        Mostrar menos
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="lh-base small p-3 rounded" style="background-color: rgba(31, 115, 183, 0.1);">
            <?= $this->Sanitize->html($description) ?>
        </div>

        <?php
        // Filter entity attachments: show non-inline files and orphan inline files
        $entityAttachments = array_filter($attachments, function ($a) use ($commentIdField, $description) {
            // Skip if belongs to a comment
            if ($a->$commentIdField !== null) {
                return false;
            }

            // Include all non-inline attachments
            if (!$a->is_inline) {
                return true;
            }

            // For inline attachments, only show if not referenced in HTML (orphan)
            return $a->content_id && strpos($description, $a->content_id) === false;
        });
        ?>
        <?= $this->element('shared/attachment_list', ['attachments' => $entityAttachments, 'entityType' => $entityType]) ?>
    </div>

    <!-- Comments Thread -->
    <?php if (!empty($comments)): ?>
        <?php foreach ($comments as $comment): ?>
            <?php if ($comment->is_system_comment): ?>
                <div class="bg-warning bg-opacity-10 mb-3 border-warning fw-bold py-2 shadow-sm w-50 mx-auto text-center" style="font-size: 13px !important; border-radius: 8px;">
                    <?= $this->Sanitize->html($comment->body) ?>
                </div>
            <?php else: ?>
                <div class="card border-0 p-3 mb-3" style="background-color: transparent !important;">
                    <div class="d-flex mb-2 gap-2">
                        <?= $this->User->profileImageTag($comment->user, ['width' => '40', 'height' => '40', 'class' => 'rounded-circle flex-shrink-0 object-fit-cover']) ?>
                        <div class="flex-grow-1 d-flex flex-column gap-0">
                            <div class="d-flex gap-2 align-items-center">
                                <strong><?= h($comment->user->name) ?></strong>
                                <i>•</i>
                                <small class="text-muted"><?= $this->TimeHuman->time($comment->created) ?></small>
                            </div>

                            <?php
                            // Show email recipients if available (for public comments with email_to/email_cc)
                            if ($comment->comment_type === 'public'):
                                $commentEmailTo = !empty($comment->email_to) ? json_decode($comment->email_to, true) : [];
                                $commentEmailCc = !empty($comment->email_cc) ? json_decode($comment->email_cc, true) : [];

                                if (!empty($commentEmailTo) || !empty($commentEmailCc)):
                                    // Combine all recipients for collapsed view (names only)
                                    $allCommentRecipients = array_merge($commentEmailTo, $commentEmailCc);
                                    $commentNamesOnly = array_map(function($recipient) {
                                        return h($recipient['name'] ?? $recipient['email']);
                                    }, $allCommentRecipients);
                                    $commentNamesString = implode(', ', $commentNamesOnly);

                                    // Prepare detailed lists
                                    $commentToListDetailed = array_map(function($recipient) {
                                        $name = h($recipient['name'] ?? $recipient['email']);
                                        $email = h($recipient['email']);
                                        return $name !== $email ? "{$name} &lt;{$email}&gt;" : $email;
                                    }, $commentEmailTo);

                                    $commentCcListDetailed = array_map(function($recipient) {
                                        $name = h($recipient['name'] ?? $recipient['email']);
                                        $email = h($recipient['email']);
                                        return $name !== $email ? "{$name} &lt;{$email}&gt;" : $email;
                                    }, $commentEmailCc);

                                    // Generate unique ID for this comment's recipients section
                                    $commentRecipientsId = 'comment-recipients-' . $comment->id;
                            ?>
                                    <div class="small mt-1">
                                        <!-- Collapsed View (Default) -->
                                        <div id="<?= $commentRecipientsId ?>-collapsed" class="recipients-collapsed">
                                            <div class="d-flex flex-column gap-0" style="font-size: 12px;">
                                                <span class="">
                                                    <strong>Para:</strong> <?= $commentNamesString ?>
                                                </span>
                                                <a href="#" class="text-nowrap" style="font-size: 12px;" onclick="toggleRecipients('<?= $commentRecipientsId ?>'); return false;">
                                                    Mostrar más
                                                </a>
                                            </div>
                                        </div>

                                        <!-- Expanded View (Hidden by default) -->
                                        <div id="<?= $commentRecipientsId ?>-expanded" class="recipients-expanded" style="display: none;">
                                            <?php if (!empty($commentEmailTo)): ?>
                                                <div class="mb-0" style="font-size: 12px;">
                                                    <strong>Para:</strong> <?= implode(', ', $commentToListDetailed) ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($commentEmailCc)): ?>
                                                <div class="mb-0" style="font-size: 12px;">
                                                    <strong>CC:</strong> <?= implode(', ', $commentCcListDetailed) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="mb-0" style="font-size: 12px;">
                                                <a href="#" class="text-nowrap" style="font-size: 12px;" onclick="toggleRecipients('<?= $commentRecipientsId ?>'); return false;">
                                                    Mostrar menos
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                            <?php
                                endif;
                            endif;
                            ?>
                        </div>
                    </div>
                    <div class="lh-base small p-3 rounded <?= $comment->is_system_comment ? 'bg-warning bg-opacity-10 border-warning' : ($comment->comment_type === 'internal' ? 'bg-warning bg-opacity-10' : 'bg-secondary bg-opacity-10') ?>">
                        <?= $this->Sanitize->html($comment->body) ?>
                    </div>

                    <?php
                    // Filter comment attachments: show non-inline files and orphan inline files
                    $commentAttachments = array_filter($attachments, function ($a) use ($comment, $commentIdField) {
                        // Must belong to this comment
                        if ($a->$commentIdField !== $comment->id) {
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
                    <?= $this->element('shared/attachment_list', ['attachments' => $commentAttachments, 'entityType' => $entityType]) ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
