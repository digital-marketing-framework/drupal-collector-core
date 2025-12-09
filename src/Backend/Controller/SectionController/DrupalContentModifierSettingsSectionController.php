<?php

namespace Drupal\dmf_collector_core\Backend\Controller\SectionController;

use DigitalMarketingFramework\Core\Backend\Controller\SectionController\SectionController;
use DigitalMarketingFramework\Core\Backend\Response\Response;
use DigitalMarketingFramework\Core\ConfigurationDocument\Parser\ConfigurationDocumentParserAwareInterface;
use DigitalMarketingFramework\Core\ConfigurationDocument\Parser\ConfigurationDocumentParserAwareTrait;
use DigitalMarketingFramework\Core\GlobalConfiguration\GlobalConfigurationAwareInterface;
use DigitalMarketingFramework\Core\GlobalConfiguration\GlobalConfigurationAwareTrait;
use DigitalMarketingFramework\Core\GlobalConfiguration\Settings\CoreSettings;
use DigitalMarketingFramework\Core\Registry\RegistryInterface;
use Drupal\dmf_collector_core\ContentModifier\ContentModifierFieldManager;

/**
 * Section controller for Drupal-specific content modifier settings.
 *
 * Provides a backend UI for enabling/disabling content modifiers
 * on specific Drupal entity types and bundles.
 */
class DrupalContentModifierSettingsSectionController extends SectionController implements ConfigurationDocumentParserAwareInterface, GlobalConfigurationAwareInterface
{
    use ConfigurationDocumentParserAwareTrait;
    use GlobalConfigurationAwareTrait;

    public function __construct(
        string $keyword,
        RegistryInterface $registry,
        protected ContentModifierFieldManager $fieldManager,
    ) {
        parent::__construct(
            $keyword,
            $registry,
            'drupal-content-modifier-settings',
            ['edit', 'save']
        );
    }

    protected function editAction(): Response
    {
        $this->addConfigurationEditorAssets();

        // Get current configuration and schema from the service
        $currentConfig = $this->fieldManager->getCurrentConfiguration();
        $schemaDocument = $this->fieldManager->buildSchemaDocument();

        // Convert to document format
        $document = $this->configurationDocumentParser->produceDocument($currentConfig, $schemaDocument);

        $this->viewData['document'] = $document;
        $this->viewData['debug'] = $this->globalConfiguration->getGlobalSettings(CoreSettings::class)->debug();

        return $this->render();
    }

    protected function saveAction(): Response
    {
        $document = $this->request->getData()['document'] ?? '';
        $newConfiguration = $this->configurationDocumentParser->parseDocument($document);

        // Get current configuration to compare
        $currentConfig = $this->fieldManager->getCurrentConfiguration();

        // Process page content modifiers
        $this->processCategory(
            $newConfiguration['page'] ?? [],
            $currentConfig['page'] ?? [],
            ContentModifierFieldManager::FIELD_NAME_PAGE,
            'Page Content Modifiers'
        );

        // Process element content modifiers
        $this->processCategory(
            $newConfiguration['element'] ?? [],
            $currentConfig['element'] ?? [],
            ContentModifierFieldManager::FIELD_NAME_ELEMENT,
            'Element Content Modifiers'
        );

        // Process form content modifiers
        $this->processFormCategory(
            $newConfiguration['form'] ?? [],
            $currentConfig['form'] ?? []
        );

        return $this->redirect('page.drupal-content-modifier-settings.edit');
    }

    /**
     * Process changes for a category of content modifiers.
     *
     * @param array<string, array<string, string>> $newConfig
     *   The new configuration.
     * @param array<string, array<string, string>> $currentConfig
     *   The current configuration.
     * @param string $fieldName
     *   The field name.
     * @param string $fieldLabel
     *   The field label.
     */
    protected function processCategory(
        array $newConfig,
        array $currentConfig,
        string $fieldName,
        string $fieldLabel,
    ): void {
        foreach ($newConfig as $entityTypeId => $bundles) {
            foreach ($bundles as $bundleId => $newStatus) {
                $currentStatus = $currentConfig[$entityTypeId][$bundleId] ?? ContentModifierFieldManager::STATUS_REMOVED;

                $this->fieldManager->applyStatusChange(
                    $entityTypeId,
                    $bundleId,
                    $fieldName,
                    $fieldLabel,
                    $currentStatus,
                    $newStatus
                );
            }
        }
    }

    /**
     * Process changes for form content modifiers.
     *
     * @param array<string, string> $newConfig
     *   The new configuration.
     * @param array<string, string> $currentConfig
     *   The current configuration.
     */
    protected function processFormCategory(array $newConfig, array $currentConfig): void
    {
        // TODO: Implement webform content modifier handling
        // This will require different logic since webforms don't use Field API
    }
}