<?php

namespace Kabocom\AiWatermark\Listeners;

use Statamic\Events\AssetContainerBlueprintFound;

class InjectAssetWatermarkFields
{
    public function handle(AssetContainerBlueprintFound $event): void
    {
        $containers = config('ai-watermark.containers', []);

        if ($event->container && $containers && ! in_array($event->container->handle(), $containers)) {
            return;
        }

        $labels = config('ai-watermark.labels');

        $event->blueprint->ensureFieldsInTab([
            'watermark' => [
                'type' => 'toggle',
                'display' => $labels['watermark_toggle'],
                'instructions' => $labels['watermark_toggle_instructions'],
            ],
            'watermark_variant' => [
                'type' => 'select',
                'display' => $labels['watermark_variant'],
                'options' => [
                    'dark' => $labels['watermark_variant_dark'],
                    'light' => $labels['watermark_variant_light'],
                ],
                'default' => 'dark',
                'clearable' => false,
                'if' => ['watermark' => 'equals true'],
            ],
            'watermark_position' => [
                'type' => 'select',
                'display' => $labels['watermark_position'],
                'options' => [
                    'top-left' => $labels['watermark_position_top_left'],
                    'top-right' => $labels['watermark_position_top_right'],
                    'bottom-left' => $labels['watermark_position_bottom_left'],
                    'bottom-right' => $labels['watermark_position_bottom_right'],
                ],
                'default' => 'bottom-right',
                'clearable' => false,
                'if' => ['watermark' => 'equals true'],
            ],
        ], null);
    }
}
