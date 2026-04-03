<?php

namespace Kuaimai\Request\Tspl;

class TsplTemplatePrintRequest
{
    public ?string $sn                 = null;
    /** For KM360C cloud printer */
    public ?string $imei               = null;
    public ?int    $templateId         = null;
    /** Single-object render data JSON string */
    public ?string $renderData         = null;
    /** Array render data JSON string */
    public ?string $renderDataArray    = null;
    public int     $printTimes         = 1;
    /** If true, render to image locally before sending */
    public bool    $image              = false;
    /** DPI: 203 or 300 */
    public int     $dpi                = 203;
}
