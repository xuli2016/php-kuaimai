<?php

namespace Kuaimai\Util;

/**
 * PDF 转图片工具类
 * 对应 Java HexUtils.convertPdfToImage() / convertPdfsToImage()
 *
 * 支持两种后端（按优先级自动选择）：
 *   1. Imagick 扩展（如已安装）
 *   2. Ghostscript 命令行（gs）
 */
class PdfUtils
{
    /**
     * 将 PDF 第一页转换为 GdImage（对应 Java convertPdfToImage）
     */
    public static function convertPdfToImage(string $filePath, int $dpi = 203): \GdImage
    {
        $images = self::convertPdfsToImage($filePath, $dpi, 1);
        if (empty($images)) {
            throw new \RuntimeException('PDF 转换图片失败：未生成任何图片');
        }
        return $images[0];
    }

    /**
     * 将 PDF 所有页转换为 GdImage 数组（对应 Java convertPdfsToImage）
     *
     * @param string $filePath PDF 文件路径
     * @param int    $dpi      渲染 DPI（203 或 300）
     * @param int    $maxPages 最大页数，0=不限制
     * @return \GdImage[]
     */
    public static function convertPdfsToImage(string $filePath, int $dpi = 203, int $maxPages = 0): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException('PDF 文件不存在: ' . $filePath);
        }

        if (extension_loaded('imagick')) {
            return self::convertWithImagick($filePath, $dpi, $maxPages);
        }

        return self::convertWithGhostscript($filePath, $dpi, $maxPages);
    }

    // -------------------------------------------------------------------------
    // Imagick 后端
    // -------------------------------------------------------------------------

    private static function convertWithImagick(string $filePath, int $dpi, int $maxPages): array
    {
        $imagick = new \Imagick();
        $imagick->setResolution($dpi, $dpi);
        $imagick->readImage($filePath);

        $images    = [];
        $pageCount = $imagick->getNumberImages();
        $limit     = ($maxPages > 0) ? min($maxPages, $pageCount) : $pageCount;

        for ($i = 0; $i < $limit; $i++) {
            $imagick->setIteratorIndex($i);
            $imagick->setImageFormat('png');
            $imagick->setImageBackgroundColor('white');
            $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

            $pngData = (string)$imagick;
            $gdImage = imagecreatefromstring($pngData);
            if ($gdImage === false) {
                throw new \RuntimeException("PDF 第 {$i} 页转换图片失败");
            }
            $images[] = $gdImage;
        }

        $imagick->clear();
        $imagick->destroy();
        return $images;
    }

    // -------------------------------------------------------------------------
    // Ghostscript 后端
    // -------------------------------------------------------------------------

    private static function convertWithGhostscript(string $filePath, int $dpi, int $maxPages): array
    {
        $gsPath = self::findGhostscript();
        if ($gsPath === null) {
            throw new \RuntimeException(
                'PDF 转图片需要 Imagick 扩展或 Ghostscript (gs) 命令行工具，当前环境均未找到。' . PHP_EOL .
                '安装方式：' . PHP_EOL .
                '  CentOS: yum install -y ghostscript' . PHP_EOL .
                '  Ubuntu: apt-get install -y ghostscript' . PHP_EOL .
                '  macOS:  brew install ghostscript'
            );
        }

        // 先获取 PDF 总页数
        $pageCount = self::getPdfPageCount($gsPath, $filePath);
        $limit     = ($maxPages > 0) ? min($maxPages, $pageCount) : $pageCount;

        $tmpDir = sys_get_temp_dir() . '/kuaimai_pdf_' . uniqid();
        if (!mkdir($tmpDir, 0777, true)) {
            throw new \RuntimeException('无法创建临时目录: ' . $tmpDir);
        }

        try {
            $outPattern = $tmpDir . '/page-%03d.png';

            // Ghostscript: 将 PDF 渲染为 PNG
            $cmd = sprintf(
                '%s -dNOPAUSE -dBATCH -dSAFER -sDEVICE=png16m -r%d -dFirstPage=1 -dLastPage=%d -sOutputFile=%s %s 2>&1',
                escapeshellarg($gsPath),
                $dpi,
                $limit,
                escapeshellarg($outPattern),
                escapeshellarg($filePath)
            );
            exec($cmd, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \RuntimeException('Ghostscript 执行失败 (code=' . $returnCode . '): ' . implode("\n", $output));
            }

            // 读取生成的 PNG 文件
            $images = [];
            for ($i = 1; $i <= $limit; $i++) {
                $pngFile = sprintf('%s/page-%03d.png', $tmpDir, $i);
                if (!file_exists($pngFile)) {
                    break;
                }
                $gdImage = imagecreatefrompng($pngFile);
                if ($gdImage === false) {
                    throw new \RuntimeException("PDF 第 {$i} 页 PNG 加载失败");
                }
                $images[] = $gdImage;
                unlink($pngFile);
            }

            return $images;
        } finally {
            // 清理临时目录
            @rmdir($tmpDir);
        }
    }

    private static function getPdfPageCount(string $gsPath, string $filePath): int
    {
        $cmd = sprintf(
            '%s -q -dNODISPLAY -c "(%s) (r) file runpdfbegin pdfpagecount = quit" 2>&1',
            escapeshellarg($gsPath),
            str_replace(['(', ')'], ['\\(', '\\)'], $filePath)
        );
        exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && !empty($output)) {
            $count = (int)trim($output[0]);
            if ($count > 0) {
                return $count;
            }
        }

        // fallback: 假设至少 1 页，让 gs 渲染时自然截止
        return 100;
    }

    private static function findGhostscript(): ?string
    {
        foreach (['gs', '/usr/bin/gs', '/usr/local/bin/gs', '/opt/homebrew/bin/gs'] as $path) {
            $fullPath = trim(shell_exec('which ' . escapeshellarg($path) . ' 2>/dev/null') ?? '');
            if ($fullPath !== '' && is_executable($fullPath)) {
                return $fullPath;
            }
            if ($path !== 'gs' && is_executable($path)) {
                return $path;
            }
        }
        return null;
    }
}
