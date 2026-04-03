<?php

namespace Kuaimai\Request\Tspl;

class TsplInstructRequest
{
    public ?string $sn       = null;
    /** Base64-encoded TSPL instruction bytes */
    public ?string $instructs = null;
    public ?string $extra     = null;
    public ?string $prereq    = null;
}
