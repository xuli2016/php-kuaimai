<?php

namespace Kuaimai\Request\Tspl;

class TsplXmlWriteRequest
{
    public ?string $sn         = null;
    public ?string $xmlStr     = null;
    public int     $printTimes = 1;
    /** Java 默认值为 true */
    public bool    $image      = true;
    /** JSON array of job IDs */
    public ?string $jobs       = null;
}
