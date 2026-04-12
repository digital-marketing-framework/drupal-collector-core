<?php

namespace Drupal\dmf_collector_core\Registry\EventSubscriber;

use DigitalMarketingFramework\Collector\Core\Registry\RegistryInterface;
use DigitalMarketingFramework\Core\InitializationInterface;
use DigitalMarketingFramework\Core\Registry\RegistryDomain;
use DigitalMarketingFramework\Core\Registry\RegistryUpdateType;
use Drupal\dmf_collector_core\Registry\Event\CollectorRegistryUpdateEvent;
use Drupal\dmf_core\DrupalInitialization;
use Drupal\dmf_core\DrupalInitializationInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Abstract base class for collector registry update event subscribers.
 */
abstract class AbstractCollectorRegistryUpdateEventSubscriber implements EventSubscriberInterface
{
    protected DrupalInitializationInterface $initialization;

    /**
     * Constructs an AbstractCollectorRegistryUpdateEventSubscriber object.
     *
     * @param InitializationInterface $initialization
     *   The initialization service
     */
    public function __construct(
        InitializationInterface $initialization,
    ) {
        if ($initialization instanceof DrupalInitializationInterface) {
            $this->initialization = $initialization;
        } else {
            $this->initialization = new DrupalInitialization(inner: $initialization);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CollectorRegistryUpdateEvent::class => 'onRegistryUpdate',
        ];
    }

    /**
     * Initializes global configuration.
     *
     * @param RegistryInterface $registry
     *   The collector registry
     */
    protected function initGlobalConfiguration(RegistryInterface $registry): void
    {
        $this->initialization->initGlobalConfiguration(RegistryDomain::COLLECTOR, $registry);
    }

    /**
     * Initializes services.
     *
     * @param RegistryInterface $registry
     *   The collector registry
     */
    protected function initServices(RegistryInterface $registry): void
    {
        $this->initialization->initServices(RegistryDomain::COLLECTOR, $registry);
    }

    /**
     * Initializes plugins.
     *
     * @param RegistryInterface $registry
     *   The collector registry
     */
    protected function initPlugins(RegistryInterface $registry): void
    {
        $this->initialization->initPlugins(RegistryDomain::COLLECTOR, $registry);
    }

    /**
     * Handles registry update event.
     *
     * @param CollectorRegistryUpdateEvent $event
     *   The event
     */
    public function onRegistryUpdate(CollectorRegistryUpdateEvent $event): void
    {
        $registry = $event->getRegistry();

        // always init meta data
        $this->initialization->initMetaData($registry);

        // init rest depending on update type
        $type = $event->getUpdateType();
        switch ($type) {
            case RegistryUpdateType::GLOBAL_CONFIGURATION:
                $this->initGlobalConfiguration($registry);
                break;

            case RegistryUpdateType::SERVICE:
                $this->initServices($registry);
                break;

            case RegistryUpdateType::PLUGIN:
                $this->initPlugins($registry);
                break;
        }
    }
}
