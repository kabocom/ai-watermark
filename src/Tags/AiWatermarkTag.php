<?php

namespace Kabocom\AiWatermark\Tags;

use Facades\Statamic\Imaging\Attributes;
use Facades\Statamic\Imaging\ImageValidator;
use Kabocom\AiWatermark\Concerns\ResolvesWatermarkContainer;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Facades\Asset;
use Statamic\Facades\Glide as GlideManager;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Image;
use Statamic\Facades\Path;
use Statamic\Facades\Site;
use Statamic\Facades\URL;
use Statamic\Imaging\ImageGenerator;
use Statamic\Tags\Tags;

class AiWatermarkTag extends Tags
{
    use ResolvesWatermarkContainer;

    protected static $handle = 'ai_watermark';

    /**
     * {{ ai_watermark:image ... }}{{ url }}{{ /ai_watermark:image }}
     *
     * Drop-in replacement for {{ glide:image }} that additionally bakes
     * in the "KI-generiert" mark whenever the resolved asset's own
     * `watermark` field is set, pulling the actual images/sizing from
     * the ai_watermark Global Set. Passing an unset/empty mark to Glide
     * is a silent no-op, so this is always safe to call.
     */
    public function wildcard($method)
    {
        $field = explode(':', $this->tag, 2)[1] ?? $method;

        $item = $this->context->value($field);

        return $this->generate($item);
    }

    private function generate($item)
    {
        if ($item instanceof \Illuminate\Support\Collection || is_array($item)) {
            return $this->generateMany($item);
        }

        if (blank($item)) {
            return $this->isPair ? [] : null;
        }

        return $this->build($this->normalize($item), $item);
    }

    /**
     * A multi-value assets field (no `max_files: 1`) resolves here as a
     * collection/array of assets rather than a single one. Watermark
     * each of them: as a pair tag this loops once per asset (same as
     * looping over the field itself), as a bare tag (a single url is
     * expected) it uses the first one.
     */
    private function generateMany($items)
    {
        $assets = collect($items)
            ->map(fn ($entry) => $this->normalize($entry))
            ->filter()
            ->values();

        if ($assets->isEmpty()) {
            return $this->isPair ? [] : null;
        }

        if (! $this->isPair) {
            return $this->build($assets->first(), $assets->first());
        }

        return $assets->map(fn ($asset) => $this->build($asset, $asset))->filter()->values()->all();
    }

    /**
     * Like {{ glide:image }}, a broken source (missing asset, unreadable
     * file, ...) is logged and renders nothing instead of failing the page.
     */
    private function build(?AssetContract $asset, $manipulateTarget)
    {
        try {
            return $this->buildOrFail($asset, $manipulateTarget);
        } catch (\Exception $e) {
            \Log::error($e->getMessage());

            return $this->isPair ? [] : null;
        }
    }

    private function buildOrFail(?AssetContract $asset, $manipulateTarget)
    {
        $manipulator = Image::manipulate($asset ?? $manipulateTarget);

        foreach ($this->params->except(['src', 'id', 'path']) as $param => $value) {
            $manipulator->{$param}($value);
        }

        [$mark, $markPos, $markW, $markPad] = $this->watermarkParams($asset);

        $manipulator
            ->mark($mark)
            ->markpos($markPos)
            ->markw($markW)
            ->markpad($markPad)
            ->markalpha('100');

        $url = $manipulator->build();

        if (! $this->isPair) {
            return $url;
        }

        // Same as {{ glide:image }}: `width`/`height` inside the pair are the
        // manipulated image's dimensions, not the source asset's, so they
        // override the asset's augmented values below.
        $data = array_merge(['url' => $url], $this->generatedAttributes($asset ?? $manipulateTarget, $manipulator->getParams()));

        if ($asset instanceof Augmentable) {
            $data = array_merge($asset->toAugmentedArray(), $data);
        }

        return $data;
    }

    /**
     * Generates the image (a cache hit for the URL built above, since the
     * params are identical) and reads its actual width/height.
     */
    private function generatedAttributes($item, array $params): array
    {
        $path = $item instanceof AssetContract ? $item->path() : (string) $item;

        if (! ImageValidator::isValidExtension(Path::extension($path))) {
            return [];
        }

        $generator = app(ImageGenerator::class);

        $generated = match (true) {
            $item instanceof AssetContract => $generator->generateByAsset($item, $params),
            URL::isAbsolute($path) => $generator->generateByUrl($path, $params),
            default => $generator->generateByPath($path, $params),
        };

        return Attributes::from(GlideManager::cacheDisk(), $generated);
    }

    private function normalize($item)
    {
        if ($item instanceof AssetContract) {
            return $item;
        }

        if (is_string($item)) {
            return Asset::find($item);
        }

        return null;
    }

    private function watermarkParams(?AssetContract $asset): array
    {
        if (! $asset || ! $asset->get('watermark')) {
            return [null, null, null, null];
        }

        $global = GlobalSet::findByHandle(config('ai-watermark.global_set'))?->in(Site::default()->handle());

        if (! $global) {
            return [null, null, null, null];
        }

        $variant = $asset->get('watermark_variant', 'dark');
        $markAsset = $global->get($variant === 'light' ? 'watermark_light' : 'watermark_dark');
        $markAsset = is_array($markAsset) ? ($markAsset[0] ?? null) : $markAsset;
        $container = $this->watermarkContainer()?->handle();
        $markAsset = is_string($markAsset) && $container ? Asset::find("{$container}::{$markAsset}") : $markAsset;

        if (! $markAsset || ! ($mark = $this->markPath($markAsset))) {
            return [null, null, null, null];
        }

        $position = $asset->get('watermark_position', 'bottom-right');
        $width = $global->get('watermark_width', config('ai-watermark.default_width'));
        $padding = $global->get('watermark_padding', config('ai-watermark.default_padding'));

        return [
            $mark,
            $position,
            "{$width}w",
            "{$padding}w",
        ];
    }

    /**
     * Glide's watermark filesystem is rooted at public_path() (Statamic's
     * default `statamic.assets.image_manipulation` config), so the `mark`
     * parameter needs to be a path relative to public_path() — not
     * relative to the asset's own container. Derives that by comparing
     * the asset's disk's configured root against public_path(), rather
     * than assuming any particular container/disk name.
     */
    private function markPath(AssetContract $markAsset): ?string
    {
        $diskHandle = $markAsset->container()->diskHandle();
        $root = rtrim((string) config("filesystems.disks.{$diskHandle}.root"), '/');
        $publicPath = rtrim(public_path(), '/');

        if (! $root || ! str_starts_with($root, $publicPath)) {
            return null;
        }

        $prefix = trim(substr($root, strlen($publicPath)), '/');

        return trim($prefix.'/'.$markAsset->path(), '/');
    }
}
