<?php

namespace Drupal\dmf_collector_core\Registry\Event;

use DigitalMarketingFramework\Collector\Core\Registry\RegistryInterface;
use DigitalMarketingFramework\Core\Registry\RegistryUpdateType;

/**
 * Event for collector registry updates.
 */
class CollectorRegistryUpdateEvent
{
    /**
     * Constructs a CollectorRegistryUpdateEvent object.
     *
     * @param \DigitalMarketingFramework\Collector\Core\Registry\RegistryInterface $registry
     *   The collector registry.
     * @param \DigitalMarketingFramework\Core\Registry\RegistryUpdateType $type
     *   The update type.
     */
    public function __construct(
        protected RegistryInterface $registry,
        protected RegistryUpdateType $type,
    ) {
    }

    /**
     * Gets the collector registry.
     *
     * @return \DigitalMarketingFramework\Collector\Core\Registry\RegistryInterface
     *   The collector registry.
     */
    public function getRegistry(): RegistryInterface
    {
        return $this->registry;
    }

    /**
     * Gets the update type.
     *
     * @return \DigitalMarketingFramework\Core\Registry\RegistryUpdateType
     *   The update type.
     */
    public function getUpdateType(): RegistryUpdateType
    {
        return $this->type;
    }
}
