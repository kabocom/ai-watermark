<?php

namespace Kabocom\AiWatermark\Concerns;

use Statamic\Contracts\Assets\AssetContainer as AssetContainerContract;
use Statamic\Facades\AssetContainer;

trait ResolvesWatermarkContainer
{
    /**
     * The asset container the watermark badge images live in. Tries the
     * configured `watermark_container` first, then falls back to the
     * conventional `assets` container, then to whatever container
     * happens to exist at all — so the addon still works on a project
     * that doesn't have a container with the configured handle.
     */
    protected function watermarkContainer(): ?AssetContainerContract
    {
        $configured = config('ai-watermark.watermark_container');

        return AssetContainer::findByHandle($configured)
            ?? AssetContainer::findByHandle('assets')
            ?? AssetContainer::all()->first();
    }
}
