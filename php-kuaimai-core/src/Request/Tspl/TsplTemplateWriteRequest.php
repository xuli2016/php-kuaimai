<?php

namespace Kuaimai\Request\Tspl;

class TsplTemplateWriteRequest
{
    public ?string $sn         = null;
    public ?int    $templateId = null;
    public ?string $renderData = null;
    public int     $printTimes = 1;
}
