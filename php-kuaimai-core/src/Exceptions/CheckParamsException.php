<?php

namespace Kuaimai\Exceptions;

use RuntimeException;

class CheckParamsException extends RuntimeException
{
    private int $errorCode;
    private ?string $subMsg;

    public function __construct(string $message, int $errorCode = 200, ?string $subMsg = null)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->subMsg    = $subMsg;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getSubMsg(): ?string
    {
        return $this->subMsg;
    }
}
