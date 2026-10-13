<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kuaimai\Util\HexUtils;
use Kuaimai\Util\TemplateRenderer;

putenv('KUAIMAI_RENDER_STRICT=1');

function checkBoldRendering(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function renderBoldText(array $object, int $dpi): GdImage
{
    $template = [
        'tagConfig' => ['width' => 75, 'height' => 100],
        'templateData' => [
            'width' => '561.3',
            'height' => '421.0',
            'viewportTransform' => [1.8709637882277859, 0, 0, 1.8709637882277859, 0, 0],
            'objects' => [$object],
        ],
    ];
    return $dpi === 300 ? TemplateRenderer::render300Dpi($template, []) : TemplateRenderer::render($template, []);
}

function boldImageBytes(GdImage $image): string
{
    ob_start();
    imagepng($image);
    return ob_get_clean();
}

/** @return array{pixels:int,left:int,right:int,top:int,bottom:int} */
function boldInkBounds(GdImage $image, bool $whiteInk = false): array
{
    $bounds = ['pixels' => 0, 'left' => imagesx($image), 'right' => -1, 'top' => imagesy($image), 'bottom' => -1];
    for ($y = 0; $y < imagesy($image); $y++) {
        for ($x = 0; $x < imagesx($image); $x++) {
            $level = imagecolorat($image, $x, $y) & 0xFF;
            if ($whiteInk ? $level <= 127 : $level >= 128) {
                continue;
            }
            $bounds['pixels']++;
            $bounds['left'] = min($bounds['left'], $x);
            $bounds['right'] = max($bounds['right'], $x);
            $bounds['top'] = min($bounds['top'], $y);
            $bounds['bottom'] = max($bounds['bottom'], $y);
        }
    }
    return $bounds;
}

$font = (new ReflectionMethod(TemplateRenderer::class, 'findFontPath'))->invoke(null, '黑体');
checkBoldRendering($font !== null, 'Install a TrueType/OpenType font to run text rendering tests');
$fontDirectory = tempnam(sys_get_temp_dir(), 'kuaimai-bold-');
checkBoldRendering($fontDirectory !== false, 'Create temporary font directory');
unlink($fontDirectory);
checkBoldRendering(mkdir($fontDirectory), 'Create temporary font directory');
$syntheticFont = $fontDirectory . '/SdkSynthetic-Regular.' . pathinfo($font, PATHINFO_EXTENSION);

try {
    // This isolated regular face has no bold sibling, exercising the Chinese-font fallback.
    checkBoldRendering(copy($font, $syntheticFont), 'Copy regular font fixture');
    $object = [
        'type' => 'textbox', 'componentType' => 'text',
        'left' => 35.31374102730163, 'top' => 32.781678469271455,
        'width' => 150, 'height' => 26.91024379170768,
        'scaleX' => 1, 'scaleY' => 1, 'angle' => 0,
        'text' => '双击编辑文本', 'fontSize' => 23.81437503690945,
        'fontFamily' => $syntheticFont, 'fontStyle' => 'normal',
        'backgroundColor' => '#fff', 'textAlign' => 'left',
    ];

    foreach ([203, 300] as $dpi) {
        $normal = renderBoldText($object + ['fontWeight' => 'normal'], $dpi);
        $bold = renderBoldText($object + ['fontWeight' => 'bold'], $dpi);
        $normalInk = boldInkBounds($normal);
        $boldInk = boldInkBounds($bold);
        checkBoldRendering($normalInk['pixels'] > 0, "$dpi DPI text must render");
        checkBoldRendering($boldInk['pixels'] > $normalInk['pixels'] * 1.05, "$dpi DPI synthetic bold must thicken strokes");
        checkBoldRendering($boldInk['left'] === $normalInk['left'], "$dpi DPI left aligned bold must preserve its starting position");
        checkBoldRendering($boldInk['right'] > $normalInk['right'], "$dpi DPI synthetic bold must include its extra width");
        checkBoldRendering(
            implode('', HexUtils::tsplBitmapBytes($normal, 0, 0, 3)) !== implode('', HexUtils::tsplBitmapBytes($bold, 0, 0, 3)),
            "$dpi DPI bold must survive TSPL bitmap conversion"
        );

        foreach (['bolder', 600, '700', 900, ' BOLD '] as $weight) {
            checkBoldRendering(boldImageBytes(renderBoldText($object + ['fontWeight' => $weight], $dpi)) === boldImageBytes($bold), "$dpi DPI bold weight alias");
        }
        foreach ([null, 'normal', 400, '500'] as $weight) {
            checkBoldRendering(boldImageBytes(renderBoldText($object + ['fontWeight' => $weight], $dpi)) === boldImageBytes($normal), "$dpi DPI regular weights must preserve regular rendering");
        }

        foreach (['center', 'right'] as $align) {
            $aligned = array_replace($object, ['textAlign' => $align]);
            $regularBounds = boldInkBounds(renderBoldText($aligned + ['fontWeight' => 'normal'], $dpi));
            $boldBounds = boldInkBounds(renderBoldText($aligned + ['fontWeight' => 'bold'], $dpi));
            $regularAnchor = $align === 'right' ? $regularBounds['right'] : ($regularBounds['left'] + $regularBounds['right']) / 2;
            $boldAnchor = $align === 'right' ? $boldBounds['right'] : ($boldBounds['left'] + $boldBounds['right']) / 2;
            checkBoldRendering(abs($regularAnchor - $boldAnchor) <= 1, "$dpi DPI $align alignment must account for bold width");
        }

        $wrapped = array_replace($object, ['width' => 35, 'text' => "双击编辑文本双击编辑文本\n双击编辑文本", 'fontWeight' => 'bold']);
        $allRows = boldInkBounds(renderBoldText($wrapped, $dpi));
        $oneRow = boldInkBounds(renderBoldText($wrapped + ['kmTextboxMaxRows' => 1], $dpi));
        checkBoldRendering($allRows['bottom'] > $oneRow['bottom'], "$dpi DPI bold must preserve wrapping and row limits");
        $rate = 75 * ($dpi === 300 ? 12 : 8) * 1.8709637882277859 / 561.3;
        checkBoldRendering($allRows['right'] - $allRows['left'] + 1 <= (int)($wrapped['width'] * $rate) + 1, "$dpi DPI wrapped bold must fit the text box");

        $rotated = array_replace($object, ['angle' => 90, 'top' => 180]);
        $regularRotated = boldInkBounds(renderBoldText($rotated + ['fontWeight' => 'normal'], $dpi));
        $boldRotated = boldInkBounds(renderBoldText($rotated + ['fontWeight' => 'bold'], $dpi));
        checkBoldRendering($boldRotated['pixels'] > $regularRotated['pixels'], "$dpi DPI rotated bold must thicken strokes");
        checkBoldRendering($boldRotated['top'] < $regularRotated['top'], "$dpi DPI bold offset must follow the rotated text baseline");

        // Crop to a fixed rectangle so white pixels belong to reverse text, not the canvas.
        $reverse = array_replace($object, ['backgroundColor' => '#000']);
        $crop = [
            'x' => (int)($object['left'] * $rate),
            'y' => (int)($object['top'] * $rate),
            'width' => $normalInk['right'] - (int)($object['left'] * $rate) + 1,
            'height' => (int)round($object['fontSize'] * $rate),
        ];
        $reverseNormal = imagecrop(renderBoldText($reverse + ['fontWeight' => 'normal'], $dpi), $crop);
        $reverseBold = imagecrop(renderBoldText($reverse + ['fontWeight' => 'bold'], $dpi), $crop);
        $reverseNormalInk = boldInkBounds($reverseNormal, true);
        $reverseBoldInk = boldInkBounds($reverseBold, true);
        checkBoldRendering($reverseBoldInk['pixels'] > $reverseNormalInk['pixels'] * 1.05, "$dpi DPI reverse text must thicken white strokes");

        printf("%d DPI synthetic bold: regular ink=%d, bold ink=%d\n", $dpi, $normalInk['pixels'], $boldInk['pixels']);
    }

    // If the host has a native bold face, compare automatic selection with using that face explicitly.
    $index = (new ReflectionMethod(TemplateRenderer::class, 'getFontIndex'))->invoke(null);
    $nativePair = null;
    foreach ([['arial', 'arialbold'], ['arial', 'arialbd'], ['dejavusans', 'dejavusansbold'], ['notosans', 'notosansbold']] as [$regularKey, $boldKey]) {
        if (isset($index[$regularKey], $index[$boldKey])) {
            $nativePair = [$index[$regularKey], $index[$boldKey]];
            break;
        }
    }
    if ($nativePair !== null) {
        foreach ([203, 300] as $dpi) {
            $nativeObject = array_replace($object, ['text' => 'ABC 123', 'fontFamily' => $nativePair[0], 'fontWeight' => 'bold']);
            $explicitBold = array_replace($nativeObject, ['fontFamily' => $nativePair[1], 'fontWeight' => 'normal']);
            checkBoldRendering(boldImageBytes(renderBoldText($nativeObject, $dpi)) === boldImageBytes(renderBoldText($explicitBold, $dpi)), "$dpi DPI native bold must use the bold face without adding synthetic weight");
        }
    } else {
        echo "Native bold check skipped: no supported regular/bold font pair installed\n";
    }
    echo "Bold rendering tests passed\n";
} finally {
    if (is_file($syntheticFont)) {
        unlink($syntheticFont);
    }
    rmdir($fontDirectory);
}
