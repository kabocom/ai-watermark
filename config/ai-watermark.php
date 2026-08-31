<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Asset containers
    |--------------------------------------------------------------------------
    |
    | Which asset container handles get the watermark fields injected into
    | their blueprint. Leave empty to inject into every asset container.
    |
    */

    'containers' => [],

    /*
    |--------------------------------------------------------------------------
    | Global set handle
    |--------------------------------------------------------------------------
    |
    | The handle of the Global Set that holds the default watermark images
    | and sizing. Created automatically on first boot if it doesn't exist.
    |
    */

    'global_set' => 'ai_watermark',

    /*
    |--------------------------------------------------------------------------
    | Watermark image container
    |--------------------------------------------------------------------------
    |
    | Which asset container the two watermark badge images (and the
    | addon's bundled defaults, on first install) live in. Must be a
    | container whose disk is web-servable — Glide's watermark compositor
    | reads straight off disk relative to public_path(), so a disk that
    | isn't inside the public webroot won't work here.
    |
    */

    'watermark_container' => 'site',

    /*
    |--------------------------------------------------------------------------
    | Labels
    |--------------------------------------------------------------------------
    |
    | CP field labels, overridable per site without forking the addon.
    |
    */

    'labels' => [
        'global_set_title' => 'KI-Wasserzeichen',
        'global_tab' => 'Hauptteil',
        'global_section_images_title' => 'Wasserzeichen-Bilder',
        'global_section_images_instructions' => 'Diese Bilder werden verwendet, wenn ein Foto als KI-generiert markiert ist. Muss ein Rasterformat mit Transparenz sein (PNG) — SVG wird von der Bildverarbeitung nicht unterstützt.',
        'global_section_size_title' => 'Größe',
        'watermark_toggle' => 'Von KI generiert',
        'watermark_toggle_instructions' => 'Wasserzeichen zum Bild hinzufügen',
        'watermark_variant' => 'Wasserzeichen-Variante',
        'watermark_variant_dark' => 'Dunkel (für helle Hintergründe)',
        'watermark_variant_light' => 'Hell (für dunkle Hintergründe)',
        'watermark_position' => 'Position des Wasserzeichens',
        'watermark_position_top_left' => 'Oben links',
        'watermark_position_top_right' => 'Oben rechts',
        'watermark_position_bottom_left' => 'Unten links',
        'watermark_position_bottom_right' => 'Unten rechts',
        'global_watermark_dark' => 'Wasserzeichen (dunkel)',
        'global_watermark_light' => 'Wasserzeichen (hell)',
        'global_watermark_width' => 'Breite (% der Bildbreite)',
        'global_watermark_width_instructions' => 'Skaliert proportional mit jedem Bild, unabhängig von dessen tatsächlicher Größe.',
        'global_watermark_padding' => 'Abstand vom Rand (% der Bildbreite)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | Used only when the Global Set is first created.
    |
    */

    'default_width' => 20,
    'default_padding' => 3,

];
