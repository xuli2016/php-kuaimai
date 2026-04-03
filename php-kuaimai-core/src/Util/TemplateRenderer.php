<?php

namespace Kuaimai\Util;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * TemplateRenderer
 * 对应 Java KuaimaiClient.tsplInstruct(replaceData, data, printTimes)
 */
class TemplateRenderer
{
    // -------------------------------------------------------------------------
    // 字体查找：按名称扫描系统字体目录
    // -------------------------------------------------------------------------

    /** @var array<string,string|null> */
    private static array $fontCache = [];

    private static array $fontDirs = [];
    /** @var array<string,string> */
    private static array $fontIndex = [];

    private static function getFontDirs(): array
    {
        if (empty(self::$fontDirs)) {
            $home = getenv('HOME') ?: '';
            $extraDirs = preg_split('/[:;]/', (string)(getenv('KUAIMAI_FONT_DIRS') ?: '')) ?: [];
            self::$fontDirs = array_values(array_unique(array_filter([
                '/System/Library/Fonts',
                '/System/Library/Fonts/Supplemental',
                '/Library/Fonts',
                $home ? $home . '/Library/Fonts' : '',
                '/usr/share/fonts',
                '/usr/local/share/fonts',
                'C:/Windows/Fonts',
                ...$extraDirs,
            ])));
        }
        return self::$fontDirs;
    }

    private static function normalizeFontKey(string $value): string
    {
        $value = trim($value, " \t\n\r\0\x0B\"'");
        $value = preg_replace('/\.(ttf|ttc|otf|otc)$/i', '', $value) ?? $value;
        $value = preg_replace('/\b(regular|normal|book|roman)\b/i', '', $value) ?? $value;
        $value = preg_replace('/[\s\-_]+/u', '', $value) ?? $value;
        return mb_strtolower($value);
    }

