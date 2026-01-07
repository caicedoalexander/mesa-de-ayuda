<?php
/**
 * Shared Element: Attachment Item
 *
 * Renderiza un solo archivo adjunto con icono y link de descarga
 *
 * @var object $attachment Entidad Attachment
 * @var string $entityType 'ticket' o 'pqrs' (para generar URL correcta)
 */

// Variables passed directly from element() call
$entityType = $entityType ?? 'ticket';

// Icon mapping para tipos de archivo
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

// Usar original_filename para display, fallback a filename
$displayName = $attachment->original_filename ?? $attachment->filename;
$ext = strtolower(pathinfo($displayName, PATHINFO_EXTENSION));
$icon = $iconMap[$ext]['icon'] ?? 'bi-file-earmark';
$color = $iconMap[$ext]['color'] ?? 'text-secondary';
$sizeKB = number_format($attachment->file_size / 1024, 1);

// Generar URL correcta según entityType
$downloadAction = $entityType === 'ticket' ? 'downloadAttachment' : 'downloadAttachment';
$controller = $entityType === 'ticket' ? 'Tickets' : 'Pqrs';
?>

<?= $this->Html->link(
    '<i class="bi ' . $icon . ' ' . $color . ' fs-5 me-1"></i> ' .
    '<span class="small text-truncate">' . h($displayName) . '</span> ' .
    '<span class="badge bg-light text-dark border">' . $sizeKB . ' KB</span>',
    ['controller' => $controller, 'action' => $downloadAction, $attachment->id],
    [
        'class' => 'attachment-link',
        'escape' => false,
        'title' => 'Descargar ' . h($displayName)
    ]
) ?>
