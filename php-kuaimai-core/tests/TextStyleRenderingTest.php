<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kuaimai\Util\HexUtils;
use Kuaimai\Util\TemplateRenderer;

putenv('KUAIMAI_RENDER_STRICT=1');

function checkTextStyle(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function renderTextStyle(array $object, int $dpi): GdImage
{
    $template = [
        'tagConfig' => ['width' => 40, 'height' => 40],
        'templateData' => ['width' => 320, 'viewportTransform' => [1], 'objects' => [$object]],
    ];
    return $dpi === 300 ? TemplateRenderer::render300Dpi($template, []) : TemplateRenderer::render($template, []);
}

function textStyleBytes(GdImage $image): string
{
    ob_start();
    imagepng($image);
    return ob_get_clean();
}

/** @return array{pixels:int,left:int,right:int,top:int,bottom:int,width:int,height:int} */
function textStyleBounds(GdImage $image): array
{
    $bounds = ['pixels' => 0, 'left' => imagesx($image), 'right' => -1, 'top' => imagesy($image), 'bottom' => -1];
    for ($y = 0; $y < imagesy($image); $y++) {
        for ($x = 0; $x < imagesx($image); $x++) {
            if ((imagecolorat($image, $x, $y) & 0xFF) >= 128) {
                continue;
            }
            $bounds['pixels']++;
            $bounds['left'] = min($bounds['left'], $x);
            $bounds['right'] = max($bounds['right'], $x);
            $bounds['top'] = min($bounds['top'], $y);
            $bounds['bottom'] = max($bounds['bottom'], $y);
        }
    }
    $bounds['width'] = max(0, $bounds['right'] - $bounds['left'] + 1);
    $bounds['height'] = max(0, $bounds['bottom'] - $bounds['top'] + 1);
    return $bounds;
}

function textStyleRowRun(GdImage $image, int $y, int $left, int $right, bool $white = false): int
{
    $maxRun = $run = 0;
    for ($x = $left; $x <= $right; $x++) {
        $level = imagecolorat($image, $x, $y) & 0xFF;
        if ($white ? $level > 127 : $level < 128) {
            $maxRun = max($maxRun, ++$run);
        } else {
            $run = 0;
        }
    }
    return $maxRun;
}

$font = (new ReflectionMethod(TemplateRenderer::class, 'findFontPath'))->invoke(null, '黑体');
checkTextStyle($font !== null, 'Install a TrueType/OpenType font to run text style tests');
$directory = tempnam(sys_get_temp_dir(), 'kuaimai-styles-');
checkTextStyle($directory !== false, 'Create temporary font directory');
unlink($directory);
checkTextStyle(mkdir($directory), 'Create temporary font directory');
$regularFont = $directory . '/SdkStyleFixture-Regular.' . pathinfo($font, PATHINFO_EXTENSION);

try {
    // An isolated Chinese regular face forces synthetic italic and bold.
    checkTextStyle(copy($font, $regularFont), 'Copy regular font fixture');
    $object = [
        'type' => 'textbox', 'left' => 20, 'top' => 20, 'width' => 240, 'scaleX' => 1,
        'fontSize' => 26, 'fontFamily' => $regularFont, 'text' => 'HH    HH', 'textAlign' => 'left',
    ];
    foreach ([203, 300] as $dpi) {
        $rate = $dpi === 300 ? 1.5 : 1.0;
        $fontSize = (int)round($object['fontSize'] * $rate);
        $baseline = (int)($object['top'] * $rate) + $fontSize;
        $left = (int)($object['left'] * $rate);
        $normal = renderTextStyle($object, $dpi);
        $normalBounds = textStyleBounds($normal);
        checkTextStyle($normalBounds['pixels'] > 0, "$dpi DPI fixture must render");

        foreach ([['underline' => true], ['linethrough' => true], ['fontStyle' => 'italic']] as $style) {
            $image = renderTextStyle(array_replace($object, $style), $dpi);
            checkTextStyle(textStyleBytes($image) !== textStyleBytes($normal), "$dpi DPI each text style must change rendering");
            checkTextStyle(
                implode('', HexUtils::tsplBitmapBytes($image, 0, 0, 3)) !== implode('', HexUtils::tsplBitmapBytes($normal, 0, 0, 3)),
                "$dpi DPI each text style must survive TSPL bitmap conversion"
            );
        }
        $underline = renderTextStyle($object + ['underline' => true], $dpi);
        $lineThrough = renderTextStyle($object + ['linethrough' => true], $dpi);
        $underlineY = $baseline + max(1, (int)round($fontSize * 0.08));
        $strikeY = $baseline - (int)round($fontSize * 0.3);
        $right = $normalBounds['right'];
        checkTextStyle(textStyleRowRun($underline, $underlineY, $left, $right) >= $normalBounds['width'] - 2, "$dpi DPI underline must span the text including spaces");
        checkTextStyle(textStyleRowRun($lineThrough, $strikeY, $left, $right) >= $normalBounds['width'] - 2, "$dpi DPI strike must span the text including spaces");
        checkTextStyle(textStyleRowRun($normal, $strikeY, $left, $right) < $normalBounds['width'] / 2, "$dpi DPI undecorated spaces must stay empty");

        $reverse = renderTextStyle($object + ['backgroundColor' => '#000'], $dpi);
        $longReverse = renderTextStyle($object + ['backgroundColor' => '#000000'], $dpi);
        checkTextStyle(textStyleBytes($reverse) === textStyleBytes($longReverse), "$dpi DPI both Java black background formats must match");
        checkTextStyle(textStyleBounds($reverse)['pixels'] > $normalBounds['pixels'] * 2, "$dpi DPI reverse must draw a black text background");
        $reverseDecorated = renderTextStyle($object + ['backgroundColor' => '#000000', 'underline' => true, 'linethrough' => true], $dpi);
        checkTextStyle(textStyleRowRun($reverseDecorated, $underlineY, $left, $right, true) >= $normalBounds['width'] - 2, "$dpi DPI reverse underline must be white");
        checkTextStyle(textStyleRowRun($reverseDecorated, $strikeY, $left, $right, true) >= $normalBounds['width'] - 2, "$dpi DPI reverse strike must be white");

        $combined = array_replace($object, ['text' => '中文 ABC 123', 'fontStyle' => 'italic', 'fontWeight' => 'bold', 'underline' => true, 'linethrough' => true]);
        $combinedImage = renderTextStyle($combined, $dpi);
        foreach (['fontStyle' => 'normal', 'fontWeight' => 'normal', 'underline' => false, 'linethrough' => false] as $key => $disabled) {
            checkTextStyle(textStyleBytes($combinedImage) !== textStyleBytes(renderTextStyle(array_replace($combined, [$key => $disabled]), $dpi)), "$dpi DPI combined styles must preserve $key");
        }

        foreach (['left', 'center', 'right'] as $align) {
            $slanted = textStyleBounds(renderTextStyle(array_replace($object, ['fontStyle' => 'italic', 'textAlign' => $align]), $dpi));
            $upright = textStyleBounds(renderTextStyle(array_replace($object, ['textAlign' => $align]), $dpi));
            $slantedAnchor = $align === 'right' ? $slanted['right'] : ($slanted['left'] + $slanted['right']) / 2;
            $uprightAnchor = $align === 'right' ? $upright['right'] : ($upright['left'] + $upright['right']) / 2;
            if ($align !== 'left') {
                checkTextStyle(abs($slantedAnchor - $uprightAnchor) <= 2, "$dpi DPI $align alignment must account for italic width");
            }
        }
        $wrapped = array_replace($combined, ['text' => "中文文本 ABC 中文文本 ABC\n下一行", 'width' => 60]);
        $wrappedBounds = textStyleBounds(renderTextStyle($wrapped, $dpi));
        $oneRowBounds = textStyleBounds(renderTextStyle($wrapped + ['kmTextboxMaxRows' => 1], $dpi));
        checkTextStyle($wrappedBounds['bottom'] > $oneRowBounds['bottom'], "$dpi DPI styled text must wrap and respect row limits");
        checkTextStyle($wrappedBounds['width'] <= (int)($wrapped['width'] * $rate) + 2, "$dpi DPI italic wrapping must include extra stroke width");

        $rotation = array_replace($combined, ['text' => 'HH HH', 'left' => 140, 'top' => 140, 'backgroundColor' => '#000000']);
        $zeroBounds = textStyleBounds(renderTextStyle($rotation, $dpi));
        foreach ([90, 180, 270, -90] as $angle) {
            $bounds = textStyleBounds(renderTextStyle($rotation + ['angle' => $angle], $dpi));
            $quarterTurn = abs($angle) !== 180;
            checkTextStyle(abs($bounds['width'] - ($quarterTurn ? $zeroBounds['height'] : $zeroBounds['width'])) <= 1, "$dpi DPI rotated reverse background width");
            checkTextStyle(abs($bounds['height'] - ($quarterTurn ? $zeroBounds['width'] : $zeroBounds['height'])) <= 1, "$dpi DPI rotated reverse background height");
            checkTextStyle(abs($bounds['pixels'] - $zeroBounds['pixels']) <= 1, "$dpi DPI quarter turns must retain glyphs, decorations and background");
        }
        $empty = renderTextStyle(array_replace($combined, ['text' => '', 'backgroundColor' => '#000000']), $dpi);
        checkTextStyle(textStyleBounds($empty)['pixels'] === 0, "$dpi DPI empty text must not leave a black block or decoration");
        printf("%d DPI italic, underline, strike, reverse and combined styles passed\n", $dpi);
    }

    $index = (new ReflectionMethod(TemplateRenderer::class, 'getFontIndex'))->invoke(null);
    $native = null;
    foreach ([['arial', 'arialitalic', 'arialbolditalic'], ['arial', 'ariali', 'arialbi'],
        ['dejavusans', 'dejavusansoblique', 'dejavusansboldoblique']] as [$regularKey, $italicKey, $boldItalicKey]) {
        if (isset($index[$regularKey], $index[$italicKey], $index[$boldItalicKey])) {
            $native = [$index[$regularKey], $index[$italicKey], $index[$boldItalicKey]];
            break;
        }
    }
    if ($native) {
        foreach ([203, 300] as $dpi) {
            $italic = array_replace($object, ['fontFamily' => $native[0], 'fontStyle' => 'italic']);
            checkTextStyle(textStyleBytes(renderTextStyle($italic, $dpi)) === textStyleBytes(renderTextStyle(array_replace($italic, ['fontFamily' => $native[1]]), $dpi)), "$dpi DPI native italic face must be selected");
            $boldItalic = $italic + ['fontWeight' => 'bold'];
            $explicit = array_replace($italic, ['fontFamily' => $native[2]]);
            checkTextStyle(textStyleBytes(renderTextStyle($boldItalic, $dpi)) === textStyleBytes(renderTextStyle($explicit, $dpi)), "$dpi DPI native bold italic face must preserve both styles");
            checkTextStyle(textStyleBytes(renderTextStyle($explicit + ['fontWeight' => 'bold'], $dpi)) === textStyleBytes(renderTextStyle($explicit, $dpi)), "$dpi DPI explicit bold italic face must not be thickened twice");
        }
    } else {
        echo "Native italic checks skipped: no supported regular/italic/bold-italic font set installed\n";
    }

    // Also exercise the existing GD built-in-font fallback without relying on the host's font installation.
    $cacheProperty = new ReflectionProperty(TemplateRenderer::class, 'fontCache');
    $cache = $cacheProperty->getValue();
    $cacheProperty->setValue(null, $cache + ['__sdk_builtin_style_test__' => null]);
    try {
        $builtin = array_replace($object, ['fontFamily' => '__sdk_builtin_style_test__']);
        foreach ([203, 300] as $dpi) {
            $plain = renderTextStyle($builtin, $dpi);
            $styled = renderTextStyle($builtin + ['fontStyle' => 'italic', 'underline' => true, 'linethrough' => true, 'backgroundColor' => '#000000'], $dpi);
            checkTextStyle(textStyleBytes($plain) !== textStyleBytes($styled), "$dpi DPI built-in fonts must support text styles");
        }
    } finally {
        $cacheProperty->setValue(null, $cache);
    }
    echo "Text style rendering tests passed\n";
} finally {
    if (is_file($regularFont)) {
        unlink($regularFont);
    }
    rmdir($directory);
}