    /**
     * Build a one-time font index from system font directories.
     * We index recursively because Linux distributions often store fonts in nested directories.
     *
     * @return array<string,string>
     */
    private static function getFontIndex(): array
    {
        if (!empty(self::$fontIndex)) {
            return self::$fontIndex;
        }

        $index = [];
        foreach (self::getFontDirs() as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
                );
            } catch (\Throwable) {
                continue;
            }

            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                    continue;
                }

                $extension = strtolower($file->getExtension());
                if (!in_array($extension, ['ttf', 'ttc', 'otf', 'otc'], true)) {
                    continue;
                }

                $path = $file->getPathname();
                $base = $file->getBasename();
                $stem = pathinfo($base, PATHINFO_FILENAME);
                foreach ([$base, $stem] as $name) {
                    $normalized = self::normalizeFontKey($name);
                    if ($normalized !== '' && !isset($index[$normalized])) {
                        $index[$normalized] = $path;
                    }
                }
            }
        }

        self::$fontIndex = $index;
        return self::$fontIndex;
    }

    /**
     * Templates may store CSS-like font stacks such as "'PingFang SC', 'Microsoft YaHei', sans-serif".
     *
     * @return string[]
     */
    private static function splitFontFamilies(?string $fontFamily): array
    {
        if ($fontFamily === null) {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', $fontFamily) ?: [];
        $families = [];
        foreach ($parts as $part) {
            $part = trim($part, " \t\n\r\0\x0B\"'");
            if ($part !== '') {
                $families[] = $part;
            }
        }
        return $families;
    }

    /**
     * 通过字体名称查找 TTF/TTC 文件路径。
     * 策略：先精确匹配文件名（去空格、不区分大小写），再 fallback 到苹方/宋体/黑体。
     */
    private static function findFontPath(?string $fontFamily): ?string
    {
        if (!$fontFamily) return self::defaultFont();

        $key = $fontFamily;
        if (array_key_exists($key, self::$fontCache)) {
            return self::$fontCache[$key];
        }

        $families = self::splitFontFamilies($fontFamily);
        if (empty($families)) {
            $families = [$fontFamily];
        }

        $fontIndex = self::getFontIndex();
        foreach ($families as $family) {
            if (file_exists($family)) {
                return self::$fontCache[$key] = $family;
            }

            foreach (self::buildCandidates($family) as $candidate) {
                $normalized = self::normalizeFontKey($candidate);
                if ($normalized !== '' && isset($fontIndex[$normalized])) {
                    return self::$fontCache[$key] = $fontIndex[$normalized];
                }
            }
        }

        // 未找到 → fallback
        return self::$fontCache[$key] = self::defaultFont();
    }

    /**
     * 根据字体名生成候选文件名列表（覆盖中英文字体常见命名）
     */
    private static function buildCandidates(string $name): array
    {
        $n = trim($name, " \t\n\r\0\x0B\"'");
        // 中文字体名映射
        $zhMap = [
            '楷体'     => ['SIMKAI.TTF', 'STKaiti.ttf', 'KaiTi.ttf', 'simkai.ttf', 'Songti.ttc', 'PingFang.ttc'],
            '仿宋'     => ['STFangsong.ttf', 'FangSong.ttf', 'simfang.ttf', 'Songti.ttc', 'PingFang.ttc'],
            '宋体'     => ['Songti.ttc', 'STSong.ttf', 'simsun.ttc', 'PingFang.ttc'],
            '黑体'     => ['STHeiti Light.ttc', 'STHeiti Medium.ttc', 'simhei.ttf', 'PingFang.ttc'],
            '微软雅黑' => ['Microsoft Yahei-1.ttf', 'msyh.ttc', 'PingFang.ttc'],
            '苹方'     => ['PingFang.ttc'],
            '华文楷体' => ['SIMKAI.TTF', 'STKaiti.ttf', 'Songti.ttc', 'PingFang.ttc'],
            '华文宋体' => ['STSong.ttf', 'Songti.ttc', 'PingFang.ttc'],
            '华文黑体' => ['STHeiti Light.ttc', 'PingFang.ttc'],
        ];
        // English name aliases (case-insensitive key)
        $zhMapEn = [
            'kaiti'         => ['SIMKAI.TTF', 'STKaiti.ttf', 'KaiTi.ttf', 'simkai.ttf', 'Songti.ttc', 'PingFang.ttc'],
            'simkai'        => ['SIMKAI.TTF', 'STKaiti.ttf'],
            'stsong'        => ['STSong.ttf', 'Songti.ttc'],
            'nsimson'       => ['Songti.ttc', 'PingFang.ttc'],
            'nsimsum'       => ['Songti.ttc', 'PingFang.ttc'],
            'nsimsun'       => ['Songti.ttc', 'PingFang.ttc'],
            'simhei'        => ['STHeiti Light.ttc', 'simhei.ttf'],
            'microsoftyahei'=> ['Microsoft Yahei-1.ttf', 'msyh.ttc', 'PingFang.ttc'],
            'microsoft yahei' => ['Microsoft Yahei-1.ttf', 'msyh.ttc', 'PingFang.ttc'],
            'pingfang'      => ['PingFang.ttc'],
            'pingfangsc'    => ['PingFang.ttc'],
            'pingfang sc'   => ['PingFang.ttc'],
            'heiti sc'      => ['STHeiti Light.ttc', 'PingFang.ttc'],
            'songti sc'     => ['Songti.ttc', 'STSong.ttf'],
        ];

        if (isset($zhMap[$n])) {
            return array_merge($zhMap[$n], self::defaultCandidates());
        }
        $nLower = mb_strtolower($n);
        if (isset($zhMapEn[$nLower])) {
            return array_merge($zhMapEn[$nLower], self::defaultCandidates());
        }

        // 英文：尝试 "Name.ttf", "Name Bold.ttf", "Name.ttc" 等
        return array_unique(array_merge([
            $n,
            $n . '.ttf',
            $n . '.ttc',
            $n . '.otf',
            $n . '.otc',
            str_replace(' ', '', $n) . '.ttf',
            str_replace(' ', '', $n) . '.ttc',
            str_replace(' ', '', $n) . '.otf',
            str_replace(' ', '', $n) . '.otc',
            $n . ' Regular.ttf',
        ], self::defaultCandidates()));
    }

    private static function defaultCandidates(): array
    {
        return ['PingFang.ttc', 'Arial.ttf', 'Arial Unicode.ttf', 'Helvetica.ttc', 'Helvetica.ttf', 'Songti.ttc', 'STHeiti Light.ttc', 'DejaVuSans.ttf', 'NotoSansCJK-Regular.ttc'];
    }

    private static function defaultFont(): ?string
    {
        $fontIndex = self::getFontIndex();
        foreach (self::defaultCandidates() as $candidate) {
            $normalized = self::normalizeFontKey($candidate);
            if ($normalized !== '' && isset($fontIndex[$normalized])) {
                return $fontIndex[$normalized];
            }
        }
        return null;
    }

    private static function logRenderError(string $stage, \Throwable $e, array $meta = []): void
    {
        $json = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}';
        if ($json === false) {
            $json = '{}';
        }
        error_log(sprintf(
            '[Kuaimai TemplateRenderer][%s] %s (%s) meta=%s',
            $stage,
            $e->getMessage(),
            $e::class,
            $json
        ));
    }

    private static function isCriticalObject(array $obj): bool
    {
        return in_array($obj['componentType'] ?? '', ['barcode', 'qrcode'], true);
    }

    private static function handleObjectRenderError(array $obj, \Throwable $e): void
    {
        $meta = [
            'type' => $obj['type'] ?? '',
            'componentType' => $obj['componentType'] ?? '',
            'text' => $obj['text'] ?? '',
            'bindTable' => $obj['bindTable'] ?? '',
            'bindTableDataKey' => $obj['bindTableDataKey'] ?? '',
        ];
        self::logRenderError('object', $e, $meta);

        if (self::isCriticalObject($obj) || getenv('KUAIMAI_RENDER_STRICT')) {
            throw new \RuntimeException('模板元素渲染失败: ' . ($obj['componentType'] ?? $obj['type'] ?? 'unknown'), 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // 渲染数据提取 (对应 Java TsplImageUtil.getContent)
    // -------------------------------------------------------------------------

    private static function getContent(array $obj, array $renderData): string
    {
        $text      = (string)($obj['text'] ?? '');
        $bindTable = $obj['bindTable'] ?? null;
        $bindKey   = $obj['bindTableDataKey'] ?? null;

        if ($bindTable && isset($renderData[$bindTable])) {
            $arr = $renderData[$bindTable];
            $row = is_array($arr) ? ($arr[0] ?? null) : null;
            if (is_array($row) && $bindKey !== null && isset($row[$bindKey])) {
                $text = (string)$row[$bindKey];
            }
        }
        return $text;
    }

    // -------------------------------------------------------------------------
    // 文字绘制 (对应 Java TsplImageUtil.writeText / drawSingleText)
    // -------------------------------------------------------------------------

    private static function drawText(\GdImage $img, array $obj, array $renderData, float $rate): void
    {
        $scaleX   = (float)($obj['scaleX'] ?? 1);
        $width    = (float)($obj['width']  ?? 24) * $rate * $scaleX;
        $fontSize = (int)max(8, (float)($obj['fontSize'] ?? 12) * $rate * $scaleX);
        $left     = (float)($obj['left']   ?? 0) * $rate;
        $top      = (float)($obj['top']    ?? 0) * $rate;
        $angle    = (float)($obj['angle']  ?? 0);

        $fontWeight   = $obj['fontWeight']  ?? '';
        $fontStyle    = $obj['fontStyle']   ?? '';
        $fontFamily   = $obj['fontFamily']  ?? null;
        $textAlign    = $obj['textAlign']   ?? 'left';
        $maxRows      = isset($obj['kmTextboxMaxRows']) ? (int)$obj['kmTextboxMaxRows'] : 0;
        $reverse      = ($obj['backgroundColor'] ?? '') === '#000';

        $content  = self::getContent($obj, $renderData);
        $fontPath = self::findFontPath($fontFamily);
        $black    = imagecolorallocate($img, 0, 0, 0);
        $white    = imagecolorallocate($img, 255, 255, 255);

        // 换行
        $lines = self::wrapLines($content, $fontPath, $fontSize, (int)$width);
        if ($maxRows > 0) {
            $lines = array_slice($lines, 0, $maxRows);
        }

        $y = (int)$top;
        foreach ($lines as $line) {
            $y += $fontSize;

            if ($fontPath && file_exists($fontPath)) {
                $bbox  = imagettfbbox($fontSize, 0, $fontPath, $line);
                $textW = abs($bbox[4] - $bbox[0]);
                $x = match ($textAlign) {
                    'center' => (int)($left + ($width - $textW) / 2),
                    'right'  => (int)($left + $width - $textW),
                    default  => (int)$left,
                };
                if ($reverse) {
                    $bboxFull = imagettfbbox($fontSize, 0, $fontPath, $line);
                    $bw = abs($bboxFull[4] - $bboxFull[0]);
                    $bh = $fontSize + 4;
                    imagefilledrectangle($img, $x, $y - $fontSize, $x + $bw, $y + 4, $black);
                    imagettftext($img, $fontSize, (float)$angle, $x, $y, $white, $fontPath, $line);
                } else {
                    imagettftext($img, $fontSize, (float)$angle, $x, $y, $black, $fontPath, $line);
                }
            } else {
                // GD 内置字体 fallback
                imagestring($img, 5, (int)$left, $y - $fontSize, $line, $black);
            }
            $y += 8;  // Java 每行之间 +8 像素间距
        }
    }

    private static function wrapLines(string $text, ?string $fontPath, int $fontSize, int $maxWidth): array
    {
        if (!$text) return [''];
        if (!$fontPath || !file_exists($fontPath) || $maxWidth <= 0) return [$text];

        $lines = [];
        foreach (explode("\n", $text) as $para) {
            $cur = '';
            foreach (mb_str_split($para) as $ch) {
                $test = $cur . $ch;
                $bbox = imagettfbbox($fontSize, 0, $fontPath, $test);
                if ($cur !== '' && abs($bbox[4] - $bbox[0]) > $maxWidth) {
                    $lines[] = $cur;
                    $cur     = $ch;
                } else {
                    $cur = $test;
                }
            }
            $lines[] = $cur;
        }
        return $lines ?: [''];
    }

    // -------------------------------------------------------------------------
    // 条码 / 二维码 (对应 Java TsplImageUtil.writeCode)
    // -------------------------------------------------------------------------

    private static function drawCode(\GdImage $canvas, array $obj, array $renderData, float $rate): void
    {
        $content = self::getContent($obj, $renderData);
        if (!$content) return;

        $scaleX = (float)($obj['scaleX'] ?? 1);
        $scaleY = (float)($obj['scaleY'] ?? 1);
        $left   = (int)((float)($obj['left']   ?? 0) * $rate);
        $top    = (int)((float)($obj['top']    ?? 0) * $rate);
        $width  = (int)((float)($obj['width']  ?? 50) * $scaleX * $rate);
        $height = (int)((float)($obj['height'] ?? 30) * $scaleY * $rate);

        $componentType = $obj['componentType'] ?? '';

        if ($componentType === 'qrcode') {
            self::pasteQRCode($canvas, $content, $left, $top, $width, $height);
        } else {
            self::pasteBarcode($canvas, $obj, $content, $left, $top, $width, $height, $rate);
        }
    }

    private static function pasteQRCode(\GdImage $canvas, string $content, int $x, int $y, int $w, int $h): void
    {
        $options = new QROptions([
            'outputType'       => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
            'outputBase64'     => true,
            'imageTransparent' => false,
            'eccLevel'         => \chillerlan\QRCode\QRCode::ECC_M,
            'scale'            => max(1, (int)ceil(max($w, $h) / 25)),
            'margin'           => 0,
        ]);
        $b64 = (new QRCode($options))->render($content);
        $commaPos = strpos($b64, ',');
        if ($commaPos === false) {
            throw new \RuntimeException('二维码输出不是 data URI');
        }

        $png = base64_decode(substr($b64, $commaPos + 1), true);
        if ($png === false) {
            throw new \RuntimeException('二维码图片 Base64 解码失败');
        }

        $qrImg = imagecreatefromstring($png);
        if (!$qrImg) {
            throw new \RuntimeException('二维码 PNG 解析失败');
        }

        $resized = imagecreatetruecolor($w, $h);
        $white   = imagecolorallocate($resized, 255, 255, 255);
        imagefill($resized, 0, 0, $white);
        // Keep QR modules sharp instead of smoothing them with resampling.
        imagecopyresized($resized, $qrImg, 0, 0, 0, 0, $w, $h, imagesx($qrImg), imagesy($qrImg));
        imagecopy($canvas, $resized, $x, $y, 0, 0, $w, $h);
    }

    private static function pasteBarcode(\GdImage $canvas, array $obj, string $content, int $x, int $y, int $w, int $h, float $rate): void
    {
        $bcType  = strtoupper(str_replace(['-', '_'], '', $obj['barcodeType'] ?? 'CODE128'));
        $bcMap   = [
            'CODE128' => BarcodeGeneratorPNG::TYPE_CODE_128,
            'CODE39'  => BarcodeGeneratorPNG::TYPE_CODE_39,
            'CODE93'  => BarcodeGeneratorPNG::TYPE_CODE_93,
            'EAN13'   => BarcodeGeneratorPNG::TYPE_EAN_13,
            'EAN8'    => BarcodeGeneratorPNG::TYPE_EAN_8,
            'UPCA'    => BarcodeGeneratorPNG::TYPE_UPC_A,
            'UPC'     => BarcodeGeneratorPNG::TYPE_UPC_A,
            'ITF'     => BarcodeGeneratorPNG::TYPE_INTERLEAVED_2_5,
            'CODABAR' => BarcodeGeneratorPNG::TYPE_CODABAR,
        ];
        $type    = $bcMap[$bcType] ?? BarcodeGeneratorPNG::TYPE_CODE_128;
        $textPos = $obj['barcodeTextPosition'] ?? 'none';
        $fontSize = max(8, (int)round((float)($obj['fontSize'] ?? 12) * $rate));
        $fontPath = self::findFontPath($obj['fontFamily'] ?? null);
        $labelHeight = self::barcodeLabelHeight($content, $textPos, $fontPath, $fontSize);
        $barcodeHeight = max(1, $h - $labelHeight);

        $gen = new BarcodeGeneratorPNG();
        $png = $gen->getBarcode($content, $type, 1, $barcodeHeight, [0, 0, 0]);
        $bcImg = imagecreatefromstring($png);
        if (!$bcImg) {
            throw new \RuntimeException('条形码 PNG 解析失败');
        }

        $layout = imagecreatetruecolor($w, $h);
        $white  = imagecolorallocate($layout, 255, 255, 255);
        imagefill($layout, 0, 0, $white);

        $barcodeTop = $textPos === 'top' ? $labelHeight : 0;
        imagecopyresized($layout, $bcImg, 0, $barcodeTop, 0, 0, $w, $barcodeHeight, imagesx($bcImg), imagesy($bcImg));

        if ($labelHeight > 0) {
            $labelTop = $textPos === 'top' ? 0 : $barcodeHeight;
            self::drawBarcodeLabel($layout, $content, $fontPath, $fontSize, $labelTop, $labelHeight);
        }

        ['image' => $finalImg, 'x' => $dstX, 'y' => $dstY] = self::rotateCodeImage(
            $layout,
            $x,
            $y,
            $w,
            $h,
            (float)($obj['angle'] ?? 0)
        );
        imagecopy($canvas, $finalImg, $dstX, $dstY, 0, 0, imagesx($finalImg), imagesy($finalImg));
    }

    private static function barcodeLabelHeight(string $content, string $textPos, ?string $fontPath, int $fontSize): int
    {
        if ($textPos === 'none' || $content === '') {
            return 0;
        }

        if ($fontPath && file_exists($fontPath)) {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $content);
            if (is_array($bbox)) {
                $textHeight = abs($bbox[7] - $bbox[1]);
                return max(12, $textHeight + 6);
            }
        }

        return max(12, imagefontheight(5) + 4);
    }

    private static function drawBarcodeLabel(\GdImage $img, string $content, ?string $fontPath, int $fontSize, int $top, int $height): void
    {
        $black = imagecolorallocate($img, 0, 0, 0);
        $imgW  = imagesx($img);

        if ($fontPath && file_exists($fontPath)) {
            $drawFontSize = $fontSize;
            $bbox = imagettfbbox($drawFontSize, 0, $fontPath, $content);
            while (is_array($bbox) && abs($bbox[4] - $bbox[0]) > max(4, $imgW - 4) && $drawFontSize > 6) {
                $drawFontSize--;
                $bbox = imagettfbbox($drawFontSize, 0, $fontPath, $content);
            }
            if (is_array($bbox)) {
                $textW = abs($bbox[4] - $bbox[0]);
                $textH = abs($bbox[7] - $bbox[1]);
                $textX = max(0, (int)(($imgW - $textW) / 2));
                $baseY = $top + max($drawFontSize, (int)(($height + $textH) / 2));
                imagettftext($img, $drawFontSize, 0, $textX, $baseY, $black, $fontPath, $content);
                return;
            }
        }

        $font = 5;
        $textW = imagefontwidth($font) * strlen($content);
        $textH = imagefontheight($font);
        $textX = max(0, (int)(($imgW - $textW) / 2));
        $textY = $top + max(0, (int)(($height - $textH) / 2));
        imagestring($img, $font, $textX, $textY, $content, $black);
    }

    /**
     * Matches Java's special-case offsets for 90/180/270 degree barcode rotation.
     *
     * @return array{image:\GdImage,x:int,y:int}
     */
    private static function rotateCodeImage(\GdImage $img, int $x, int $y, int $w, int $h, float $angle): array
    {
        if ($angle <= 5) {
            return ['image' => $img, 'x' => $x, 'y' => $y];
        }

        $white = imagecolorallocate($img, 255, 255, 255);

        if ($angle > 85 && $angle < 95) {
            return ['image' => imagerotate($img, 270, $white), 'x' => $x - $h, 'y' => $y];
        }
        if ($angle > 175 && $angle < 185) {
            return ['image' => imagerotate($img, 180, $white), 'x' => $x - $w, 'y' => $y - $h];
        }
        if ($angle > 265 && $angle < 275) {
            return ['image' => imagerotate($img, 90, $white), 'x' => $x, 'y' => $y - $w];
        }
        if ($angle > 355) {
            return ['image' => $img, 'x' => $x, 'y' => $y];
        }

        $rotated = imagerotate($img, 360 - $angle, $white);
        $sqrt = sqrt(($w ** 2) + ($h ** 2)) * 0.5;
        $w2 = cos(deg2rad($angle + 45)) * $sqrt;
        $h2 = sin(deg2rad($angle + 45)) * $sqrt;
        $x0 = $x + $w2;
        $y0 = $y + $h2;
        $x1 = $x0 - (cos(deg2rad(45)) * $sqrt);
        $y1 = $y0 - (sin(deg2rad(45)) * $sqrt);

        return ['image' => $rotated, 'x' => (int)round($x1), 'y' => (int)round($y1)];
    }

    // -------------------------------------------------------------------------
    // 图片元素 (对应 Java TsplImageUtil.writeImage)
    // -------------------------------------------------------------------------

    private static function drawImage(\GdImage $canvas, array $obj, array $renderData, float $rate): void
    {
        $left = (int)((float)($obj['left'] ?? 0) * $rate);
        $top  = (int)((float)($obj['top']  ?? 0) * $rate);

        $componentType = $obj['componentType'] ?? '';
        $src = ($componentType === 'imagedata')
            ? self::getContent($obj, $renderData)
            : (string)($obj['src'] ?? '');

        if (!$src) return;
        $src = HexUtils::removeBase64Header($src);
        if (!$src) return;

        $bytes = base64_decode($src, true);
        if ($bytes === false) {
            throw new \RuntimeException('图片 Base64 解码失败');
        }

        $srcImg = imagecreatefromstring($bytes);
        if (!$srcImg) {
            throw new \RuntimeException('图片内容无法解析');
        }

        // Java: resize by srcW * scaleX * rate, srcH * scaleY * rate
        $scaleX  = (float)($obj['scaleX'] ?? 1);
        $scaleY  = (float)($obj['scaleY'] ?? 1);
        $srcW    = imagesx($srcImg);
        $srcH    = imagesy($srcImg);
        $dstW    = (int)round($srcW * $scaleX * $rate);
        $dstH    = (int)round($srcH * $scaleY * $rate);
        if ($dstW <= 0 || $dstH <= 0) return;

        $resized = imagecreatetruecolor($dstW, $dstH);
        $white   = imagecolorallocate($resized, 255, 255, 255);
        imagefill($resized, 0, 0, $white);
        imagecopyresampled($resized, $srcImg, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        $angle = (float)($obj['angle'] ?? 0);
        if ($angle > 5) {
            $rot = 0;
            if ($angle > 85  && $angle < 95)  { $rot = 270; $left -= $dstH; }
            elseif ($angle > 175 && $angle < 185) { $rot = 180; $left -= $dstW; $top -= $dstH; }
            elseif ($angle > 265 && $angle < 275) { $rot = 90;  $top  -= $dstW; }
            if ($rot) {
                $resized = imagerotate($resized, $rot, imagecolorallocate($resized, 255, 255, 255));
            }
        }

        imagecopy($canvas, $resized, $left, $top, 0, 0, imagesx($resized), imagesy($resized));
    }

    // -------------------------------------------------------------------------
    // 线条 (对应 Java TsplImageUtil.writeLine)
    // -------------------------------------------------------------------------

    private static function drawLine(\GdImage $img, array $obj, float $rate): void
    {
        $scaleX      = (float)($obj['scaleX'] ?? 1);
        $left        = (float)($obj['left']   ?? 0) * $rate;
        $top         = (float)($obj['top']    ?? 0) * $rate;
        $width       = (float)($obj['width']  ?? 0) * $scaleX * $rate;
        $angle       = (float)($obj['angle']  ?? 0);
        $strokeWidth = max(1, (int)((float)($obj['strokeWidth'] ?? 1)));
        $black       = imagecolorallocate($img, 0, 0, 0);

        if ($angle > 5) {
            $x2 = (int)($left + cos(deg2rad($angle)) * $width);
            $y2 = (int)($top  + sin(deg2rad($angle)) * $width);
        } else {
            $x2 = (int)($left + $width);
            $y2 = (int)$top;
        }

        imagesetthickness($img, $strokeWidth);
        imageline($img, (int)$left, (int)$top, $x2, $y2, $black);
        imagesetthickness($img, 1);
    }

    // -------------------------------------------------------------------------
    // 矩形 / 椭圆 (对应 Java TsplImageUtil.writeRect / writeEllipse)
    // -------------------------------------------------------------------------

    private static function drawRect(\GdImage $img, array $obj, float $rate, bool $oval = false): void
    {
        $scaleX      = (float)($obj['scaleX'] ?? 1);
        $scaleY      = (float)($obj['scaleY'] ?? 1);
        $left        = (float)($obj['left']   ?? 0) * $rate;
        $top         = (float)($obj['top']    ?? 0) * $rate;
        $width       = (float)($obj['width']  ?? 0) * $scaleX * $rate;
        $height      = (float)($obj['height'] ?? 0) * $scaleY * $rate;
        $fill        = (string)($obj['fill']  ?? '');
        $strokeWidth = max(1, (int)((float)($obj['strokeWidth'] ?? 1)));
        $rx          = (int)((float)($obj['rx'] ?? 0));
        $ry          = (int)((float)($obj['ry'] ?? 0));
        $black       = imagecolorallocate($img, 0, 0, 0);

        $x1 = (int)$left;
        $y1 = (int)$top;
        $x2 = (int)($left + $width);
        $y2 = (int)($top  + $height);
        $cx = (int)($left + $width  / 2);
        $cy = (int)($top  + $height / 2);

        imagesetthickness($img, $strokeWidth);
        if ($oval) {
            if ($fill === 'black') imagefilledellipse($img, $cx, $cy, (int)$width, (int)$height, $black);
            else                   imageellipse($img, $cx, $cy, (int)$width, (int)$height, $black);
        } else {
            if ($fill === 'black') imagefilledrectangle($img, $x1, $y1, $x2, $y2, $black);
            else                   imagerectangle($img, $x1, $y1, $x2, $y2, $black);
        }
        imagesetthickness($img, 1);
    }

    // -------------------------------------------------------------------------
    // 主渲染函数 (对应 Java KuaimaiClient.tsplInstruct(replaceData, data, printTimes))
    // -------------------------------------------------------------------------

    /**
     * @param array $templateData  getTemplate() 返回的 data 数组
     * @param array $renderData    单组渲染数据（renderDataArray 中的一个元素）
     */
    public static function render(array $templateData, array $renderData): \GdImage
    {
        $tagConfig    = self::parseJson($templateData['tagConfig']    ?? []);
        $templateInfo = self::parseJson($templateData['templateData'] ?? []);

        $widthMm  = (float)($tagConfig['width']  ?? 75);
        $heightMm = (float)($tagConfig['height'] ?? 100);
        $printDir = (float)($tagConfig['printDirection'] ?? 0);

        // Java: (Math.round(width)-1)*8 × (Math.round(height)-1)*8
        $canvasW = (int)((round($widthMm)  - 1) * 8);
        $canvasH = (int)((round($heightMm) - 1) * 8);

        // rate 计算（对应 Java viewportTransform）
        $viewport   = self::parseJsonArray($templateInfo['viewportTransform'] ?? []);
        $canvasZoom = (float)($viewport[0] ?? 1.0);
        $templateW  = (float)($templateInfo['width'] ?? $canvasW);
        $realW      = $canvasZoom > 0 ? $templateW / $canvasZoom : $templateW;
        $rate       = $realW > 0 ? ($widthMm * 8) / $realW : 1.0;

        // Java: TYPE_3BYTE_BGR, 白色背景
        $img   = imagecreatetruecolor($canvasW, $canvasH);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);


        $objects = self::parseJsonArray($templateInfo['objects'] ?? []);
        foreach ($objects as $obj) {
            if (!is_array($obj)) continue;
            try {
                match ($obj['type'] ?? '') {
                    'textbox' => self::drawText($img, $obj, $renderData, $rate),
                    'image'   => match ($obj['componentType'] ?? '') {
                        'qrcode', 'barcode' => self::drawCode($img, $obj, $renderData, $rate),
                        default             => self::drawImage($img, $obj, $renderData, $rate),
                    },
                    'line'    => self::drawLine($img, $obj, $rate),
                    'rect'    => self::drawRect($img, $obj, $rate, false),
                    'ellipse' => self::drawRect($img, $obj, $rate, true),
                    default   => null,
                };
            } catch (\Throwable $e) {
                self::handleObjectRenderError($obj, $e);
            }
        }

        // 旋转（对应 Java rotateImage）
        if ($printDir > 5) {
            $rotated = imagerotate($img, -(float)$printDir, $white);
            if ($rotated) $img = $rotated;
        }

        return $img;
    }

    // -------------------------------------------------------------------------
    // 工具方法
    // -------------------------------------------------------------------------

    private static function parseJson(mixed $v): array
    {
        if (is_array($v)) return $v;
        if (is_string($v)) return json_decode($v, true) ?? [];
        return [];
    }

    private static function parseJsonArray(mixed $v): array
    {
        $r = self::parseJson($v);
        return array_is_list($r) ? $r : [];
    }
}
