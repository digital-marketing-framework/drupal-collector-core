<?php

namespace Drupal\dmf_collector_core;

use DigitalMarketingFramework\Collector\Core\CollectorCoreInitialization;
use DigitalMarketingFramework\Core\Backend\Controller\AjaxController\AjaxControllerInterface;
use DigitalMarketingFramework\Core\Backend\Controller\SectionController\SectionControllerInterface;
use DigitalMarketingFramework\Core\Backend\Section\Section;
use DigitalMarketingFramework\Core\Backend\Section\SectionInterface;
use DigitalMarketingFramework\Core\Registry\RegistryDomain;
use DigitalMarketingFramework\Core\Registry\RegistryInterface;
use Drupal\dmf_collector_core\Backend\Controller\AjaxController\DrupalContentModifierSettingsAjaxController;
use Drupal\dmf_collector_core\Backend\Controller\SectionController\DrupalContentModifierSettingsSectionController;
use Drupal\dmf_collector_core\ContentModifier\ContentModifierFieldManager;
use Drupal\dmf_core\DrupalInitialization;

class DrupalCollectorCoreInitialization extends DrupalInitialization
{
    protected const PLUGINS = [
        RegistryDomain::CORE => [
            AjaxControllerInterface::class => [
                DrupalContentModifierSettingsAjaxController::class,
            ],
            SectionControllerInterface::class => [
                DrupalContentModifierSettingsSectionController::class,
            ],
        ],
    ];

    public function __construct(
        protected ContentModifierFieldManager $fieldManager,
    ) {
        parent::__construct(
            inner: new CollectorCoreInitialization('dmf_collector_core'),
            packageName: 'drupal-collector-core',
            packageAlias: 'dmf_collector_core',
        );
    }

    /**
     * @return array<SectionInterface>
     */
    protected function getBackendSections(): array
    {
        return [
            new Section(
                'Drupal Content Modifiers',
                'COLLECTOR',
                'page.drupal-content-modifier-settings.edit',
                'Configure content modifier fields for Drupal entity types',
                'MOD:dmf_collector_core/images/icons/dashboard-drupal-content-modifiers.svg',
                'Show',
                60
            ),
        ];
    }

    protected function getAdditionalPluginArguments(string $interface, string $pluginClass, RegistryInterface $registry): array
    {
        if (
            $pluginClass === DrupalContentModifierSettingsAjaxController::class
            || $pluginClass === DrupalContentModifierSettingsSectionController::class
        ) {
            return [$this->fieldManager];
        }

        return parent::getAdditionalPluginArguments($interface, $pluginClass, $registry);
    }
}
