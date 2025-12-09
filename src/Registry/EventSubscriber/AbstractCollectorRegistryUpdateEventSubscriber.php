<?php

namespace Drupal\dmf_collector_core\Registry\EventSubscriber;

use DigitalMarketingFramework\Collector\Core\Registry\RegistryInterface;
use DigitalMarketingFramework\Core\InitializationInterface;
use DigitalMarketingFramework\Core\Registry\RegistryDomain;
use DigitalMarketingFramework\Core\Registry\RegistryUpdateType;
use Drupal\dmf_collector_core\Registry\Event\CollectorRegistryUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Abstract base class for collector registry update event subscribers.
 */
abstract class AbstractCollectorRegistryUpdateEventSubscriber implements EventSubscriberInterface
{
    /**
     * Constructs an AbstractCollectorRegistryUpdateEventSubscriber object.
     *
     * @param \DigitalMarketingFramework\Core\InitializationInterface $initialization
     *   The initialization service.
     */
    public function __construct(
        protected InitializationInterface $initialization,
    ) {
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
     * @param \DigitalMarketingFramework\Collector\Core\Registry\RegistryInterface $registry
     *   The collector registry.
     */
    protected function initGlobalConfiguration(RegistryInterface $registry): void
    {
        $this->initialization->initGlobalConfiguration(RegistryDomain::COLLECTOR, $registry);
    }

    /**
     * Initializes services.
     *
     * @param \DigitalMarketingFramework\Collector\Core\Registry\RegistryInterface $registry
     *   The collector registry.
     */
    protected function initServices(RegistryInterface $registry): void
    {
        $this->initialization->initServices(RegistryDomain::COLLECTOR, $registry);
    }

    /**
     * Initializes plugins.
     *
     * @param \DigitalMarketingFramework\Collector\Core\Registry\RegistryInterface $registry
     *   The collector registry.
     */
    protected function initPlugins(RegistryInterface $registry): void
    {
        $this->initialization->initPlugins(RegistryDomain::COLLECTOR, $registry);
    }

    /**
     * Handles registry update event.
     *
     * @param \Drupal\dmf_collector_core\Registry\Event\CollectorRegistryUpdateEvent $event
     *   The event.
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
