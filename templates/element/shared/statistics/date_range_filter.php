<?php
/**
 * Date Range Filter Form
 *
 * @var array $filters Current filters
 * @var string $action Action name to submit to
 */

$dateRange = $filters['date_range'] ?? 'all';
$startDate = $filters['start_date'] ?? '';
$endDate = $filters['end_date'] ?? '';
?>

<div class="modern-card mb-4" data-animate="fade-up" data-delay="100">
    <div class="p-3">
        <form method="get" action="<?= $this->Url->build(['action' => $action]) ?>" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="font-size: 0.875rem; color: var(--gray-700);">
                    <i class="bi bi-calendar-range me-1"></i>Período
                </label>
                <select name="range" class="form-select" id="date-range-select" style="border-radius: 8px; border: 1px solid var(--gray-300);">
                    <option value="all" <?= $dateRange === 'all' ? 'selected' : '' ?>>Todo el tiempo</option>
                    <option value="today" <?= $dateRange === 'today' ? 'selected' : '' ?>>Hoy</option>
                    <option value="week" <?= $dateRange === 'week' ? 'selected' : '' ?>>Últimos 7 días</option>
                    <option value="month" <?= $dateRange === 'month' ? 'selected' : '' ?>>Últimos 30 días</option>
                    <option value="custom" <?= $dateRange === 'custom' ? 'selected' : '' ?>>Rango personalizado</option>
                </select>
            </div>
            <div class="col-md-3" id="start-date-field" style="display: <?= $dateRange === 'custom' ? 'block' : 'none' ?>;">
                <label class="form-label fw-semibold" style="font-size: 0.875rem; color: var(--gray-700);">Desde</label>
                <input type="date" name="start_date" class="form-control" value="<?= h($startDate) ?>" style="border-radius: 8px; border: 1px solid var(--gray-300);">
            </div>
            <div class="col-md-3" id="end-date-field" style="display: <?= $dateRange === 'custom' ? 'block' : 'none' ?>;">
                <label class="form-label fw-semibold" style="font-size: 0.875rem; color: var(--gray-700);">Hasta</label>
                <input type="date" name="end_date" class="form-control" value="<?= h($endDate) ?>" style="border-radius: 8px; border: 1px solid var(--gray-300);">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn w-100" style="background: linear-gradient(135deg, #00A85E 0%, #00d474 100%); color: white; border: none; border-radius: 8px; font-weight: 600; padding: 0.5rem 1rem; transition: all 0.2s;">
                    <i class="bi bi-funnel me-1"></i> Aplicar Filtro
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const rangeSelect = document.getElementById('date-range-select');
    const startDateField = document.getElementById('start-date-field');
    const endDateField = document.getElementById('end-date-field');

    if (rangeSelect) {
        rangeSelect.addEventListener('change', function() {
            const isCustom = this.value === 'custom';
            startDateField.style.display = isCustom ? 'block' : 'none';
            endDateField.style.display = isCustom ? 'block' : 'none';
        });
    }

    // Add hover effect to submit button
    const submitBtn = document.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-1px)';
            this.style.boxShadow = '0 4px 12px rgba(0, 168, 94, 0.3)';
        });
        submitBtn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    }
})();
</script>
