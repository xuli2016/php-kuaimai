<?php

namespace Kuaimai\Util;

use Kuaimai\Util\ZLibUtils;

class HexUtils
{
    // -------------------------------------------------------------------------
    // Base64 / image helpers
    // -------------------------------------------------------------------------

    public static function removeBase64Header(string $base64): string
    {
        $pos = strpos($base64, ';base64,');
        return $pos !== false ? substr($base64, $pos + 8) : $base64;
    }

    public static function base64ToImage(string $base64): \GdImage
    {
        $bytes = base64_decode(self::removeBase64Header($base64));
        $img   = imagecreatefromstring($bytes);
        if ($img === false) {
            throw new \RuntimeException('Invalid image data');
        }
        return $img;
    }

    public static function imageToBase64(\GdImage $img): string
    {
        ob_start();
        imagepng($img);
        return 'data:image/png;base64,' . base64_encode(ob_get_clean());
    }

    public static function resize(\GdImage $src, int $w, int $h): \GdImage
    {
        $dst = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
        return $dst;
    }

    // -------------------------------------------------------------------------
    // Otsu threshold — matches Java TsplImageUtil.getThreshold() exactly
    // -------------------------------------------------------------------------

    public static function getThreshold(\GdImage $img): int
    {
        $w = imagesx($img);
        $h = imagesy($img);

        $hist  = array_fill(0, 256, 0);
        $total = $w * $h;

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb  = imagecolorat($img, $x, $y);
                $r    = ($rgb >> 16) & 0xFF;
                $g    = ($rgb >> 8)  & 0xFF;
                $b    = $rgb         & 0xFF;
                $gray = (int)($r * 0.3 + $g * 0.59 + $b * 0.11);
                $hist[$gray]++;
            }
        }

        // Java's nested-loop Otsu (matches exactly)
        $nBestTH  = 128;
        $deltaMax = 0.0;

        for ($i = 0; $i < 256; $i++) {
            $w0 = $w1 = $u0tmp = $u1tmp = 0.0;
            for ($j = 0; $j < 256; $j++) {
                if ($j <= $i) { $w0 += $hist[$j]; $u0tmp += $j * $hist[$j]; }
                else           { $w1 += $hist[$j]; $u1tmp += $j * $hist[$j]; }
            }
            if ($w0 == 0 || $w1 == 0) continue;
            $u0    = $u0tmp / $w0;
            $u1    = $u1tmp / $w1;
            $delta = $w0 * $w1 * pow($u0 - $u1, 2);
            if ($delta > $deltaMax) { $deltaMax = $delta; $nBestTH = $i; }
        }

        return $nBestTH;
    }

    // -------------------------------------------------------------------------
    // TSPL BITMAP instruction — matches Java TsplImageUtil.addImageNew()
    //
    // KEY: mode 0/3 → white pixel = bit 1, black pixel = bit 0
    //      mode 4   → white pixel = bit 0, black pixel = bit 1
    // -------------------------------------------------------------------------

    /**
     * @return string[]  One BITMAP command string per chunk
     */
    public static function tsplBitmapBytes(\GdImage $img, int $oX = 0, int $oY = 0, int $mode = 0): array
    {
        $w         = imagesx($img);
        $h         = imagesy($img);
        $wModByte  = ($w % 8) === 0 ? 0 : 8 - ($w % 8);
        $wPrintByte = ($w + $wModByte) / 8;

        $threshold = self::getThreshold($img);

        // Decide chunking (same calc as Java calculateImageSizeInKB / 50KB)
        $totalKB      = ($w * $h) / (8 * 1024);
        $numChunks    = $totalKB > 50 ? (int)ceil($totalKB / 50) : 1;
        $rowsPerChunk = $numChunks > 1 ? (int)floor($h / $numChunks) : $h;

        $instructions = [];

        for ($chunkIdx = 0; $chunkIdx < $numChunks; $chunkIdx++) {
            $yStart   = $chunkIdx * $rowsPerChunk;
            $chunkH   = ($chunkIdx < $numChunks - 1) ? $rowsPerChunk : ($h - $yStart);

            // Build bit string exactly like Java
            $bits = '';
            for ($y = $yStart; $y < $yStart + $chunkH; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $rgb  = imagecolorat($img, $x, $y);
                    $r    = ($rgb >> 16) & 0xFF;
                    $g    = ($rgb >> 8)  & 0xFF;
                    $b    = $rgb         & 0xFF;
                    $gray = (int)($r * 0.3 + $g * 0.59 + $b * 0.11);

                    if ($mode === 4) {
                        $bits .= ($gray === 255) ? '0' : '1';   // mode4: white=0, black=1
                    } else {
                        $bits .= ($gray <= $threshold) ? '0' : '1'; // mode0/3: black=0, white=1
                    }
                }
                // Pad row to 8-bit boundary
                for ($k = 0; $k < $wModByte; $k++) {
                    $bits .= ($mode === 4) ? '0' : '1';
                }
            }

            // Pack bits into bytes using Java's nibble method
            $byteData = '';
            for ($i = 0; $i < strlen($bits); $i += 8) {
                $y1 = bindec(substr($bits, $i,     4));
                $z1 = bindec(substr($bits, $i + 4, 4));
                $byteData .= chr((($y1 << 4) | $z1) & 0xFF);
            }

            $actualMode = ($mode === 4) ? 3 : $mode;

            if ($actualMode === 3) {
                $byteData = ZLibUtils::compress($byteData);
                $header   = sprintf("BITMAP %d,%d,%d,%d,%d,%d,", $oX, $oY + $yStart, $wPrintByte, $chunkH, $actualMode, strlen($byteData));
            } else {
                $header = sprintf("BITMAP %d,%d,%d,%d,%d,", $oX, $oY + $yStart, $wPrintByte, $chunkH, $actualMode);
            }

            $instructions[] = $header . $byteData . "\r\n";
        }

        return $instructions;
    }

    // -------------------------------------------------------------------------
    // ESC/POS image instruction — matches Java HexUtils.escimgToInstruct()
    // -------------------------------------------------------------------------

    public static function escImgToInstruct(\GdImage $img, int $mode = 0): string
    {
        $w          = imagesx($img);
        $h          = imagesy($img);
        $wModByte   = ($w % 8) === 0 ? 0 : 8 - ($w % 8);
        $wPrintByte = ($w + $wModByte) / 8;

        // ESC/POS uses simple average threshold (not Otsu): avg >= 200 → white
        $bits = '';
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($img, $x, $y);
                $r   = ($rgb >> 16) & 0xFF;
                $g   = ($rgb >> 8)  & 0xFF;
                $b   = $rgb         & 0xFF;
                $bits .= (($r + $g + $b) / 3 >= 200) ? '0' : '1';
            }
            for ($k = 0; $k < $wModByte; $k++) {
                $bits .= '0';
            }
        }

        // Pack using same nibble method as Java
        $pixelBytes = '';
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $y1 = bindec(substr($bits, $i,     4));
            $z1 = bindec(substr($bits, $i + 4, 4));
            $pixelBytes .= chr((($y1 << 4) | $z1) & 0xFF);
        }

        $h1   = $h % 256;
        $h256 = (int)($h / 256);

        if ($mode === 4) {
            $pixelBytes  = ZLibUtils::compress($pixelBytes);
            $len         = strlen($pixelBytes);
            $header      = pack('C*', 29, 118, 48, $mode, $wPrintByte, 0, $h1, $h256, $len % 256, (int)($len / 256));
        } else {
            $header = pack('C*', 29, 118, 48, $mode, $wPrintByte, 0, $h1, $h256);
        }

        return $header . $pixelBytes;
    }

    /**
     * ESC/POS image bytes with chunking + compression — matches Java TsplImageUtil.getEscImageBytes()
     * Splits large images into ~50KB chunks, each compressed independently.
     */
    public static function getEscImageBytes(\GdImage $img, bool $compress = true): string
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $bytesPerRow = (int)ceil($w / 8.0);
        $totalSizeKB = ($bytesPerRow * $h) / 1024.0;
        $partCount   = max(1, (int)ceil($totalSizeKB / 50.0));
        $mode        = $compress ? 4 : 0;

        $result = '';
        if ($partCount > 1) {
            for ($i = 0; $i < $partCount; $i++) {
                $yStart  = (int)($i * $h / $partCount);
                $yEnd    = (int)(($i + 1) * $h / $partCount);
                $subH    = $yEnd - $yStart;
                if ($subH <= 0) {
                    continue;
                }
                $subImg = imagecrop($img, ['x' => 0, 'y' => $yStart, 'width' => $w, 'height' => $subH]);
                if ($subImg === false) {
                    continue;
                }
                $result .= self::escImgToInstruct($subImg, $mode);
                imagedestroy($subImg);
            }
        } else {
            $result = self::escImgToInstruct($img, $mode);
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Misc binary helpers
    // -------------------------------------------------------------------------

    public static function hexStr2Bytes(string $hex): string  { return hex2bin($hex) ?: ''; }
    public static function bytesToHexStr(string $bytes): string { return bin2hex($bytes); }
}
