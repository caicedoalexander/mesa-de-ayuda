<?php
declare(strict_types=1);

namespace App\Service\Traits;

use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Cache\Cache;
use Cake\Log\Log;
use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use DateTimeInterface;

/**
 * SLAManagementTrait
 *
 * Shared SLA (Service Level Agreement) calculation and breach detection logic
 * for both PQRS and Compras modules.
 *
 * Provides:
 * - Type-based SLA calculation for PQRS (peticion, queja, reclamo, sugerencia)
 * - Single SLA configuration for Compras
 * - Breach detection for both first response and resolution SLAs
 * - 24/7 calendar day calculation (not business hours)
 *
 * @package App\Service\Traits
 */
trait SLAManagementTrait
{
    use LocatorAwareTrait;

    /**
     * Cache for loaded SLA configuration
     *
     * @var array|null
     */
    private ?array $slaConfigCache = null;

    /**
     * Calculate PQRS SLA deadlines based on type
     *
     * @param string $type PQRS type (peticion, queja, reclamo, sugerencia)
     * @param \DateTimeInterface|null $createdDate Creation date (defaults to now)
     * @return array Array with 'first_response' and 'resolution' DateTime objects
     */
    public function calculatePqrsSLA(string $type, ?DateTimeInterface $createdDate = null): array
    {
        $config = $this->getSLAConfig();
        $created = $createdDate ?? new DateTime();

        // Get SLA days from config based on PQRS type
        $firstResponseKey = "sla_pqrs_{$type}_first_response_days";
        $resolutionKey = "sla_pqrs_{$type}_resolution_days";

        $firstResponseDays = (int)($config[$firstResponseKey] ?? 2); // Default: 2 days
        $resolutionDays = (int)($config[$resolutionKey] ?? 5); // Default: 5 days

        // Calculate deadlines (24/7 calendar days)
        $firstResponseDue = (clone $created)->modify("+{$firstResponseDays} days");
        $resolutionDue = (clone $created)->modify("+{$resolutionDays} days");

        return [
            'first_response' => $firstResponseDue,
            'resolution' => $resolutionDue,
        ];
    }

    /**
     * Calculate Compras SLA deadlines (single configuration)
     *
     * @param \DateTimeInterface|null $createdDate Creation date (defaults to now)
     * @return array Array with 'first_response' and 'resolution' DateTime objects
     */
    public function calculateComprasSLA(?DateTimeInterface $createdDate = null): array
    {
        $config = $this->getSLAConfig();
        $created = $createdDate ?? new DateTime();

        // Get SLA days from config
        $firstResponseDays = (int)($config['sla_compras_first_response_days'] ?? 1); // Default: 1 day
        $resolutionDays = (int)($config['sla_compras_resolution_days'] ?? 3); // Default: 3 days

        // Calculate deadlines (24/7 calendar days)
        $firstResponseDue = (clone $created)->modify("+{$firstResponseDays} days");
        $resolutionDue = (clone $created)->modify("+{$resolutionDays} days");

        return [
            'first_response' => $firstResponseDue,
            'resolution' => $resolutionDue,
        ];
    }

    /**
     * Check if first response SLA is breached
     *
     * Returns true if:
     * - first_response_sla_due is past AND
     * - first_response_at is null (no response yet) AND
     * - Entity is not in closed status
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity to check
     * @return bool True if first response SLA is breached
     */
    public function isFirstResponseSLABreached(EntityInterface $entity): bool
    {
        // If no first response SLA deadline set, can't be breached
        if (!$entity->first_response_sla_due) {
            return false;
        }

        // If already responded, can't be breached
        if ($entity->first_response_at) {
            return false;
        }

        // Get closed statuses based on entity type
        $closedStatuses = $this->getClosedStatusesForEntity($entity);

        // If entity is closed, ignore SLA
        if (in_array($entity->status, $closedStatuses)) {
            return false;
        }

        // Check if deadline has passed
        $now = new DateTime();
        return $now > $entity->first_response_sla_due;
    }

