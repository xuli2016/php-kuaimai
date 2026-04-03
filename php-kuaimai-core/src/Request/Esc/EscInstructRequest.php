<?php

namespace Kuaimai\Request\Esc;

class EscInstructRequest
{
    public ?string $sn           = null;
    /** Base64-encoded ESC/POS instruction bytes */
    public ?string $instructions  = null;
    public int     $copies        = 1;
    public int     $volume        = 0;
    public int     $volumeIndex   = 0;
}
