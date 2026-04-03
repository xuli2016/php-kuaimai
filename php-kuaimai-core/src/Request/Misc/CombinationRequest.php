<?php

namespace Kuaimai\Request\Misc;

class CombinationRequest
{
    public ?string $sn       = null;
    /** Base64-encoded instruction bytes */
    public ?string $instructs = null;
    public ?string $extra     = null;
    public ?string $prereq    = null;
}
