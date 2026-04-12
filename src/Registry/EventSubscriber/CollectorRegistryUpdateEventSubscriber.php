<?php

namespace Drupal\dmf_collector_core\Registry\EventSubscriber;

use Drupal\dmf_collector_core\DrupalCollectorCoreInitialization;

/**
 * Event subscriber for collector registry updates.
 */
class CollectorRegistryUpdateEventSubscriber extends AbstractCollectorRegistryUpdateEventSubscriber
{
    // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- narrowed type hint for runtime enforcement
    public function __construct(
        DrupalCollectorCoreInitialization $initialization,
    ) {
        parent::__construct($initialization);
    }
}
