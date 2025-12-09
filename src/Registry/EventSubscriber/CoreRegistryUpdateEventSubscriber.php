<?php

namespace Drupal\dmf_collector_core\Registry\EventSubscriber;

use DigitalMarketingFramework\Collector\Core\CollectorCoreInitialization;
use DigitalMarketingFramework\Core\Backend\Section\Section;
use DigitalMarketingFramework\Core\Registry\RegistryInterface;
use Drupal\dmf_collector_core\Backend\Controller\AjaxController\DrupalContentModifierSettingsAjaxController;
use Drupal\dmf_collector_core\Backend\Controller\SectionController\DrupalContentModifierSettingsSectionController;
use Drupal\dmf_collector_core\ContentModifier\ContentModifierFieldManager;
use Drupal\dmf_core\Registry\EventSubscriber\AbstractCoreRegistryUpdateEventSubscriber;

/**
 * Event subscriber for Core registry updates from collector package.
 */
class CoreRegistryUpdateEventSubscriber extends AbstractCoreRegistryUpdateEventSubscriber
{
    /**
     * Constructs a CoreRegistryUpdateEventSubscriber object.
     *
     * @param \Drupal\dmf_collector_core\ContentModifier\ContentModifierFieldManager $fieldManager
     *   The content modifier field manager service.
     */
    public function __construct(
        protected ContentModifierFieldManager $fieldManager,
    ) {
        $initialization = new CollectorCoreInitialization('dmf_collector_core');
        parent::__construct($initialization);
    }

    /**
     * {@inheritdoc}
     */
    protected function initServices(RegistryInterface $registry): void
    {
        parent::initServices($registry);

        // Register the backend section for content modifier settings
        $registry->getBackendManager()->setSection(
            new Section(
                'Drupal Content Modifiers',
                'COLLECTOR',
                'page.drupal-content-modifier-settings.edit',
                'Configure content modifier fields for Drupal entity types',
                'MOD:dmf_collector_core/images/icons/dashboard-drupal-content-modifiers.svg',
                'Show',
                60
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function initPlugins(RegistryInterface $registry): void
    {
        parent::initPlugins($registry);

        // Register Drupal-specific backend controllers
        $registry->registerBackendAjaxController(
            DrupalContentModifierSettingsAjaxController::class,
            [$this->fieldManager]
        );

        $registry->registerBackendSectionController(
            DrupalContentModifierSettingsSectionController::class,
            [$this->fieldManager]
        );
    }
}