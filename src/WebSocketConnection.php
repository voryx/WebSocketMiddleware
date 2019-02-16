<?php

namespace Voryx\WebSocketMiddleware;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
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

    public function __construct(DuplexStreamInterface $stream)
    {
        $this->stream = $stream;

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

                        $this->emit('close', [$closeCode, $reason, $this]);

                        return;
                }
            },
            true
        );

        $stream->on('data', [$mb, 'onData']);
    }

    public function send($data)
    {
        if (!($data instanceof MessageInterface)) {
            $data = new Frame($data, true, Frame::OP_TEXT);
        }

        $this->stream->write($data->getContents());
    }

    public function close($code = 1000, $reason = '')
    {
        $this->stream->end((new Frame(pack('n', $code) . $reason, true, Frame::OP_CLOSE))->getContents());
    }
}
