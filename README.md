# AI Watermark

A Statamic 6 addon that bakes a "KI-generiert" (AI-generated) watermark directly into the pixels of any image rendered through Glide, whenever the source asset is flagged as AI-generated. Built to satisfy the German legal requirement that AI-generated images be labeled in a way that can't be stripped by disabling background graphics, screenshotting, or right-click-saving — because it's not a CSS overlay, it's part of the actual served image bytes.

## Requirements

- Statamic ^6.0 (PHP 8.3+, per Statamic 6's own requirements)
- A web-servable asset container (see [Watermark image container](#watermark-image-container) below)

## Installation

Not published on Packagist — add it as a VCS repository:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/kabocom/ai-watermark"
        }
    ],
    "require": {
        "kabocom/ai-watermark": "dev-main"
    }
}
```

Then:

```
composer require kabocom/ai-watermark:dev-main
```

That's the only manual step for everything *except* rendering images — see [What you still have to do](#what-you-still-have-to-do) below.

## What happens automatically

On `composer require`, Statamic auto-discovers the addon's service provider. From that point on, every request:

1. **Injects three fields into every asset container's blueprint** — `watermark` (toggle), `watermark_variant` (`dark`/`light` select), `watermark_position` (four-corner select) — via `Statamic\Events\AssetContainerBlueprintFound`. This happens at runtime; your project's blueprint YAML files are never touched. Existing project-defined fields with the same handles always win on conflict (Statamic's own `Blueprint::ensureField()` merge behavior), so this is safe to install into a project that already has its own `watermark` field.

On the **first** request after install, additionally:

2. **Writes the `ai_watermark` Global Set's blueprint** to `resources/blueprints/globals/ai_watermark.yaml` — two sections, "Wasserzeichen-Bilder" (the two badge images) and "Größe" (size/padding) — if that file doesn't already exist. Skipped silently on every later request once the file is there.
3. **Creates the `ai_watermark` Global Set itself**, with `watermark_width: 20`, `watermark_padding: 3`, and `watermark_dark`/`watermark_light` pointing at two bundled default PNG badges, copied onto the configured watermark container's disk. Skipped entirely if a Global Set with that handle already exists — this addon never overwrites Global Set data you've already customized.

All three steps are idempotent and independent: deleting just the blueprint file (say, to reset your section layout back to the addon's default) will regenerate only that, without touching your Global Set's actual data or images.

## What you still have to do

**Swap `{{ glide:image }}` / `{{ glide:poster }}` calls over to `{{ ai_watermark:image }}` / `{{ ai_watermark:poster }}`** in whatever templates render your images. This is a genuine drop-in replacement — same tag-pair syntax, same parameters (`fit`, `width`, `height`, `quality`, `format`, ...), same `url` + augmented-asset-field scope inside the pair — it just also bakes in the mark when appropriate:

```antlers
{{ ai_watermark:image :fit='crop_focal' :width='600' :height='600' quality='85' }}
    <img src="{{ url }}" alt="{{ alt }}">
{{ /ai_watermark:image }}
```

There's no way to make this step automatic — the mark has to be injected into a rendering call that's not decided until template-authoring time, and no Statamic core hook exists for changing Glide's manipulation parameters generically ahead of generation (checked; the closest core mechanism, `ImageGenerator::applyDefaultManipulations()`, is a private method with no extension point). Calling the tag on an asset that isn't flagged is always a safe no-op — Glide silently skips compositing when the mark parameter is empty, so there's no harm in using `{{ ai_watermark:image }}` everywhere instead of `{{ glide:image }}` by default.

## Configuration

Publish the config to customize:

```
php artisan vendor:publish --tag=ai-watermark-config
```

| Key | Default | Purpose |
|---|---|---|
| `containers` | `[]` (all) | Which asset container handles get the three watermark fields injected. |
| `global_set` | `ai_watermark` | Handle of the Global Set holding the default images and sizing. |
| `watermark_container` | `site` | Which asset container the two badge images live in. **Must be web-servable** — see below. |
| `labels` | German strings | Every CP field/section label, overridable without forking the addon (e.g. for a non-German site). |
| `default_width` / `default_padding` | `20` / `3` | Percent-of-image-width, used only when the Global Set is first created. |

### Watermark image container

Glide's watermark compositor reads image files directly off disk relative to `public_path()` — not through any container abstraction. The addon resolves this correctly for whichever container `watermark_container` names, by checking that container's actual configured disk root (`config('filesystems.disks.{disk}.root')`) against `public_path()`. If that container's disk lives *outside* the public webroot, the mark silently won't apply (same safe no-op as an asset with the toggle off) — point `watermark_container` at a container whose disk root is under `public/`.

## How the watermark is chosen per photo

- The `watermark` toggle must be on for that specific asset (or video poster).
- `watermark_variant` (`dark` for light photo backgrounds, `light` for dark ones) selects which of the two Global Set images gets used — this is a manual per-photo editor choice, not automatic brightness detection.
- `watermark_position` anchors the mark to one of the four photo corners. This is deliberately not a free-form point: the position is resolved against whatever each individual rendering happens to crop the photo to, so a corner anchor is the only choice that stays correct across differently-cropped placements of the same source image.
