<?php

namespace Kuaimai\Request\Device;

class AdjustDeviceDensityRequest
{
    public ?string $sn      = null;
    /** Density range 1-15, default 8 */
    public int     $density = 8;
}
