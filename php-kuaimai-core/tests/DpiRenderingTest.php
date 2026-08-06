<?php

declare(strict_types=1);

// picqer 2.x 在 PHP 8.5 会因内部 imagedestroy() 输出第三方弃用提示，不影响 SDK 测试结果。
error_reporting(E_ALL & ~E_DEPRECATED);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kuaimai\KuaimaiClient;
use Kuaimai\Request\Tspl\TsplImageRequest;
use Kuaimai\Request\Tspl\TsplTemplatePrintRequest;
use Kuaimai\Util\HexUtils;
use Kuaimai\Util\TemplateRenderer;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf('%s: expected=%s actual=%s', $message, var_export($expected, true), var_export($actual, true)));
    }
}

function assertTrueValue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array{x:int,y:int,width:int,height:int} */
function nonWhiteBounds(GdImage $image): array
{
    $minX = imagesx($image);
    $minY = imagesy($image);
    $maxX = -1;
    $maxY = -1;
    for ($y = 0; $y < imagesy($image); $y++) {
        for ($x = 0; $x < imagesx($image); $x++) {
            if ((imagecolorat($image, $x, $y) & 0xFFFFFF) !== 0xFFFFFF) {
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }
    }

    return $maxX < $minX
        ? ['x' => 0, 'y' => 0, 'width' => 0, 'height' => 0]
        : ['x' => $minX, 'y' => $minY, 'width' => $maxX - $minX + 1, 'height' => $maxY - $minY + 1];
}

function template(int $direction = 0, array $objects = []): array
{
    return [
        'tagConfig' => json_encode([
            'width' => 10,
            'height' => 20,
            'printDirection' => $direction,
        ], JSON_THROW_ON_ERROR),
        'templateData' => json_encode([
            'width' => 80,
            'viewportTransform' => [1],
            'objects' => $objects,
        ], JSON_THROW_ON_ERROR),
    ];
}

$image203 = TemplateRenderer::render(template(), []);
$image300 = TemplateRenderer::render300Dpi(template(), []);
assertSameValue(80, imagesx($image203), '203dpi canvas width');
assertSameValue(160, imagesy($image203), '203dpi canvas height');
assertSameValue(120, imagesx($image300), '300dpi canvas width');
assertSameValue(240, imagesy($image300), '300dpi canvas height');

$rotated300 = TemplateRenderer::render300Dpi(template(90), []);
assertSameValue(240, imagesx($rotated300), '300dpi rotated canvas width');
assertSameValue(120, imagesy($rotated300), '300dpi rotated canvas height');

$line = [
    'type' => 'line',
    'left' => 8,
    'top' => 16,
    'width' => 24,
    'scaleX' => 1,
    'strokeWidth' => 2,
    'angle' => 0,
];
$line203 = TemplateRenderer::render(template(0, [$line]), []);
$line300 = TemplateRenderer::render300Dpi(template(0, [$line]), []);
$bounds203 = nonWhiteBounds($line203);
$bounds300 = nonWhiteBounds($line300);
assertTrueValue(abs($bounds300['x'] - (int)round($bounds203['x'] * 1.5)) <= 1, '300dpi line x must scale by 1.5');
assertTrueValue(abs($bounds300['width'] - (int)round($bounds203['width'] * 1.5)) <= 2, '300dpi line width must scale by 1.5');
assertTrueValue($bounds300['height'] > $bounds203['height'], '300dpi fixed-dot stroke width must increase');

$components300 = TemplateRenderer::render300Dpi(template(0, [
    [
        'type' => 'rect',
        'left' => 4,
        'top' => 48,
        'width' => 24,
        'height' => 20,
        'scaleX' => 1,
        'scaleY' => 1,
        'strokeWidth' => 2,
    ],
    [
        'type' => 'ellipse',
        'left' => 36,
        'top' => 48,
        'width' => 24,
        'height' => 20,
        'scaleX' => 1,
        'scaleY' => 1,
        'strokeWidth' => 2,
    ],
    [
        'type' => 'image',
        'componentType' => 'qrcode',
        'left' => 4,
        'top' => 80,
        'width' => 28,
        'height' => 28,
        'scaleX' => 1,
        'scaleY' => 1,
        'text' => 'https://www.kuaimai.com',
    ],
    [
        'type' => 'image',
        'componentType' => 'barcode',
        'barcodeType' => 'CODE128',
        'barcodeTextPosition' => 'none',
        'left' => 36,
        'top' => 80,
        'width' => 40,
        'height' => 24,
        'scaleX' => 1,
        'scaleY' => 1,
        'text' => '123456',
    ],
]), []);
assertTrueValue(nonWhiteBounds($components300)['height'] > 80, '300dpi shapes and bitmap codes must render');

$ninePixelImage = imagecreatetruecolor(9, 1024);
$white = imagecolorallocate($ninePixelImage, 255, 255, 255);
imagefill($ninePixelImage, 0, 0, $white);
assertTrueValue(HexUtils::calculateImageSizeInKB($ninePixelImage) >= 2.0, 'bitmap size must include row byte padding');
$bitmap = implode('', HexUtils::tsplBitmapBytes($ninePixelImage, 0, 0, 3));
assertTrueValue(str_starts_with($bitmap, 'BITMAP 0,0,2,'), '9-dot image must use two bytes per row');

$invalidDpiRequest = new TsplImageRequest();
$invalidDpiRequest->sn = 'TEST-SN';
$invalidDpiRequest->bufferedImage = imagecreatetruecolor(1, 1);
$invalidDpiRequest->dpi = 600;
$invalidDpiResponse = KuaimaiClient::createClient('test-app', 'test-secret')->tsplImageDirectPrint($invalidDpiRequest);
assertSameValue(false, $invalidDpiResponse->status, 'invalid dpi status');
assertSameValue('dpi仅支持203或300', $invalidDpiResponse->message, 'invalid dpi message');

$invalidTemplateDpiRequest = new TsplTemplatePrintRequest();
$invalidTemplateDpiRequest->sn = 'TEST-SN';
$invalidTemplateDpiRequest->templateId = 1;
$invalidTemplateDpiRequest->dpi = 600;
$invalidTemplateDpiResponse = KuaimaiClient::createClient('test-app', 'test-secret')->tsplTemplatePrint($invalidTemplateDpiRequest);
assertSameValue(false, $invalidTemplateDpiResponse->status, 'invalid template dpi status');
assertSameValue('dpi仅支持203或300', $invalidTemplateDpiResponse->message, 'invalid template dpi message');

echo "DPI rendering tests passed\n";
