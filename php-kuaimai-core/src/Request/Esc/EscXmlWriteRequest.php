<?php

namespace Kuaimai\Request\Esc;

class EscXmlWriteRequest
{
    public ?string $sn           = null;
    /** XML instruction string, e.g. "<page><render><t size='01'>hello</t></render></page>" */
    public ?string $instructions = null;
    public int     $volume       = 0;
    /** Optional cutter flag. Service expects Integer: 1=cut, 0=no cut. */
    public ?int    $cut          = null;
}
