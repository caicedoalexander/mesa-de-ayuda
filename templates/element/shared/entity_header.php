<?php
/**
 * Shared Element: Entity Header
 *
 * Unified header for Tickets, PQRS, and Compras view pages.
 * Eliminates 102 lines of duplicated code across 3 separate header files.
 *
 * @var object $entity Entity (ticket, pqrs, or compra)
 * @var string $entityType 'ticket', 'pqrs', or 'compra'
 * @var array $entityMetadata Metadata from controller
 * @var array $resolvedStatuses Array of statuses considered "resolved"
 */

$meta = $entityMetadata;
$number = $entity->{$meta['numberField']};
$subject = $entity->{$meta['subjectField']};
$created = $entity->{$meta['createdField']};
$resolvedAt = $entity->{$meta['resolvedField']} ?? null;
$status = $entity->{$meta['statusField']};
$isResolved = in_array($status, $resolvedStatuses);
?>

<!-- Fixed Header -->
<div class="py-3 px-4 shadow-sm bg-white" style="border-radius: 8px;">
    <div class="d-flex justify-content-between gap-5 small">
        <div class="d-flex flex-column justify-content-between" style="min-width: 0; flex: 1;">
            <div class="marquee-container <?= $meta['marqueeClass'] ?>-container" style="max-width: 600px;">
                <h1 class="fs-5 fw-semibold m-0 <?= $meta['marqueeClass'] ?>-text"><?= h($subject) ?></h1>
            </div>
            <span><strong class="text-muted"><?= $meta['numberLabel'] ?>:</strong> <?= h($number) ?></span>
        </div>
        <div class="d-flex flex-column justify-content-between">
            <span class="text-muted lh-1">
                <strong class="text-muted">Creado:</strong>
                <?= $this->TimeHuman->long($created) ?>
            </span>
            <?php if ($resolvedAt && $isResolved): ?>
                <span class="text-success lh-1">
                    <strong>Resuelto:</strong>
                    <?= $this->TimeHuman->long($resolvedAt) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof MarqueeText !== 'undefined') {
            MarqueeText.init('.<?= $meta['marqueeClass'] ?>-container', '.<?= $meta['marqueeClass'] ?>-text', {
                speed: 60,
                minDuration: 10,
                hoverDelay: 0,
                resetOnLeave: true
            });
        }
    });
</script>
