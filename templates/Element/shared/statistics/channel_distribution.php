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
    'email' => 'bi-envelope-fill',
    'whatsapp' => 'bi-whatsapp',
    'phone' => 'bi-telephone-fill',
    'presencial' => 'bi-person-fill',
];

$channelColors = [
    'web' => 'var(--brand-green)',
    'email' => 'var(--brand-orange)',
    'whatsapp' => '#25D366',
    'phone' => 'var(--info)',
    'presencial' => '#6B7280',
];

$total = array_sum($channelDistribution);
?>

<div class="modern-card chart-card h-100" data-animate="fade-up" data-delay="900">
    <div class="chart-header">
        <h5 class="chart-title">
            Por Canal
        </h5>
    </div>
    <div class="p-3">
        <?php if (!empty($channelDistribution)): ?>
            <?php foreach ($channelDistribution as $channel => $count): ?>
                <?php
                $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                $label = $channelLabels[$channel] ?? ucfirst($channel);
                $icon = $channelIcons[$channel] ?? 'bi-circle';
                $color = $channelColors[$channel] ?? '#6B7280';
                ?>
                <div class="metric-card mb-3" style="border-left: 3px solid <?= $color ?>; background: rgba(0,0,0,0.02); border-radius: 10px;">
                    <div class="d-flex align-items-center gap-3 flex-1">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: <?= $color ?>; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.25rem;">
                            <i class="<?= $icon ?>"></i>
                        </div>
                        <div class="flex-1">
                            <div style="font-weight: 600; color: var(--gray-900); font-size: 0.9375rem;">
                                <?= h($label) ?>
                            </div>
                            <div style="font-size: 0.8125rem; color: var(--gray-500); margin-top: 2px;">
                                <?= number_format($count) ?> registros
                            </div>
                        </div>
                        <div class="text-end">
                            <div style="font-size: 1.5rem; font-weight: 700; color: var(--gray-900); line-height: 1;">
                                <?= $percentage ?>%
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted text-center py-4 mb-0">No hay datos de canal disponibles.</p>
        <?php endif; ?>
    </div>
</div>
