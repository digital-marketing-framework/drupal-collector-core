<?php

namespace Drupal\dmf_collector_core\Backend\Controller\AjaxController;

use DigitalMarketingFramework\Core\Backend\Controller\AjaxController\FullDocumentConfigurationEditorAjaxController;
use DigitalMarketingFramework\Core\Backend\Response\JsonResponse;
use DigitalMarketingFramework\Core\Backend\Response\Response;
use DigitalMarketingFramework\Core\Registry\RegistryInterface;
use DigitalMarketingFramework\Core\SchemaDocument\SchemaDocument;
use Drupal\dmf_collector_core\ContentModifier\ContentModifierFieldManager;

/**
 * AJAX controller for Drupal-specific content modifier settings.
 *
 * Generates a dynamic schema based on Drupal's entity types and bundles,
 * allowing admins to enable/disable content modifiers per bundle.
 */
class DrupalContentModifierSettingsAjaxController extends FullDocumentConfigurationEditorAjaxController
{
    public function __construct(
        string $keyword,
        RegistryInterface $registry,
        protected ContentModifierFieldManager $fieldManager,
    ) {
        parent::__construct(
            $keyword,
            $registry,
            'drupal-content-modifier-settings'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getSchemaDocument(): SchemaDocument
    {
        // Schema is rebuilt on each request to reflect current field states
        return $this->fieldManager->buildSchemaDocument();
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultsAction(): Response
    {
        // Get current state as defaults (not schema defaults)
        $data = $this->fieldManager->getCurrentConfiguration();

        return new JsonResponse($data);
    }
}