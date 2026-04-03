<?php

namespace Kuaimai\Request\Tspl;

class TsplPdfPrintRequest
{
    public ?string $sn        = null;
    /** Path to PDF file */
    public ?string $filePath  = null;
    /** DPI: 203 or 300 */
    public int     $dpi       = 203;
    /** Label width in mm */
    public float   $width     = 75.0;
    /** Label height in mm */
    public float   $height    = 100.0;
}
