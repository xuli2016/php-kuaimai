<?php

namespace Kuaimai\Request\Esc;

class EscImageRequest
{
    public ?string $sn          = null;
    /** Base64 image string (may include data URI prefix) */
    public ?string $imageBase64  = null;
    /** Direct GD image resource (alternative to base64) */
    public mixed   $bufferedImage = null;
    /** Print width in mm, default 58 */
    public float   $printWidth   = 58.0;
    /** Extra feed lines after print */
    public int     $endFeed      = 0;
}
