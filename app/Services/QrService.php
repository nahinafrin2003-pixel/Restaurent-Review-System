<?php

namespace App\Services;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrService
{
    public static function svg(string $text, int $size = 180): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd()));

        return $writer->writeString($text);
    }

    /** PNG data-URI for embedding in PDFs (falls back to SVG when GD is missing). */
    public static function dataUri(string $text, int $size = 180): string
    {
        if (extension_loaded('gd')) {
            $png = (new Writer(new GDLibRenderer($size, 2)))->writeString($text);

            return 'data:image/png;base64,' . base64_encode($png);
        }

        return 'data:image/svg+xml;base64,' . base64_encode(self::svg($text, $size));
    }
}
