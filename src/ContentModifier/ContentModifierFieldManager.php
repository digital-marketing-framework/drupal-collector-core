<?php

namespace Drupal\dmf_collector_core\ContentModifier;

use DigitalMarketingFramework\Core\SchemaDocument\RenderingDefinition\RenderingDefinitionInterface;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\ContainerSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\StringSchema;
use DigitalMarketingFramework\Core\SchemaDocument\SchemaDocument;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Service for managing content modifier fields on Drupal entities.
 *
 * Handles creating, deleting, showing, and hiding content modifier fields
 * across different entity types and bundles. Also provides schema generation
 * for the backend configuration UI.
 */
class ContentModifierFieldManager
{
    public const FIELD_NAME_PAGE = 'dmf_page_modifiers';

    public const FIELD_NAME_ELEMENT = 'dmf_element_modifiers';

    public const FIELD_NAME_FORM = 'dmf_form_modifiers';

    public const KEY_PAGE = 'page';

    public const KEY_ELEMENT = 'element';

    public const KEY_FORM = 'form';

    public const KEY_WEBFORM = 'webform';

    public const KEY_STATUS = 'status';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_HIDDEN = 'hidden';

    public const STATUS_REMOVED = 'removed';

    /**
     * Entity types that can have element content modifiers.
     *
     * These are hardcoded as they represent embeddable content pieces.
     *
     * @var array<string>
     */
    protected const ELEMENT_ENTITY_TYPES = ['paragraph', 'block_content'];

