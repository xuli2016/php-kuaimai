<?php

namespace Kuaimai\Request\Tspl;

class TsplImageRequest
{
    public ?string    $sn          = null;
    /** Base64 image string (may include data URI prefix) */
    public ?string    $imageBase64  = null;
    /** Direct GD image resource (alternative to base64) */
    public mixed      $bufferedImage = null;
    /** DPI: 203 or 300 */
    public int        $dpi          = 203;
    /** Label width in mm (0 = auto) */
    public float      $setWidth     = 0.0;
    /** Label height in mm (0 = auto) */
    public float      $setHeight    = 0.0;
    public int        $printTimes   = 1;
}
