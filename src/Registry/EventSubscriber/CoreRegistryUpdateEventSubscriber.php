<?php

namespace Drupal\dmf_collector_core\Registry\EventSubscriber;

use Drupal\dmf_collector_core\DrupalCollectorCoreInitialization;
use Drupal\dmf_core\Registry\EventSubscriber\AbstractCoreRegistryUpdateEventSubscriber;

/**
 * Event subscriber for Core registry updates from collector package.
 */
class CoreRegistryUpdateEventSubscriber extends AbstractCoreRegistryUpdateEventSubscriber
{
    // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- narrowed type hint for runtime enforcement
    public function __construct(
        DrupalCollectorCoreInitialization $initialization,
    ) {
        parent::__construct($initialization);
    }
}
