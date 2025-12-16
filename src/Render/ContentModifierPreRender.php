<?php

namespace Drupal\dmf_collector_core\Render;

use DigitalMarketingFramework\Collector\Core\ContentModifier\ContentModifierHandlerInterface;
use DigitalMarketingFramework\Collector\Core\Registry\RegistryInterface as CollectorRegistryInterface;
use DigitalMarketingFramework\Core\Registry\RegistryCollectionInterface;
use Drupal;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\dmf_collector_core\ContentModifier\ContentModifierFieldManager;

/**
 * Pre-render callback for content modifier fields.
 *
 * Handles registering content modifier settings and adding ID attributes
 * to entity render arrays during the rendering process.
 *
 * ## Rendering Paths for Element Modifiers
 *
 * Element content modifiers require an ID wrapper on the rendered element so
 * the frontend JavaScript can find and personalize the content. Drupal renders
 * block_content entities through multiple paths, each requiring different handling:
 *
 * ### Path 1: Block Layout / Block Field (via Block Plugin)
 *
 * When block_content is placed via Block Layout (regions) or Block Field
 * (paragraph embedded), it renders through a Block plugin wrapper:
 *
 *     Block plugin → block_content entity → content
 *
 * The Block plugin adds a display title OUTSIDE the entity wrapper. To include
 * the title in our ID wrapper, we must act at the Block plugin level via
 * hook_block_view_alter(). The hook calls processBlockElementModifier() and
 * sets a drupal_static flag to prevent double-processing.
 *
 * Additionally, Block Layout blocks use lazy builders and render AFTER
 * hook_page_bottom() normally runs. This is why settings output uses
 * SettingsLazyBuilder - to ensure settings are collected after all blocks render.
 *
 * ### Path 2: Direct Entity Rendering (drupal_entity() in Twig)
 *
 * When block_content is rendered directly (e.g., {{ drupal_entity(...) }}),
 * there's no Block plugin wrapper. The entity pre-render callback handles this
 * case, adding the ID wrapper directly to the entity.
 *
 * ### Path Selection
 *
 * - hook_block_view_alter() fires first for Block plugin renders, sets flag
 * - processElementModifier() checks flag, skips if already handled
 * - For direct renders, flag is not set, so processElementModifier() handles it
 *
 * ### ID Wrapper Strategy
 *
 * - Entities with #theme (e.g., nodes): Use #attributes['id']
 * - Entities without #theme (e.g., block_content): Use #prefix/#suffix div
 * - Block plugin level: Always use #prefix/#suffix (wraps entire block)
 *
 * @see dmf_collector_core_block_view_alter()
 * @see SettingsLazyBuilder
 */
