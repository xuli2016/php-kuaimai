<?php

namespace Kuaimai\Request\Esc;

class EscPdfPrintRequest
{
    public ?string $sn         = null;
    /** Path to PDF file */
    public ?string $filePath   = null;
    /** Print width in mm, default 58 */
    public float   $printWidth = 58.0;
    /** Extra feed lines after print */
    public int     $endFeed    = 0;
}