    /**
     * Entity types to exclude from page content modifiers.
     *
     * These are internal/utility entity types that don't represent
     * user-facing pages even if they have canonical links.
     *
     * @var array<string>
     */
    protected const PAGE_ENTITY_TYPE_EXCLUDE_LIST = [
        'block_content',      // Embedded blocks, not standalone pages
        'file',               // File entities
        'menu_link_content',  // Menu link configuration
        'redirect',           // URL redirect configuration
        'shortcut',           // User shortcut links
        'webform_submission', // Form submission data records
    ];

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        protected EntityTypeBundleInfoInterface $bundleInfo,
        protected EntityDisplayRepositoryInterface $displayRepository,
    ) {
    }

    /**
     * Get entity types that can have page content modifiers.
     *
     * Page content modifiers apply to content entities that have a canonical
     * link template (i.e., can be viewed as a standalone page).
     *
     * @return array<string, string>
     *   Entity type IDs as keys, labels as values.
     */
    protected function getPageEntityTypes(): array
    {
        $pageEntityTypes = [];

        foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $entityType) {
            // Only content entities (not config entities)
            if (!$entityType instanceof ContentEntityTypeInterface) {
                continue;
            }

            // Must have a canonical link template (can be viewed as a page)
            if (!$entityType->hasLinkTemplate('canonical')) {
                continue;
            }

            // Exclude internal/utility entity types
            if (in_array($entityTypeId, self::PAGE_ENTITY_TYPE_EXCLUDE_LIST, true)) {
                continue;
            }

            $pageEntityTypes[$entityTypeId] = (string) $entityType->getLabel();
        }

        // Sort alphabetically by label, but keep 'node' at the top
        uasort($pageEntityTypes, fn($a, $b) => strcasecmp($a, $b));
        if (isset($pageEntityTypes['node'])) {
            $nodeLabel = $pageEntityTypes['node'];
            unset($pageEntityTypes['node']);
            $pageEntityTypes = ['node' => $nodeLabel] + $pageEntityTypes;
        }

        return $pageEntityTypes;
    }

    /**
     * Get entity types that can have element content modifiers.
     *
     * @return array<string, string>
     *   Entity type IDs as keys, labels as values.
     */
    protected function getElementEntityTypes(): array
    {
        $elementEntityTypes = [];

        foreach (self::ELEMENT_ENTITY_TYPES as $entityTypeId) {
            if (!$this->entityTypeManager->hasDefinition($entityTypeId)) {
                continue;
            }

            $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
            $elementEntityTypes[$entityTypeId] = (string) $entityType->getLabel();
        }

        return $elementEntityTypes;
    }

    /**
     * Check if a field exists for a given entity type and bundle.
     *
     * @param string $entityTypeId
     *   The entity type ID.
     * @param string $bundle
     *   The bundle name.
     * @param string $fieldName
     *   The field name.
     *
     * @return string
     *   The field status: 'active', 'hidden', or 'removed'.
     */
    protected function getFieldStatus(string $entityTypeId, string $bundle, string $fieldName): string
    {
        $field = FieldConfig::loadByName($entityTypeId, $bundle, $fieldName);

        if ($field === null) {
            return self::STATUS_REMOVED;
        }

        // Check if field is hidden in form display
        $formDisplay = $this->displayRepository->getFormDisplay($entityTypeId, $bundle);
        $component = $formDisplay->getComponent($fieldName);

        return $component === null ? self::STATUS_HIDDEN : self::STATUS_ACTIVE;
    }

    /**
     * Get the current configuration for all content modifier fields.
     *
     * @return array<string, mixed>
     *   The current configuration organized by category/entityType/bundle.
     */
    public function getCurrentConfiguration(): array
    {
        $config = [];

        // Page content modifiers
        foreach ($this->getPageEntityTypes() as $entityTypeId => $entityTypeLabel) {
            $bundles = $this->bundleInfo->getBundleInfo($entityTypeId);
            foreach ($bundles as $bundleId => $bundleInfo) {
                $config[self::KEY_PAGE][$entityTypeId][$bundleId] = $this->getFieldStatus(
                    $entityTypeId,
                    $bundleId,
                    self::FIELD_NAME_PAGE
                );
            }
        }

        // Element content modifiers
        foreach ($this->getElementEntityTypes() as $entityTypeId => $entityTypeLabel) {
            $bundles = $this->bundleInfo->getBundleInfo($entityTypeId);
            foreach ($bundles as $bundleId => $bundleInfo) {
                $config[self::KEY_ELEMENT][$entityTypeId][$bundleId] = $this->getFieldStatus(
                    $entityTypeId,
                    $bundleId,
                    self::FIELD_NAME_ELEMENT
                );
            }
        }

        // Form content modifiers (webform)
        if ($this->entityTypeManager->hasDefinition(self::KEY_WEBFORM)) {
            // TODO: Implement webform field status check
            $config[self::KEY_FORM][self::KEY_WEBFORM][self::KEY_STATUS] = self::STATUS_REMOVED;
        }

        return $config;
    }

    /**
     * Apply a status change for a specific entity type/bundle.
     *
     * @param string $entityTypeId
     *   The entity type ID.
     * @param string $bundleId
     *   The bundle ID.
     * @param string $fieldName
     *   The field name.
     * @param string $fieldLabel
     *   The field label.
     * @param string $currentStatus
     *   The current status.
     * @param string $newStatus
     *   The new status.
     */
    public function applyStatusChange(
        string $entityTypeId,
        string $bundleId,
        string $fieldName,
        string $fieldLabel,
        string $currentStatus,
        string $newStatus,
    ): void {
        if ($currentStatus === $newStatus) {
            return;
        }

        switch ($newStatus) {
            case self::STATUS_ACTIVE:
                if ($currentStatus === self::STATUS_REMOVED) {
                    // Create new field
                    $this->createField($entityTypeId, $bundleId, $fieldName, $fieldLabel);
                } else {
                    // Field exists but hidden - show it
                    $this->showFieldInFormDisplay($entityTypeId, $bundleId, $fieldName);
                }
                break;

            case self::STATUS_HIDDEN:
                if ($currentStatus === self::STATUS_REMOVED) {
                    // Create field but hide it immediately
                    $this->createField($entityTypeId, $bundleId, $fieldName, $fieldLabel);
                    $this->hideFieldFromFormDisplay($entityTypeId, $bundleId, $fieldName);
                } else {
                    // Field exists - just hide it
                    $this->hideFieldFromFormDisplay($entityTypeId, $bundleId, $fieldName);
                }
                break;

            case self::STATUS_REMOVED:
                // Delete the field (and all its data)
                $this->deleteField($entityTypeId, $bundleId, $fieldName);
                break;
        }
    }

    /**
     * Create a content modifier field for an entity type/bundle.
     *
     * @param string $entityTypeId
     *   The entity type ID.
     * @param string $bundleId
     *   The bundle ID.
     * @param string $fieldName
     *   The field name.
     * @param string $fieldLabel
     *   The field label.
     */
    protected function createField(
        string $entityTypeId,
        string $bundleId,
        string $fieldName,
        string $fieldLabel,
    ): void {
        // Check if field storage exists (shared across bundles)
        $fieldStorage = FieldStorageConfig::loadByName($entityTypeId, $fieldName);

        if ($fieldStorage === null) {
            // Create field storage using custom schema configuration field type.
            $fieldStorage = FieldStorageConfig::create([
                'field_name' => $fieldName,
                'entity_type' => $entityTypeId,
                'type' => 'dmf_schema_configuration',
                'cardinality' => 1,
            ]);
            $fieldStorage->save();
        }

        // Create field config for this bundle.
        // Use "Content Personalization" as a user-friendly label for all content
        // modifier fields, regardless of type (page, element, form).
        $fieldConfig = FieldConfig::create([
            'field_storage' => $fieldStorage,
            'bundle' => $bundleId,
            'label' => 'Content Personalization',
            'description' => 'Configuration for Anyrel content modifiers.',
            'required' => false,
            'settings' => [],
        ]);
        $fieldConfig->save();

        // Add to form display (backend editing)
        $this->showFieldInFormDisplay($entityTypeId, $bundleId, $fieldName);

        // Configure view display (frontend rendering)
        $this->configureViewDisplay($entityTypeId, $bundleId, $fieldName);
    }

    /**
     * Delete a content modifier field from an entity type/bundle.
     *
     * @param string $entityTypeId
     *   The entity type ID.
     * @param string $bundleId
     *   The bundle ID.
     * @param string $fieldName
     *   The field name.
     */
    protected function deleteField(
        string $entityTypeId,
        string $bundleId,
        string $fieldName,
    ): void {
        $fieldConfig = FieldConfig::loadByName($entityTypeId, $bundleId, $fieldName);

        if ($fieldConfig !== null) {
            $fieldConfig->delete();
        }

        // Note: FieldStorageConfig is automatically deleted when the last
        // FieldConfig using it is deleted, so we don't need to handle that.
    }

    /**
     * Get the content modifier group for a field name.
     *
     * @param string $fieldName
     *   The field name.
     *
     * @return string
     *   The content modifier group ('page', 'element', or 'form').
     */
    protected function getContentModifierGroup(string $fieldName): string
    {
        return match ($fieldName) {
            self::FIELD_NAME_PAGE => self::KEY_PAGE,
            self::FIELD_NAME_ELEMENT => self::KEY_ELEMENT,
            self::FIELD_NAME_FORM => self::KEY_FORM,
            default => self::KEY_PAGE,
        };
    }

    /**
     * Show a field in the entity form display.
     *
     * @param string $entityTypeId
     *   The entity type ID.
     * @param string $bundleId
     *   The bundle ID.
     * @param string $fieldName
     *   The field name.
     */
    protected function showFieldInFormDisplay(
        string $entityTypeId,
        string $bundleId,
        string $fieldName,
    ): void {
        $formDisplay = $this->displayRepository->getFormDisplay($entityTypeId, $bundleId);

        // Configure the widget with content modifier specific settings.
        // The widget passes these to the configuration editor AJAX endpoints.
        $contentModifierGroup = $this->getContentModifierGroup($fieldName);

        // All content modifier fields appear at the top of the form by default.
        // For nodes, a form alter hook moves page modifiers to the sidebar.
        $formDisplay->setComponent($fieldName, [
            'type' => 'dmf_schema_configuration_editor',
            'weight' => -100,
            'settings' => [
                'document_type' => 'content-modifier',
                'mode' => 'modal',
                'rows' => 5,
                'supports_includes' => false,
                'additional_parameters' => [
                    'contentModifierGroup' => $contentModifierGroup,
                    'contentModifierList' => '1',
                ],
            ],
        ]);

        $formDisplay->save();
    }

    /**
     * Hide a field from the entity form display.
     *
     * @param string $entityTypeId
     *   The entity type ID.
     * @param string $bundleId
     *   The bundle ID.
     * @param string $fieldName
     *   The field name.
     */
    protected function hideFieldFromFormDisplay(
        string $entityTypeId,
        string $bundleId,
        string $fieldName,
    ): void {
        $formDisplay = $this->displayRepository->getFormDisplay($entityTypeId, $bundleId);

        $formDisplay->removeComponent($fieldName);
        $formDisplay->save();
    }

    /**
     * Configure the view display for frontend rendering.
     *
     * The field uses the hidden formatter because content modifier rendering
     * is handled by hook_entity_view_alter() and a pre-render callback that
     * adds data attributes directly to the entity wrapper element.
     *
     * @param string $entityTypeId
     *   The entity type ID.
     * @param string $bundleId
     *   The bundle ID.
     * @param string $fieldName
     *   The field name.
     */
    protected function configureViewDisplay(
        string $entityTypeId,
        string $bundleId,
        string $fieldName,
    ): void {
        $viewDisplay = $this->displayRepository->getViewDisplay($entityTypeId, $bundleId);
        $viewDisplay->setComponent($fieldName, [
            'type' => 'dmf_schema_configuration_hidden',
            'label' => 'hidden',
        ]);
        $viewDisplay->save();
    }

    /**
     * Build the schema document for content modifier settings.
     *
     * @return SchemaDocument
     *   The schema document with all content modifier categories.
     */
    public function buildSchemaDocument(): SchemaDocument
    {
        $schemaDocument = new SchemaDocument();
        $mainSchema = $schemaDocument->getMainSchema();
        $mainSchema->getRenderingDefinition()->setLabel('Content Modifier Settings');

        // Build page content modifiers section
        $this->buildCategorySchema(
            $mainSchema,
            self::KEY_PAGE,
            'Page Content Modifiers',
            $this->getPageEntityTypes(),
            self::FIELD_NAME_PAGE
        );

        // Build element content modifiers section
        $this->buildCategorySchema(
            $mainSchema,
            self::KEY_ELEMENT,
            'Element Content Modifiers',
            $this->getElementEntityTypes(),
            self::FIELD_NAME_ELEMENT
        );

        // Build form content modifiers section
        $this->buildFormSchema($mainSchema);

        return $schemaDocument;
    }

    /**
     * Get select options based on current field status.
     *
     * @param string $currentStatus
     *   The current field status.
     *
     * @return array<string, string>
     *   The available options.
     */
    protected function getStatusOptions(string $currentStatus): array
    {
        if ($currentStatus === self::STATUS_REMOVED) {
            // Field doesn't exist - can only activate or keep removed
            return [
                self::STATUS_REMOVED => 'Disabled',
                self::STATUS_ACTIVE => 'Enabled',
            ];
        }

        // Field exists - all options available
        return [
            self::STATUS_ACTIVE => 'Enabled',
            self::STATUS_HIDDEN => 'Hidden (data retained)',
            self::STATUS_REMOVED => 'Disabled (data deleted)',
        ];
    }

    /**
     * Build schema for a category of content modifiers.
     *
     * @param ContainerSchema $schema
     *   The schema to add properties to.
     * @param string $categoryKey
     *   The category key (e.g., 'page', 'element').
     * @param string $categoryLabel
     *   The category label.
     * @param array<string, string> $entityTypes
     *   Entity type IDs as keys, labels as values.
     * @param string $fieldName
     *   The field name used for this category.
     */
    protected function buildCategorySchema(
        ContainerSchema $schema,
        string $categoryKey,
        string $categoryLabel,
        array $entityTypes,
        string $fieldName,
    ): void {
        $categorySchema = new ContainerSchema();
        $categorySchema->getRenderingDefinition()->setLabel($categoryLabel);

        foreach ($entityTypes as $entityTypeId => $entityTypeLabel) {
            $bundles = $this->bundleInfo->getBundleInfo($entityTypeId);

            if (empty($bundles)) {
                continue;
            }

            // Sort bundles alphabetically by label
            uasort($bundles, fn($a, $b) => strcasecmp($a['label'], $b['label']));

            $entityTypeSchema = new ContainerSchema();
            $entityTypeSchema->getRenderingDefinition()->setLabel($entityTypeLabel);

            foreach ($bundles as $bundleId => $bundleData) {
                $currentStatus = $this->getFieldStatus($entityTypeId, $bundleId, $fieldName);
                $options = $this->getStatusOptions($currentStatus);

                $stringSchema = new StringSchema($currentStatus);
                foreach ($options as $value => $label) {
                    $stringSchema->getAllowedValues()->addValue($value, $label);
                }
                $stringSchema->getRenderingDefinition()->setFormat(RenderingDefinitionInterface::FORMAT_SELECT);
                $stringSchema->getRenderingDefinition()->setLabel('Bundle: ' . $bundleData['label']);

                $entityTypeSchema->addProperty($bundleId, $stringSchema);
            }

            $categorySchema->addProperty($entityTypeId, $entityTypeSchema);
        }

        $schema->addProperty($categoryKey, $categorySchema);
    }

    /**
     * Build schema for form content modifiers (webform and other form modules).
     *
     * @param ContainerSchema $schema
     *   The schema to add properties to.
     */
    protected function buildFormSchema(ContainerSchema $schema): void
    {
        $categorySchema = new ContainerSchema();
        $categorySchema->getRenderingDefinition()->setLabel('Form Content Modifiers');

        // Webform module
        if ($this->entityTypeManager->hasDefinition(self::KEY_WEBFORM)) {
            $webformSchema = new ContainerSchema();
            $webformSchema->getRenderingDefinition()->setLabel('Webform');

            // For webforms, we use a simple enabled/disabled flag
            // since webforms don't have bundles in the traditional sense
            $stringSchema = new StringSchema(self::STATUS_REMOVED);
            $stringSchema->getAllowedValues()->addValue(self::STATUS_REMOVED, 'Disabled');
            $stringSchema->getAllowedValues()->addValue(self::STATUS_ACTIVE, 'Enabled');
            $stringSchema->getRenderingDefinition()->setFormat(RenderingDefinitionInterface::FORMAT_SELECT);
            $stringSchema->getRenderingDefinition()->setLabel('Status');

            $webformSchema->addProperty(self::KEY_STATUS, $stringSchema);

            $categorySchema->addProperty(self::KEY_WEBFORM, $webformSchema);
        }

        // Only add the form category if there are any form modules available
        if (count($categorySchema->getProperties()) > 0) {
            $schema->addProperty(self::KEY_FORM, $categorySchema);
        }
    }
}