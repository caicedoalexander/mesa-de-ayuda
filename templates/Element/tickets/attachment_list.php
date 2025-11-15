<?php
/**
 * Attachment List Element
 *
 * Renders a list of attachments with icons and download links
 *
 * @var array $attachments Array of Attachment entities
 */

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
?>

<?php if (!empty($attachments)): ?>
    <div class="mt-3 pt-3 border-top">
        <strong class="d-block mb-2 small text-muted">Adjuntos:</strong>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($attachments as $attachment): ?>
                <?php
                $ext = strtolower(pathinfo($attachment->original_filename, PATHINFO_EXTENSION));
                $icon = $iconMap[$ext]['icon'] ?? 'bi-file-earmark';
                $color = $iconMap[$ext]['color'] ?? 'text-secondary';
                $sizeKB = number_format($attachment->file_size / 1024, 1);
                ?>
                <?= $this->Html->link(
                    '<i class="bi ' . $icon . ' ' . $color . ' fs-5 me-1"></i> ' .
                    '<span class="small">' . h($attachment->original_filename) . '</span> ' .
                    '<span class="badge bg-light text-dark border">' . $sizeKB . ' KB</span>',
                    ['action' => 'downloadAttachment', $attachment->id],
                    [
                        'class' => 'd-flex align-items-center gap-1 px-3 py-2 border rounded text-decoration-none bg-white hover-bg-light',
                        'escape' => false,
                        'title' => 'Descargar ' . h($attachment->original_filename)
                    ]
                ) ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
