<?php

namespace Drupal\dmf_collector_core\Registry;

use DigitalMarketingFramework\Collector\Core\Registry\Registry as CoreCollectorRegistry;
use DigitalMarketingFramework\Core\Registry\RegistryUpdateType;
use Drupal\dmf_collector_core\Registry\Event\CollectorRegistryUpdateEvent;
use Drupal\dmf_core\Registry\Event\CoreRegistryUpdateEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Collector registry for Drupal.
 */
class Registry extends CoreCollectorRegistry
{
    /**
     * Constructs a Registry object.
     *
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
     *   The event dispatcher.
     */
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        // Dispatch core registry update events
        $this->eventDispatcher->dispatch(
            new CoreRegistryUpdateEvent($this, RegistryUpdateType::GLOBAL_CONFIGURATION)
        );
        $this->eventDispatcher->dispatch(
            new CoreRegistryUpdateEvent($this, RegistryUpdateType::SERVICE)
        );
        $this->eventDispatcher->dispatch(
            new CoreRegistryUpdateEvent($this, RegistryUpdateType::PLUGIN)
        );

        // Dispatch collector registry update events
        $this->eventDispatcher->dispatch(
            new CollectorRegistryUpdateEvent($this, RegistryUpdateType::GLOBAL_CONFIGURATION)
        );
        $this->eventDispatcher->dispatch(
            new CollectorRegistryUpdateEvent($this, RegistryUpdateType::SERVICE)
        );
        $this->eventDispatcher->dispatch(
            new CollectorRegistryUpdateEvent($this, RegistryUpdateType::PLUGIN)
        );
    }
}
