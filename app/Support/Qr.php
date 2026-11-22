<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class Qr
{
    /**
     * An inline SVG (scales sharply when printed) in the given colour.
     */
    public static function svg(string $content, string $color = '#000000'): string
    {
        $color = preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : '#000000';

        $svg = (new QRCode(new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'quietzoneSize' => 1,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
            'connectPaths' => true,
            'svgDefs' => '',
            'cssClass' => 'qr-svg',
        ])))->render($content);

        // Colour the dark modules (the library draws them with fill="#000").
        return (string) preg_replace('/fill="#000(000)?"/', 'fill="'.$color.'"', $svg);
    }
}
