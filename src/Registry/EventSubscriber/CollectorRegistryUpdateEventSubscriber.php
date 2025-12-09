<?php

namespace Drupal\dmf_collector_core\Registry\EventSubscriber;

use DigitalMarketingFramework\Collector\Core\CollectorCoreInitialization;

/**
 * Event subscriber for collector registry updates.
 */
class CollectorRegistryUpdateEventSubscriber extends AbstractCollectorRegistryUpdateEventSubscriber
{
    /**
     * Constructs a CollectorRegistryUpdateEventSubscriber object.
     */
    public function __construct()
    {
        $initialization = new CollectorCoreInitialization('dmf_collector_core');
        parent::__construct($initialization);
    }
}
