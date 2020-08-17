<?php

namespace Voryx\WebSocketMiddleware;

use Ratchet\RFC6455\Handshake\PermessageDeflateOptions;

class WebSocketOptions
{
    private $permessageDeflateEnabled = false;
    private $maxMessagePayloadSize    = null;
    private $maxFramePayloadSize      = null;

    private function __construct()
    {

    }

    public static function getDefault()
    {
        return new self();
    }

    public function withPermessageDeflate()
    {
        $c                           = clone $this;
        $c->permessageDeflateEnabled = true;

        return $c;
    }

    public function withoutPermessageDeflate()
    {
        $c                           = clone $this;
        $c->permessageDeflateEnabled = false;

        return $c;
    }

    public function withMaxMessagePayloadSize($maxSize)
    {
        $c                        = clone $this;
        $c->maxMessagePayloadSize = $maxSize;

        return $c;
    }

    public function withMaxFramePayloadSize($maxSize)
    {
        $c                      = clone $this;
        $c->maxFramePayloadSize = $maxSize;

        return $c;
    }

    public function isPermessageDeflateEnabled()
    {
        return $this->permessageDeflateEnabled;
    }

    public function getMaxMessagePayloadSize()
    {
        return $this->maxMessagePayloadSize;
    }

    public function getMaxFramePayloadSize()
    {
        return $this->maxFramePayloadSize;
    }
}