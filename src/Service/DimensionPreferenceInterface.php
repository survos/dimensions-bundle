<?php

declare(strict_types=1);

namespace Survos\DimensionsBundle\Service;

/**
 * Implement this interface to provide per-user, per-institution, or per-session
 * display unit preferences. Inject your implementation as a service and the
 * DimensionFormatter will use it when present.
 */
interface DimensionPreferenceInterface
{
    /** Returns the preferred display unit string ('mm', 'cm', 'm', 'in', 'ft') or null to use the bundle default. */
    public function getPreferredDisplayUnit(): ?string;
}
