<?php

namespace Voryx\WebSocketMiddleware;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Ratchet\RFC6455\Handshake\PermessageDeflateOptions;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\Message;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\Stream\DuplexStreamInterface;

class WebSocketConnection implements EventEmitterInterface
{
    use EventEmitterTrait;

    private $stream;

    /** @var WebSocketOptions */
    private $webSocketOptions;

    /** @var PermessageDeflateOptions */
    private $permessageDeflateOptions;

    private $messageBuffer;

    public function __construct(DuplexStreamInterface $stream, WebSocketOptions $webSocketOptions, PermessageDeflateOptions $permessageDeflateOptions)
    {
        $this->stream                   = $stream;
        $this->webSocketOptions         = $webSocketOptions;
        $this->permessageDeflateOptions = $permessageDeflateOptions;

        $mb = new MessageBuffer(
            new CloseFrameChecker(),
            function (Message $message) {
                $this->emit('message', [$message, $this]);
            },
            function (Frame $frame) {
                switch ($frame->getOpcode()) {
                    case Frame::OP_PING:
                        $this->stream->write((new Frame($frame->getPayload(), true, Frame::OP_PONG))->getContents());
                        return;
                    case Frame::OP_CLOSE:
                        $closeCode = unpack('n*', substr($frame->getPayload(), 0, 2));
                        $closeCode = reset($closeCode) ?: 1000;
                        $reason = '';

                        if ($frame->getPayloadLength() > 2) {
                            $reason = substr($frame->getPayload(), 2);
                        }

                        $this->stream->end($frame->getContents());

                        $this->emit('close', [$closeCode, $this, $reason]);
                        return;
                }
            },
            true,
            null,
            $this->webSocketOptions->getMaxMessagePayloadSize(),
            $this->webSocketOptions->getMaxFramePayloadSize(),
            [$this->stream, 'write'],
            $this->permessageDeflateOptions
        );

        $this->messageBuffer = $mb;

        $stream->on('data', [$mb, 'onData']);
        $stream->on('close', function () {
            $this->emit('close', [1006, $this, '']);
        });
    }

    public function send($data)
    {
        if ($data instanceof Frame) {
            $this->messageBuffer->sendFrame($data);
            return;
        }

        if ($data instanceof MessageInterface) {
            $this->messageBuffer->sendMessage($data->getPayload(), true, $data->isBinary());
            return;
        }

        $this->messageBuffer->sendMessage($data);
    }

    public function close($code = 1000, $reason = '')
    {
        $this->stream->end((new Frame(pack('n', $code) . $reason, true, Frame::OP_CLOSE))->getContents());
    }
}
