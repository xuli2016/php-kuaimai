<?php

namespace Kuaimai\Request\Misc;

class CainiaoBindRequest
{
    public ?string $imei = null;
    /** Binding code (valid for 5 minutes) */
    public ?string $code = null;
}
