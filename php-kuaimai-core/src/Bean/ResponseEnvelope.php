<?php

namespace Kuaimai\Bean;

class ResponseEnvelope
{
    public ?bool   $status  = null;
    public mixed   $data    = null;
    public ?string $message = null;
    public ?int    $code    = null;

    public static function fromArray(array $arr): self
    {
        $r          = new self();
        $r->status  = isset($arr['status'])  ? (bool)$arr['status']  : null;
        $r->data    = $arr['data']   ?? null;
        $r->message = $arr['message'] ?? null;
        $r->code    = isset($arr['code']) ? (int)$arr['code'] : null;
        return $r;
    }

    public static function error(string $message): self
    {
        $r          = new self();
        $r->status  = false;
        $r->message = $message;
        return $r;
    }

    public function toArray(): array
    {
        return [
            'status'  => $this->status,
            'data'    => $this->data,
            'message' => $this->message,
            'code'    => $this->code,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }
}
