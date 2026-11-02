<?php

declare(strict_types=1);

// 在非 Windows 主机上也覆盖 Windows 的程序名、PATH 分隔符及默认安装目录探测。
namespace Kuaimai\Util {
    const PHP_OS_FAMILY = 'Windows';
    const PATH_SEPARATOR = ';';
}

namespace {
    require dirname(__DIR__) . '/src/Util/PdfUtils.php';

    use Kuaimai\Util\PdfUtils;

    function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    function writePdf(string $path, int $pages): void
    {
        $kids = [];
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
        for ($i = 0; $i < $pages; $i++) {
            $pageId = 3 + $i * 2;
            $kids[] = "$pageId 0 R";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 18 18] /Resources << >> /Contents ' . ($pageId + 1) . ' 0 R >>';
            $color = ['1 0 0', '0 1 0', '0 0 1'][$i % 3];
            $stream = "$color rg 0 0 18 18 re f\n";
            $objects[$pageId + 1] = '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . 'endstream';
        }
        $objects[2] = '<< /Type /Pages /Count ' . $pages . ' /Kids [' . implode(' ', $kids) . '] >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$object\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . count($offsets) . "\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size ' . count($offsets) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
        file_put_contents($path, $pdf);
    }

    function removeTree(string $path): void
    {
        foreach (scandir($path) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $child = $path . '/' . $name;
            if (is_dir($child) && !is_link($child)) {
                removeTree($child);
            } else {
                unlink($child);
            }
        }
        rmdir($path);
    }

    $variables = ['PATH', 'KUAIMAI_GS_PATH', 'ProgramW6432', 'ProgramFiles', 'ProgramFiles(x86)'];
    $original = [];
    foreach ($variables as $name) {
        $original[$name] = getenv($name);
    }
    $realGs = getenv('KUAIMAI_GS_PATH') ?: null;
    foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: '') as $directory) {
        foreach (PHP_OS_FAMILY === 'Windows' ? ['gswin64c.exe', 'gswin32c.exe'] : ['gs'] as $name) {
            $candidate = trim($directory, '"') . '/' . $name;
            if ($realGs === null && is_file($candidate) && is_executable($candidate)) {
                $realGs = $candidate;
            }
        }
    }
    $tmp = str_replace('\\', '/', sys_get_temp_dir()) . '/kuaimai_test_' . bin2hex(random_bytes(6));
    mkdir($tmp);
    $find = new ReflectionMethod(PdfUtils::class, 'findGhostscript');
    if (PHP_VERSION_ID < 80100) {
        $find->setAccessible(true);
    }

    try {
        foreach ($variables as $name) {
            putenv($name);
        }
        $bin = $tmp . '/Program Files/gs/gs10.08.0/bin';
        mkdir($bin, 0777, true);
        $exe = $bin . '/gswin64c.exe';
        file_put_contents($exe, 'test executable');
        chmod($exe, 0755);
        putenv('ProgramFiles=' . $tmp . '/Program Files');
        check($find->invoke(null) === $exe, 'Windows default installation must be found without PATH');

        putenv('ProgramFiles');
        putenv('PATH=' . $tmp . '/absent;"' . $bin . '"');
        check($find->invoke(null) === $exe, 'Windows semicolon PATH and quoted directories must work');
        rename($exe, $bin . '/gswin32c.exe');
        check($find->invoke(null) === $bin . '/gswin32c.exe', '32-bit Windows console executable must be found');
        putenv('KUAIMAI_GS_PATH="' . $bin . '/gswin32c.exe"');
        putenv('PATH=');
        check($find->invoke(null) === $bin . '/gswin32c.exe', 'Explicit executable must work without PATH');
        putenv('KUAIMAI_GS_PATH=' . $tmp . '/missing.exe');
        try {
            $find->invoke(null);
            throw new RuntimeException('Invalid configured path was accepted');
        } catch (RuntimeException $e) {
            check(str_contains($e->getMessage(), 'KUAIMAI_GS_PATH'), 'Invalid path must explain the configuration error');
        }
        putenv('KUAIMAI_GS_PATH');
        check($find->invoke(null) === null, 'No installation must return null');
        echo "PASS: Windows discovery, 32-bit fallback and explicit path validation\n";

        if ($realGs === null || !extension_loaded('gd')) {
            echo "SKIP: real PDF conversion requires Ghostscript and GD\n";
        } else {
            // 可执行文件和 PDF 路径包含空格、百分号、括号及 shell 元字符。
            if (PHP_OS_FAMILY !== 'Windows') {
                $linkedGs = $tmp . '/gs % & console';
                symlink($realGs, $linkedGs);
                $realGs = $linkedGs;
            }
            putenv('KUAIMAI_GS_PATH=' . $realGs);
            $pdf = $tmp . '/sample % & (pages).pdf';
            writePdf($pdf, 3);
            $convert = new ReflectionMethod(PdfUtils::class, 'convertWithGhostscript');
            if (PHP_VERSION_ID < 80100) {
                $convert->setAccessible(true);
            }
            $before = glob(sys_get_temp_dir() . '/kuaimai_pdf_*') ?: [];
            $images = $convert->invoke(null, $pdf, 72, 0);
            check(count($images) === 3, 'All PDF pages must be rendered');
            foreach ($images as $i => $image) {
                check(imagesx($image) === 18 && imagesy($image) === 18, 'Requested DPI must be respected');
                check((imagecolorat($image, 9, 9) & 0xFFFFFF) === [0xFF0000, 0x00FF00, 0x0000FF][$i], 'Page order and colors must be preserved');
            }
            check(count($convert->invoke(null, $pdf, 72, 1)) === 1, 'Single-page limit must be respected');
            check(count($convert->invoke(null, $pdf, 72, 9)) === 3, 'Limit beyond document length must stop at EOF');
            writePdf($pdf, 101);
            check(count($convert->invoke(null, $pdf, 72, 0)) === 101, 'Unlimited conversion must not truncate at 100 pages');
            file_put_contents($pdf, 'invalid PDF');
            try {
                $convert->invoke(null, $pdf, 72, 0);
                throw new RuntimeException('Invalid PDF was accepted');
            } catch (RuntimeException $e) {
                check(str_contains($e->getMessage(), 'Ghostscript 执行失败'), 'Conversion failure must preserve Ghostscript diagnostics');
            }
            check(array_diff(glob(sys_get_temp_dir() . '/kuaimai_pdf_*') ?: [], $before) === [], 'Temporary output must be removed after success and failure');
            echo "PASS: real conversion, special paths, DPI, page limits, 101 pages and failure cleanup\n";
        }
    } finally {
        foreach ($original as $name => $value) {
            putenv($value === false ? $name : $name . '=' . $value);
        }
        removeTree($tmp);
    }
}
