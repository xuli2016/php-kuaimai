<?php

namespace Kuaimai\Util;

class TsplUtil
{
    /**
     * CLS - clear buffer
     */
    public static function crtiClear(): string
    {
        return "CLS\r\n";
    }

    /**
     * SIZE width mm, height mm
     */
    public static function crtiSize(float $width, float $height): string
    {
        return sprintf("SIZE %.1f mm, %.1f mm\r\n", $width, $height);
    }

    /**
     * PRINT m,n
     */
    public static function crtiPrint(int $m = 1, int $n = 1): string
    {
        return sprintf("PRINT %d,%d\r\n", $m, $n);
    }

    /**
     * TEXT x,y,font,rotation,x-mul,y-mul,"text"
     */
    public static function crtiText(int $x, int $y, string $font, int $rotation, int $xMul, int $yMul, string $text): string
    {
        return sprintf("TEXT %d,%d,\"%s\",%d,%d,%d,\"%s\"\r\n", $x, $y, $font, $rotation, $xMul, $yMul, $text);
    }

    /**
     * GAP gap mm, offset mm
     */
    public static function crtiGap(float $gap, float $offset = 0.0): string
    {
        return sprintf("GAP %.1f mm, %.1f mm\r\n", $gap, $offset);
    }

    /**
     * DIRECTION 0/1 (0 = forward, 1 = backward)
     */
    public static function crtiDirection(int $dir = 0): string
    {
        return sprintf("DIRECTION %d\r\n", $dir);
    }

    /**
     * DENSITY 1-15
     */
    public static function crtiDensity(int $density = 8): string
    {
        return sprintf("DENSITY %d\r\n", $density);
    }

    /**
     * SPEED 1-4
     */
    public static function crtiSpeed(int $speed = 4): string
    {
        return sprintf("SPEED %d\r\n", $speed);
    }

    /**
     * BAR x,y,width,height
     */
    public static function crtiBar(int $x, int $y, int $width, int $height): string
    {
        return sprintf("BAR %d,%d,%d,%d\r\n", $x, $y, $width, $height);
    }

    /**
     * BOX x1,y1,x2,y2,thickness
     */
    public static function crtiBox(int $x1, int $y1, int $x2, int $y2, int $thickness = 1): string
    {
        return sprintf("BOX %d,%d,%d,%d,%d\r\n", $x1, $y1, $x2, $y2, $thickness);
    }

    /**
     * QRCODE x,y,ecLevel,cellWidth,mode,rotation,"data"
     */
    public static function crtiQRCode(int $x, int $y, string $ecLevel, int $cellWidth, string $mode, int $rotation, string $data): string
    {
        return sprintf("QRCODE %d,%d,%s,%d,%s,%d,\"%s\"\r\n", $x, $y, $ecLevel, $cellWidth, $mode, $rotation, $data);
    }

    /**
     * BARCODE x,y,type,height,readable,rotation,narrow,wide,"data"
     */
    public static function crtiBarcode(int $x, int $y, string $type, int $height, int $readable, int $rotation, int $narrow, int $wide, string $data): string
    {
        return sprintf("BARCODE %d,%d,\"%s\",%d,%d,%d,%d,%d,\"%s\"\r\n",
            $x, $y, $type, $height, $readable, $rotation, $narrow, $wide, $data);
    }

    /**
     * CUT - paper cut
     */
    public static function crtiCut(): string
    {
        return "CUT\r\n";
    }

    /**
     * FEED n dots
     */
    public static function crtiFeed(int $dots): string
    {
        return sprintf("FEED %d\r\n", $dots);
    }

    /**
     * Build complete TSPL job from image bytes.
     * Returns the full instruction string to be sent.
     *
     * @param \GdImage $img
     * @param float    $widthMm   label width in mm
     * @param float    $heightMm  label height in mm
     * @param int      $printTimes
     * @param int      $mode      0=uncompressed, 3=zlib
     */
    public static function buildImageJob(\GdImage $img, float $widthMm, float $heightMm, int $printTimes = 1, int $mode = 0): string
    {
        $instructions  = self::crtiSize($widthMm, $heightMm);
        $instructions .= self::crtiGap(2.0);
        $instructions .= self::crtiDirection(0);
        $instructions .= self::crtiClear();

        $bitmapChunks = HexUtils::tsplBitmapBytes($img, 0, 0, $mode);
        foreach ($bitmapChunks as $chunk) {
            $instructions .= $chunk;
        }

        $instructions .= self::crtiPrint($printTimes, 1);
        return $instructions;
    }
}
