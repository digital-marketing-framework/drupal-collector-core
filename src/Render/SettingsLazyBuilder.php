<?php

namespace Drupal\dmf_collector_core\Render;

use DigitalMarketingFramework\Core\Registry\RegistryCollectionInterface;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Lazy builder for DMF frontend settings.
 *
 * Uses lazy building to ensure settings are collected after all content
 * (including lazy-built blocks) has been rendered and registered.
 */
class SettingsLazyBuilder implements TrustedCallbackInterface
{
    public function __construct(
        protected RegistryCollectionInterface $registryCollection,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public static function trustedCallbacks(): array
    {
        return ['renderSettings'];
    }

    /**
     * Lazy builder callback to render DMF settings.
     *
     * @return array
     *   A render array containing the settings JSON script tag.
     */
    public function renderSettings(): array
    {
        $settings = $this->registryCollection->getFrontendSettings();

        // Return empty array if no settings.
        if (empty($settings)) {
            return [];
        }

        return [
            '#type' => 'html_tag',
            '#tag' => 'script',
            '#attributes' => [
                'type' => 'application/json',
                'data-dmf-selector' => 'dmf-settings-json',
            ],
            '#value' => json_encode($settings, JSON_THROW_ON_ERROR),
            '#attached' => [
                'library' => [
                    'dmf_core/frontend-scripts',
                ],
            ],
        ];
    }
}