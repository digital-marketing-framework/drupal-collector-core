<?php

namespace Drupal\dmf_collector_core\Registry\EventSubscriber;

use DigitalMarketingFramework\Core\Registry\RegistryDomain;
use Drupal\dmf_collector_core\Registry\Registry;
use Drupal\dmf_core\Registry\Event\RegistryCollectionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for adding collector registry to registry collection.
 */
class RegistryCollectionEventSubscriber implements EventSubscriberInterface
{
    /**
     * Constructs a RegistryCollectionEventSubscriber object.
     *
     * @param Registry $registry
     *   The collector registry.
     */
    public function __construct(
        protected Registry $registry,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            RegistryCollectionEvent::class => 'onRegistryCollectionUpdate',
        ];
    }

    /**
     * Handles registry collection update event.
     *
     * @param \Drupal\dmf_core\Registry\Event\RegistryCollectionEvent $event
     *   The event.
     */
    public function onRegistryCollectionUpdate(RegistryCollectionEvent $event): void
    {
        $event->getRegistryCollection()->addRegistry(RegistryDomain::COLLECTOR, $this->registry);
    }
}
