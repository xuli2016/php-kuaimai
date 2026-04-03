<?php

namespace Kuaimai\Request\Misc;

class CainiaoPrintRequest
{
    public ?string $imei           = null;
    /** Base64 image data (with or without data URI prefix) */
    public ?string $imageBase64Data = null;
}
