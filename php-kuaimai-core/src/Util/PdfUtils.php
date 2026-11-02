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
                '  macOS:  brew install ghostscript' . PHP_EOL .
                '  Windows: 安装 Ghostscript，使用 bin 目录下的 gswin64c.exe / gswin32c.exe。' . PHP_EOL .
                '已安装时可设置环境变量 KUAIMAI_GS_PATH 为命令行程序的完整路径。'
            );
        }

        $tmpDir = sys_get_temp_dir() . '/kuaimai_pdf_' . uniqid();
        if (!mkdir($tmpDir, 0777, true)) {
            throw new \RuntimeException('无法创建临时目录: ' . $tmpDir);
        }

        try {
            $outPattern = $tmpDir . '/page-%03d.png';

            // 直接传递参数，避免 Windows shell 破坏空格路径及 %03d 输出占位符。
            $command = [
                $gsPath, '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER', '-sDEVICE=png16m',
                '-r' . $dpi, '-dFirstPage=1',
            ];
            if ($maxPages > 0) {
                $command[] = '-dLastPage=' . $maxPages;
            }
            $command[] = '-sOutputFile=' . $outPattern;
            $command[] = '-f';
            $command[] = realpath($filePath);
            self::runGhostscript($command, $tmpDir . '/ghostscript.log');

            // 不预先查询页数，让 Ghostscript 在文档末尾自然结束。
            $pngFiles = glob($tmpDir . '/page-*.png') ?: [];
            natsort($pngFiles);
            if (empty($pngFiles)) {
                throw new \RuntimeException('PDF 转换图片失败：未生成任何图片');
            }
            $images = [];
            foreach (array_values($pngFiles) as $index => $pngFile) {
                $i = $index + 1;
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
            foreach (glob($tmpDir . '/*') ?: [] as $tmpFile) {
                @unlink($tmpFile);
            }
            @rmdir($tmpDir);
        }
    }

    /** @param string[] $command */
    private static function runGhostscript(array $command, string $logFile): void
    {
        if (!function_exists('proc_open')) {
            throw new \RuntimeException('Ghostscript 转换需要 proc_open，请检查 PHP 的 disable_functions 配置');
        }

        // 输出到文件，避免多页文档的 stdout/stderr 管道缓冲区阻塞。
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['file', $logFile, 'w'],
            2 => ['redirect', 1],
        ], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new \RuntimeException('无法启动 Ghostscript，请检查程序路径和 PHP 服务账号的执行权限');
        }
        fclose($pipes[0]);
        $returnCode = proc_close($process);
        if ($returnCode !== 0) {
            $output = file_get_contents($logFile);
            throw new \RuntimeException('Ghostscript 执行失败 (code=' . $returnCode . '): ' . $output);
        }
    }

    private static function findGhostscript(): ?string
    {
        $configuredPath = getenv('KUAIMAI_GS_PATH');
        if ($configuredPath !== false && trim($configuredPath) !== '') {
            $configuredPath = trim($configuredPath, " \t\n\r\0\x0B\"");
            if (is_file($configuredPath) && is_executable($configuredPath)) {
                return $configuredPath;
            }
            throw new \RuntimeException('KUAIMAI_GS_PATH 指定的 Ghostscript 程序不存在或不可执行: ' . $configuredPath);
        }

        $windows = PHP_OS_FAMILY === 'Windows';
        $names = $windows ? ['gswin64c.exe', 'gswin32c.exe', 'gs.exe'] : ['gs'];
        $directories = explode(PATH_SEPARATOR, getenv('PATH') ?: '');
        if (!$windows) {
            $directories = array_merge($directories, ['/usr/bin', '/usr/local/bin', '/opt/homebrew/bin']);
        }
        foreach ($directories as $directory) {
            $directory = trim($directory, " \t\n\r\0\x0B\"");
            if ($directory === '') {
                continue;
            }
            foreach ($names as $name) {
                $path = $directory . '/' . $name;
                if (is_file($path) && is_executable($path)) {
                    return $path;
                }
            }
        }

        // Windows 安装器通常不会为 PHP 服务进程设置 PATH，补充扫描默认安装目录。
        if ($windows) {
            $roots = [getenv('ProgramW6432'), getenv('ProgramFiles'), getenv('ProgramFiles(x86)')];
            foreach (array_unique(array_filter($roots)) as $root) {
                $versions = glob(str_replace('\\', '/', $root) . '/gs/gs*', GLOB_ONLYDIR) ?: [];
                usort($versions, 'strnatcasecmp');
                foreach (array_reverse($versions) as $directory) {
                    foreach ($names as $name) {
                        $path = $directory . '/bin/' . $name;
                        if (is_file($path) && is_executable($path)) {
                            return $path;
                        }
                    }
                }
            }
        }
        return null;
    }
}
