<?php

namespace Kuaimai\Request\Device;

class QueryDeviceStatusRequest
{
    public ?string $sn  = null;
    /** JSON array string of SNs, e.g. '["SN1","SN2"]' */
    public ?string $sns = null;
}
