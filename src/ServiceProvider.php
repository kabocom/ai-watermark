<?php

namespace Kabocom\AiWatermark;

use Illuminate\Support\Facades\File;
use Kabocom\AiWatermark\Concerns\ResolvesWatermarkContainer;
use Statamic\Facades\Asset;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\YAML;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    use ResolvesWatermarkContainer;

    public function bootAddon()
    {
        $this->publishBlueprint();
        $this->publishDefaultImages();
        $this->provisionGlobalSet();
    }

    /**
     * Writes the ai_watermark Global Set's blueprint into the project if
     * one doesn't already exist there. This is the only place the field
     * definitions live now — no runtime field-injection listener for the
     * Global Set, unlike the asset-container fields below, since a Global
     * Set only ever has the one blueprint (nothing to merge across
     * multiple, differently-shaped containers).
     */
    private function publishBlueprint(): void
    {
        $path = resource_path('blueprints/globals/'.config('ai-watermark.global_set').'.yaml');

        if (File::exists($path)) {
            return;
        }

        $labels = config('ai-watermark.labels');
        $container = $this->watermarkContainer()?->handle();

        File::makeDirectory(dirname($path), 0755, true, true);

        File::put($path, YAML::dump([
            'tabs' => [
                'main' => [
                    'display' => $labels['global_tab'],
                    'sections' => [
                        [
                            'display' => $labels['global_section_images_title'],
                            'instructions' => $labels['global_section_images_instructions'],
                            'fields' => [
                                [
                                    'handle' => 'watermark_dark',
                                    'field' => [
                                        'type' => 'assets',
                                        'display' => $labels['global_watermark_dark'],
                                        'container' => $container,
                                        'max_files' => 1,
                                        'width' => 50,
                                    ],
                                ],
                                [
                                    'handle' => 'watermark_light',
                                    'field' => [
                                        'type' => 'assets',
                                        'display' => $labels['global_watermark_light'],
                                        'container' => $container,
                                        'max_files' => 1,
                                        'width' => 50,
                                    ],
                                ],
                            ],
                        ],
                        [
                            'display' => $labels['global_section_size_title'],
                            'fields' => [
                                [
                                    'handle' => 'watermark_width',
                                    'field' => [
                                        'display' => $labels['global_watermark_width'],
                                        'instructions' => $labels['global_watermark_width_instructions'],
                                        'instructions_position' => 'below',
                                        'type' => 'integer',
                                        'default' => config('ai-watermark.default_width'),
                                        'width' => 50,
                                    ],
                                ],
                                [
                                    'handle' => 'watermark_padding',
                                    'field' => [
                                        'display' => $labels['global_watermark_padding'],
                                        'type' => 'integer',
                                        'default' => config('ai-watermark.default_padding'),
                                        'width' => 50,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]));
    }

    /**
     * Creates the ai_watermark Global Set with the addon's bundled default
     * watermark images if a set with that handle doesn't already exist in
     * this project. Idempotent — does nothing on subsequent boots.
     */
    private function provisionGlobalSet(): void
    {
        $handle = config('ai-watermark.global_set');

        if (GlobalSet::findByHandle($handle)) {
            return;
        }

        $set = (new \Statamic\Globals\GlobalSet)
            ->handle($handle)
            ->title(config('ai-watermark.labels.global_set_title'));

        $set->save();

        $set->makeLocalization(Site::default()->handle())
            ->data([
                'watermark_dark' => 'watermark/ki-generiert-dark.png',
                'watermark_light' => 'watermark/ki-generiert-light.png',
                'watermark_width' => config('ai-watermark.default_width'),
                'watermark_padding' => config('ai-watermark.default_padding'),
            ])
            ->save();
    }

    /**
     * Copies the addon's bundled default PNGs onto the watermark
     * container's disk (files in vendor/ aren't web-servable) and writes
     * their `.meta/*.yaml` sidecar files too, via `Asset::meta()` — the
     * same generation Statamic itself uses, so the CP asset picker shows
     * them correctly right away instead of waiting for the next natural
     * Stache refresh. Both steps are per-file idempotent (skipped if the
     * PNG or its meta file already exists), so this is safe to re-run on
     * every boot indefinitely.
     */
    private function publishDefaultImages(): void
    {
        $container = $this->watermarkContainer();

        if (! $container) {
            return;
        }

        $disk = $container->disk()->filesystem();

        foreach (['ki-generiert-dark.png', 'ki-generiert-light.png'] as $file) {
            $target = "watermark/{$file}";

            if (! $disk->exists($target)) {
                $source = $this->getAddon()->directory().'resources/watermarks/'.$file;

                $disk->put($target, file_get_contents($source));
            }

            Asset::make()->container($container)->path($target)->meta();
        }
    }
}