    /**
     * Check if resolution SLA is breached
     *
     * Returns true if:
     * - resolution_sla_due is past AND
     * - Entity is not in closed status
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity to check
     * @return bool True if resolution SLA is breached
     */
    public function isResolutionSLABreached(EntityInterface $entity): bool
    {
        // If no resolution SLA deadline set, can't be breached
        if (!$entity->resolution_sla_due) {
            return false;
        }

        // Get closed statuses based on entity type
        $closedStatuses = $this->getClosedStatusesForEntity($entity);

        // If entity is closed, ignore SLA
        if (in_array($entity->status, $closedStatuses)) {
            return false;
        }

        // Check if deadline has passed
        $now = new DateTime();
        return $now > $entity->resolution_sla_due;
    }

    /**
     * Get all entities with breached SLA
     *
     * @param string $module Module name ('Pqrs' or 'Compras')
     * @param array $closedStatuses Statuses considered closed
     * @param string $slaType SLA type ('first_response' or 'resolution')
     * @return array Array of entities with breached SLA
     */
    public function getBreachedSLAEntities(string $module, array $closedStatuses, string $slaType = 'resolution'): array
    {
        $table = $this->fetchTable($module);
        $now = new DateTime();

        $query = $table->find()
            ->where([
                "{$module}.status NOT IN" => $closedStatuses,
            ]);

        // Add SLA-specific conditions
        if ($slaType === 'first_response') {
            $query->where([
                "{$module}.first_response_sla_due <" => $now,
                "{$module}.first_response_at IS" => null,
            ]);
        } else {
            $query->where([
                "{$module}.resolution_sla_due <" => $now,
            ]);
        }

        // Include related data
        if ($module === 'Pqrs') {
            $query->contain(['Assignees']);
        } else {
            $query->contain(['Requesters', 'Assignees']);
        }

        $query->order(["{$module}.{$slaType}_sla_due" => 'ASC']);

        return $query->toArray();
    }

    /**
     * Load SLA configuration from system_settings with caching
     *
     * @return array SLA configuration array
     */
    public function getSLAConfig(): array
    {
        // Return cached config if available
        if ($this->slaConfigCache !== null) {
            return $this->slaConfigCache;
        }

        // Try to load from cache
        $this->slaConfigCache = Cache::remember('sla_settings', function () {
            $settingsTable = $this->fetchTable('SystemSettings');

            $settings = $settingsTable->find()
                ->select(['setting_key', 'setting_value'])
                ->where([
                    'setting_key LIKE' => 'sla_%'
                ])
                ->toArray();

            return collection($settings)->combine('setting_key', 'setting_value')->toArray();
        }, '_cake_core_');

        return $this->slaConfigCache;
    }

    /**
     * Get closed statuses for an entity based on its type
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity to check
     * @return array Array of closed statuses
     */
    protected function getClosedStatusesForEntity(EntityInterface $entity): array
    {
        $tableName = $entity->getSource();

        if ($tableName === 'Pqrs') {
            return ['resuelto', 'cerrado'];
        } elseif ($tableName === 'Compras') {
            return ['completado', 'rechazado'];
        } elseif ($tableName === 'Tickets') {
            return ['resuelto', 'convertido'];
        }

        return [];
    }

    /**
     * Recalculate SLA for an entity based on current configuration
     *
     * Useful when admin changes SLA settings and wants to update existing entities
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity to recalculate
     * @return bool Success
     */
    public function recalculateSLAForEntity(EntityInterface $entity): bool
    {
        $tableName = $entity->getSource();
        $table = $this->fetchTable($tableName);

        // Calculate new SLA based on entity type
        if ($tableName === 'Pqrs') {
            $slas = $this->calculatePqrsSLA($entity->type, $entity->created);
        } elseif ($tableName === 'Compras') {
            $slas = $this->calculateComprasSLA($entity->created);
        } else {
            Log::error("Unsupported entity type for SLA recalculation: {$tableName}");
            return false;
        }

        // Update SLA fields
        $entity->first_response_sla_due = $slas['first_response'];
        $entity->resolution_sla_due = $slas['resolution'];

        // Save entity
        if (!$table->save($entity)) {
            Log::error('Failed to recalculate SLA', [
                'entity' => $tableName,
                'id' => $entity->id,
                'errors' => $entity->getErrors()
            ]);
            return false;
        }

        return true;
    }
}
