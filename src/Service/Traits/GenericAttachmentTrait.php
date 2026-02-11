<?php
declare(strict_types=1);

namespace App\Service\Traits;

use App\Service\S3Service;

/**
 * GenericAttachmentTrait
 *
 * @deprecated Use \App\Service\FileStorageService instead (TRAIT-002).
 *
 * This trait has been replaced by FileStorageService, an injectable service class.
 * All consumers have been migrated:
 * - TicketService: injects FileStorageService via constructor
 * - ComprasService: injects FileStorageService via constructor
 * - PqrsService: injects FileStorageService via constructor
 * - GenericEmailService: injects FileStorageService via constructor
 * - TicketSystemControllerTrait: uses lazy-loaded FileStorageService
 *
 * This file is kept temporarily for reference. Safe to delete once verified.
 *
 * @see \App\Service\FileStorageService
 */
trait GenericAttachmentTrait
{
    /**
     * @deprecated Use FileStorageService instead
     */
    abstract protected function getStorageService(): S3Service;
}