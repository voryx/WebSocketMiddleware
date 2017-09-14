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
                        $closeCode = 1000;
                        if ($frame->getPayloadLength() >= 2) {
                            list($closeCode) = array_merge(unpack('n*', substr($frame->getPayload(), 0, 2)));
                        }

                        if ($closeCode >= 2000) {
                            // emit close code as error
                            $exception = new \Exception('WebSocket closed with code ' . $closeCode);
                            $this->emit('error', [$exception, $this]);
                            return;
                        }

                        $this->emit('close', [$closeCode, $this]);

                        $this->stream->close();
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

    public function close()
    {
        $this->stream->end((new Frame(pack('n', 1000), true, Frame::OP_CLOSE))->getContents());
    }
}
