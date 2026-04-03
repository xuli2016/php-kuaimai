<?php

namespace Kuaimai\Request\Esc;

class EscTemplatePrintRequest
{
    public ?string $sn             = null;
    public ?int    $templateId     = null;
    /** Single-object render data JSON string */
    public ?string $renderData     = null;
    public int     $copies         = 1;
    /** Optional cutter flag. Service expects Integer: 1=cut, 0=no cut. */
    public ?int    $cut            = null;
    public int     $volume         = 0;
    public int     $endFeed        = 0;
}
