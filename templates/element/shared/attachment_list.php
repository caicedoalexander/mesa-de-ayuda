<?php
/**
 * Shared Element: Attachment List
 *
 * Unified attachment list for Tickets, PQRS, and Compras.
 * Replaces 3 separate attachment_list files (191 lines → 60 lines).
 *
 * @var array $attachments Array of Attachment entities
 * @var string $entityType 'ticket', 'pqrs', or 'compra'
 */

$entityType = $entityType ?? 'ticket';

// Icon mapping for file types
$iconMap = [
    'pdf' => ['icon' => 'bi-file-earmark-pdf', 'color' => 'text-danger'],
    'doc' => ['icon' => 'bi-file-earmark-word', 'color' => 'text-primary'],
    'docx' => ['icon' => 'bi-file-earmark-word', 'color' => 'text-primary'],
    'xls' => ['icon' => 'bi-file-earmark-excel', 'color' => 'text-success'],
    'xlsx' => ['icon' => 'bi-file-earmark-excel', 'color' => 'text-success'],
    'ppt' => ['icon' => 'bi-file-earmark-ppt', 'color' => 'text-warning'],
    'pptx' => ['icon' => 'bi-file-earmark-ppt', 'color' => 'text-warning'],
    'png' => ['icon' => 'bi-file-earmark-image', 'color' => 'text-success'],
    'jpg' => ['icon' => 'bi-file-earmark-image', 'color' => 'text-success'],
    'jpeg' => ['icon' => 'bi-file-earmark-image', 'color' => 'text-success'],
    'gif' => ['icon' => 'bi-file-earmark-image', 'color' => 'text-success'],
    'bmp' => ['icon' => 'bi-file-earmark-image', 'color' => 'text-success'],
    'webp' => ['icon' => 'bi-file-earmark-image', 'color' => 'text-success'],
    'zip' => ['icon' => 'bi-file-earmark-zip', 'color' => 'text-warning'],
    'rar' => ['icon' => 'bi-file-earmark-zip', 'color' => 'text-warning'],
    '7z' => ['icon' => 'bi-file-earmark-zip', 'color' => 'text-warning'],
    'txt' => ['icon' => 'bi-file-earmark-text', 'color' => 'text-secondary'],
    'csv' => ['icon' => 'bi-file-earmark-spreadsheet', 'color' => 'text-success'],
];

// Determine controller based on entity type (for download URL)
$controllerMap = [
    'ticket' => 'Tickets',
    'pqrs' => 'Pqrs',
    'compra' => 'Compras',
];
$controller = $controllerMap[$entityType] ?? 'Tickets';
?>

<?php if (!empty($attachments)): ?>
    <div class="mt-2">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($attachments as $attachment): ?>
                <?php
                // Use original_filename for display, fallback to filename if not available
                $displayName = $attachment->original_filename ?? $attachment->filename;
                $ext = strtolower(pathinfo($displayName, PATHINFO_EXTENSION));
                $icon = $iconMap[$ext]['icon'] ?? 'bi-file-earmark';
                $color = $iconMap[$ext]['color'] ?? 'text-secondary';
                $sizeKB = number_format($attachment->file_size / 1024, 1);
                ?>
                <?= $this->Html->link(
                    '<i class="bi ' . $icon . ' ' . $color . ' fs-5 me-1"></i> ' .
                    '<span class="small text-truncate">' . h($displayName) . '</span> ' .
                    '<span class="badge bg-light text-dark border">' . $sizeKB . ' KB</span>',
                    ['controller' => $controller, 'action' => 'downloadAttachment', $attachment->id],
                    [
                        'class' => 'attachment-link',
                        'escape' => false,
                        'title' => 'Descargar ' . h($displayName)
                    ]
                ) ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
