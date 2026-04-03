<?php

namespace Kuaimai\Request\Misc;

class ResultRequest
{
    public ?string $sn       = null;
    /** Job ID array, e.g. ['1234567890']; legacy JSON array strings are also accepted. */
    public array|string|null $jobIds = null;
    public ?string $jobIdStr = null;
}
