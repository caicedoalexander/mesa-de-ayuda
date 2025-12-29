<?php
/**
 * Channel Distribution Display
 *
 * @var array $channelDistribution Channel => count mapping
 */

if (empty($channelDistribution)) {
    return; // Don't display if no channel data
}

$channelLabels = [
    'web' => 'Web/Formulario',
    'email' => 'Email',
    'whatsapp' => 'WhatsApp',
    'phone' => 'Teléfono',
    'presencial' => 'Presencial',
];

$channelIcons = [
    'web' => 'bi-globe',
    'email' => 'bi-envelope',
    'whatsapp' => 'bi-whatsapp',
    'phone' => 'bi-telephone',
    'presencial' => 'bi-person',
];

$total = array_sum($channelDistribution);
?>

<div class="card border-0 shadow-sm h-100">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-broadcast"></i> Por Canal</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($channelDistribution)): ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <tbody>
                        <?php foreach ($channelDistribution as $channel => $count): ?>
                            <?php
                            $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                            $label = $channelLabels[$channel] ?? ucfirst($channel);
                            $icon = $channelIcons[$channel] ?? 'bi-circle';
                            ?>
                            <tr>
                                <td style="width: 40px;">
                                    <i class="<?= $icon ?> text-primary"></i>
                                </td>
                                <td>
                                    <strong><?= h($label) ?></strong>
                                </td>
                                <td style="width: 80px;" class="text-end">
                                    <span class="badge bg-secondary"><?= number_format($count) ?></span>
                                </td>
                                <td style="width: 80px;" class="text-end">
                                    <small class="text-muted"><?= $percentage ?>%</small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">No hay datos de canal disponibles.</p>
        <?php endif; ?>
    </div>
</div>
