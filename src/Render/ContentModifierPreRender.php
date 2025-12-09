<?php

namespace Drupal\dmf_collector_core\Render;

use DigitalMarketingFramework\Collector\Core\ContentModifier\ContentModifierHandlerInterface;
use DigitalMarketingFramework\Collector\Core\Registry\RegistryInterface as CollectorRegistryInterface;
use DigitalMarketingFramework\Core\Registry\RegistryCollectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\dmf_collector_core\ContentModifier\ContentModifierFieldManager;

/**
 * Pre-render callback for content modifier fields.
 *
 * Handles registering content modifier settings and adding data attributes
 * to entity render arrays during the rendering process.
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
        return \Drupal::service('dmf_collector_core.content_modifier_pre_render')
            ->preRender($build);
    }

    /**
     * Get the content modifier handler from the registry.
     *
     * @return \DigitalMarketingFramework\Collector\Core\ContentModifier\ContentModifierHandlerInterface
     *   The content modifier handler.
     */
    protected function getContentModifierHandler(): ContentModifierHandlerInterface
    {
        /** @var RegistryCollectionInterface $registryCollection */
        $registryCollection = \Drupal::service('dmf_core.registry_collection');

        /** @var CollectorRegistryInterface $collectorRegistry */
        $collectorRegistry = $registryCollection->getRegistryByClass(CollectorRegistryInterface::class);

        return $collectorRegistry->getContentModifierHandler();
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
     * @return \Drupal\Core\Entity\EntityInterface|null
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
     * @param \Drupal\Core\Entity\EntityInterface $entity
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
     * @return \Drupal\Core\Entity\EntityInterface|null
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
     * @param \Drupal\Core\Entity\EntityInterface $entity
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
            \Drupal::logger('dmf_collector_core')->error(
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
     * @param array $build
     *   The render array.
     * @param \Drupal\Core\Entity\EntityInterface $entity
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

        $configurationDocument = $entity->get($fieldName)->value ?? '';
        if (empty($configurationDocument)) {
            return $build;
        }

        // Use existing ID or generate a unique one.
        $id = $build['#attributes']['id']
            ?? 'dmf-e-' . $entity->getEntityTypeId() . '-' . $entity->id();

        // Register element-specific settings in the handler.
        // These will be included in the global settings JSON under content["{id}"].
        // The frontend JS uses DMF.content to find settings by element ID.
        // Data attributes are NOT added to avoid conflicts with DMF.content settings.
        try {
            $this->getContentModifierHandler()->setElementSpecificSettingsFromConfigurationDocument(
                $configurationDocument,
                true, // asList
                $id
            );

            // Ensure #attributes exists.
            if (!isset($build['#attributes'])) {
                $build['#attributes'] = [];
            }

            // Set the ID on the element so JS can find it via DMF.content[id].
            $build['#attributes']['id'] = $id;
        } catch (\Exception $e) {
            \Drupal::logger('dmf_collector_core')->error(
                'Error registering element content modifier settings: @message',
                ['@message' => $e->getMessage()]
            );
        }

        return $build;
    }
}