class ContentModifierPreRender implements TrustedCallbackInterface
{
    public function __construct(
        protected RouteMatchInterface $routeMatch,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public static function trustedCallbacks(): array
    {
        return ['preRenderCallback'];
    }

    /**
     * Static pre-render callback for entity view builds.
     *
     * This static method is required for Drupal's trusted callback system.
     * It retrieves the service instance and delegates to the instance method.
     *
     * @param array $build
     *   The entity render array.
     *
     * @return array
     *   The modified render array.
     */
    public static function preRenderCallback(array $build): array
    {
        return Drupal::service('dmf_collector_core.content_modifier_pre_render')
            ->preRender($build);
    }

    /**
     * Get the content modifier handler from the registry.
     *
     * @return ContentModifierHandlerInterface
     *   The content modifier handler.
     */
    protected function getContentModifierHandler(): ContentModifierHandlerInterface
    {
        /** @var RegistryCollectionInterface $registryCollection */
        $registryCollection = Drupal::service('dmf_core.registry_collection');

        /** @var CollectorRegistryInterface $collectorRegistry */
        $collectorRegistry = $registryCollection->getRegistryByClass(CollectorRegistryInterface::class);

        return $collectorRegistry->getContentModifierHandler();
    }

    /**
     * Register element modifier settings and apply ID wrapper.
     *
     * This is the common implementation used by both processElementModifier()
     * and processBlockElementModifier(). It handles settings registration and
     * ID wrapper application.
     *
     * @param array &$build
     *   The render array to modify.
     * @param string $configurationDocument
     *   The configuration document JSON.
     * @param string $baseId
     *   Base ID for the element (e.g., 'dmf-e-block_content-123').
     * @param bool $forceWrapper
     *   If TRUE, always use #prefix/#suffix wrapper div. If FALSE, use
     *   #attributes['id'] when #theme exists (for template-based rendering).
     *
     * @return string
     *   The unique ID that was applied.
     *
     * @throws \Exception
     *   If settings registration fails.
     */
    protected function registerAndWrapElement(
        array &$build,
        string $configurationDocument,
        string $baseId,
        bool $forceWrapper = false
    ): string {
        $id = Html::getUniqueId($baseId);

        // Register element-specific settings in the handler.
        // These will be included in the global settings JSON under content["{id}"].
        $this->getContentModifierHandler()->setElementSpecificSettingsFromConfigurationDocument(
            $configurationDocument,
            true, // asList
            $id
        );

        // Apply ID wrapper using appropriate strategy.
        if (!$forceWrapper && isset($build['#theme'])) {
            // Template exists (e.g., nodes) - use #attributes.
            if (!isset($build['#attributes'])) {
                $build['#attributes'] = [];
            }
            $build['#attributes']['id'] = $id;
        } else {
            // No template or forced wrapper - add wrapper div.
            $build['#prefix'] = ($build['#prefix'] ?? '') . '<div id="' . $id . '">';
            $build['#suffix'] = '</div>' . ($build['#suffix'] ?? '');
        }

        return $id;
    }

    /**
     * Process element modifier for a block_content entity at Block plugin level.
     *
     * Called from hook_block_view_alter() for blocks rendered via Block plugins
     * (Block Layout or Block Field). This ensures the ID wrapper includes the
     * block's display title, which is rendered outside the entity wrapper.
     *
     * @param array &$build
     *   The block render array (Block plugin level, not entity level).
     * @param EntityInterface $blockContent
     *   The block_content entity.
     *
     * @return bool
     *   TRUE if the block was processed, FALSE if no processing was needed.
     */
    public function processBlockElementModifier(array &$build, EntityInterface $blockContent): bool
    {
        $fieldName = ContentModifierFieldManager::FIELD_NAME_ELEMENT;

        if (!$blockContent->hasField($fieldName)) {
            return false;
        }

        $configurationDocument = $blockContent->get($fieldName)->value ?? '';
        if (empty($configurationDocument)) {
            return false;
        }

        $baseId = 'dmf-e-block_content-' . $blockContent->id();

        try {
            // Always use wrapper div at Block level (includes display title).
            $this->registerAndWrapElement($build, $configurationDocument, $baseId, true);

            // Mark as handled to prevent double-processing in entity pre-render.
            $handledBlockContent = &drupal_static('dmf_block_content_handled', []);
            $handledBlockContent[$blockContent->uuid()] = true;

            return true;
        } catch (\Exception $e) {
            Drupal::logger('dmf_collector_core')->error(
                'Error processing element modifier for block_content @id: @message',
                ['@id' => $blockContent->id(), '@message' => $e->getMessage()]
            );
            return false;
        }
    }

    /**
     * Pre-render callback for entity view builds.
     *
     * Determines whether the entity is rendered as the main page or as an
     * embedded element, then processes the appropriate content modifier field.
     *
     * @param array $build
     *   The entity render array.
     *
     * @return array
     *   The modified render array.
     */
    public function preRender(array $build): array
    {
        // Get the entity from the render array.
        $entity = $this->getEntityFromBuild($build);
        if (!$entity instanceof EntityInterface) {
            return $build;
        }

        $isMainPage = $this->isMainPageEntity($entity);

        if ($isMainPage) {
            $build = $this->processPageModifier($build, $entity);
        } else {
            $build = $this->processElementModifier($build, $entity);
        }

        return $build;
    }

    /**
     * Get the entity from a render array.
     *
     * @param array $build
     *   The render array.
     *
     * @return EntityInterface|null
     *   The entity, or null if not found.
     */
    protected function getEntityFromBuild(array $build): ?EntityInterface
    {
        // Try common keys where entities are stored in render arrays.
        if (isset($build['#entity']) && $build['#entity'] instanceof EntityInterface) {
            return $build['#entity'];
        }

        // For nodes, the entity is often stored under #node.
        if (isset($build['#node']) && $build['#node'] instanceof EntityInterface) {
            return $build['#node'];
        }

        // For paragraphs and other entities.
        if (isset($build['#paragraph']) && $build['#paragraph'] instanceof EntityInterface) {
            return $build['#paragraph'];
        }

        // Generic check for entity types.
        foreach ($build as $key => $value) {
            if (str_starts_with($key, '#') && $value instanceof EntityInterface) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Check if the entity is the main page entity.
     *
     * @param EntityInterface $entity
     *   The entity to check.
     *
     * @return bool
     *   TRUE if this is the main page entity, FALSE otherwise.
     */
    protected function isMainPageEntity(EntityInterface $entity): bool
    {
        // Get the entity from the current route.
        $routeEntity = $this->getRouteEntity();

        if (!$routeEntity instanceof EntityInterface) {
            return false;
        }

        // Compare entity type and ID.
        return $entity->getEntityTypeId() === $routeEntity->getEntityTypeId()
            && $entity->id() === $routeEntity->id();
    }

    /**
     * Get the main entity from the current route.
     *
     * @return EntityInterface|null
     *   The route's main entity, or null if not an entity route.
     */
    protected function getRouteEntity(): ?EntityInterface
    {
        // Common entity route parameter names.
        $entityParameters = ['node', 'taxonomy_term', 'user', 'media', 'commerce_product'];

        foreach ($entityParameters as $paramName) {
            $entity = $this->routeMatch->getParameter($paramName);
            if ($entity instanceof EntityInterface) {
                return $entity;
            }
        }

        // Fallback: check all route parameters for entities.
        foreach ($this->routeMatch->getParameters()->all() as $param) {
            if ($param instanceof EntityInterface) {
                return $param;
            }
        }

        return null;
    }

    /**
     * Process page content modifier field.
     *
     * @param array $build
     *   The render array.
     * @param EntityInterface $entity
     *   The entity.
     *
     * @return array
     *   The modified render array.
     */
    protected function processPageModifier(array $build, EntityInterface $entity): array
    {
        $fieldName = ContentModifierFieldManager::FIELD_NAME_PAGE;

        if (!$entity->hasField($fieldName)) {
            return $build;
        }

        $configurationDocument = $entity->get($fieldName)->value ?? '';
        if (empty($configurationDocument)) {
            return $build;
        }

        // Register page-specific settings in the handler.
        // These will be included in the global settings JSON under content["<page>"].
        try {
            $this->getContentModifierHandler()->setPageSpecificSettingsFromConfigurationDocument(
                $configurationDocument,
                true // asList
            );
        } catch (\Exception $e) {
            Drupal::logger('dmf_collector_core')->error(
                'Error registering page content modifier settings: @message',
                ['@message' => $e->getMessage()]
            );
        }

        // Page modifiers don't add data attributes to the element.
        // They operate on the page level via the global settings JSON.

        return $build;
    }

    /**
     * Process element content modifier field.
     *
     * This handles element modifiers for entities rendered directly (e.g., via
     * drupal_entity() in Twig). For block_content rendered via Block plugins,
     * hook_block_view_alter() handles it instead to include the display title.
     *
     * @param array $build
     *   The render array.
     * @param EntityInterface $entity
     *   The entity.
     *
     * @return array
     *   The modified render array.
     */
    protected function processElementModifier(array $build, EntityInterface $entity): array
    {
        $fieldName = ContentModifierFieldManager::FIELD_NAME_ELEMENT;

        if (!$entity->hasField($fieldName)) {
            return $build;
        }

        // For block_content entities, check if already handled at Block level.
        // hook_block_view_alter() sets a flag when it processes a block_content
        // to prevent double-processing within the same render cycle.
        // We clear the flag after checking so subsequent independent renders
        // (e.g., drupal_entity('block_content', ...)) are processed correctly.
        if ($entity->getEntityTypeId() === 'block_content') {
            $handledBlockContent = &drupal_static('dmf_block_content_handled', []);
            if (!empty($handledBlockContent[$entity->uuid()])) {
                unset($handledBlockContent[$entity->uuid()]);
                return $build;
            }
        }

        $configurationDocument = $entity->get($fieldName)->value ?? '';
        if (empty($configurationDocument)) {
            return $build;
        }

        $baseId = 'dmf-e-' . $entity->getEntityTypeId() . '-' . $entity->id();

        try {
            // Use #attributes if available, otherwise wrapper div.
            // forceWrapper = false allows using #attributes when #theme exists.
            $this->registerAndWrapElement($build, $configurationDocument, $baseId, false);
        } catch (\Exception $e) {
            Drupal::logger('dmf_collector_core')->error(
                'Error registering element content modifier settings: @message',
                ['@message' => $e->getMessage()]
            );
        }

        return $build;
    }
}
