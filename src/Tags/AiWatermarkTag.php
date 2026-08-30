<?php

namespace Kabocom\AiWatermark\Tags;

use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Facades\Asset;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Image;
use Statamic\Facades\Site;
use Statamic\Tags\Tags;

class AiWatermarkTag extends Tags
{
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
        $asset = $this->normalize($item);

        $manipulator = Image::manipulate($asset ?? $item);

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

        $data = ['url' => $url];

        if ($asset instanceof Augmentable) {
            $data = array_merge($asset->toAugmentedArray(), $data);
        }

        return $data;
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
        $markAsset = is_string($markAsset) ? Asset::find("site::{$markAsset}") : $markAsset;

        if (! $markAsset) {
            return [null, null, null, null];
        }

        $position = $asset->get('watermark_position', 'bottom-right');
        $width = $global->get('watermark_width', config('ai-watermark.default_width'));
        $padding = $global->get('watermark_padding', config('ai-watermark.default_padding'));

        return [
            'site/'.$markAsset->path(),
            $position,
            "{$width}w",
            "{$padding}w",
        ];
    }
}
