<?php
declare(strict_types=1);

namespace App\Service\Traits;

use Cake\Datasource\EntityInterface;

/**
 * SlaAwareTrait
 *
 * Provides SLA (Service Level Agreement) status wrapper methods for services
 * that delegate to SlaManagementService.
 *
 * Requirements:
 * - Using class must have a $slaService property (SlaManagementService instance)
 */
trait SlaAwareTrait
{
    /**
     * Check if first response SLA is breached for an entity
     *
     * @param EntityInterface $entity Entity with first_response_sla_due, first_response_at, status
     * @return bool
     */
    public function isFirstResponseSLABreached(EntityInterface $entity): bool
    {
        return $this->slaService->isFirstResponseSlaBreached(
            $entity->first_response_sla_due,
            $entity->first_response_at,
            $entity->status
        );
    }

    /**
     * Check if resolution SLA is breached for an entity
     *
     * @param EntityInterface $entity Entity with resolution_sla_due, resolved_at, status
     * @return bool
     */
    public function isResolutionSLABreached(EntityInterface $entity): bool
    {
        return $this->slaService->isResolutionSlaBreached(
            $this->getResolutionSlaDue($entity),
            $entity->resolved_at,
            $entity->status
        );
    }

    /**
     * Get SLA status information for an entity
     *
     * @param EntityInterface $entity Entity with SLA fields
     * @return array{first_response: array, resolution: array}
     */
    public function getSlaStatus(EntityInterface $entity): array
    {
        return [
            'first_response' => $this->slaService->getSlaStatus(
                $entity->first_response_sla_due,
                $entity->first_response_at,
                $entity->status
            ),
            'resolution' => $this->slaService->getSlaStatus(
                $this->getResolutionSlaDue($entity),
                $entity->resolved_at,
                $entity->status
            ),
        ];
    }

    /**
     * Get the resolution SLA due date from an entity
     *
     * Override this in classes that need legacy field fallback (e.g., ComprasService).
     *
     * @param EntityInterface $entity Entity
     * @return \Cake\I18n\DateTime|null
     */
    protected function getResolutionSlaDue(EntityInterface $entity): ?\Cake\I18n\DateTime
    {
        return $entity->resolution_sla_due;
    }
}
