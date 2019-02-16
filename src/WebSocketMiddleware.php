<?php

namespace Voryx\WebSocketMiddleware;

use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Handshake\NegotiatorInterface;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use React\Http\Response;
use React\Stream\CompositeStream;
use React\Stream\ThroughStream;

final class WebSocketMiddleware
{
    private $paths;
    private $connectionHandler;
    private $subProtocols;
    private $negotiator;

    public function __construct(array $paths = [], callable $connectionHandler = null, array $subProtocols = [], NegotiatorInterface $negotiator = null)
    {
        $this->paths             = $paths;
        $this->connectionHandler = $connectionHandler ?: function () {};
        $this->subProtocols      = $subProtocols;

        if ($negotiator === null) {
            $negotiator = new ServerNegotiator(new RequestVerifier());
            $negotiator->setSupportedSubProtocols($this->subProtocols);
            $negotiator->setStrictSubProtocolCheck(true);
        }

        $this->negotiator = $negotiator;
    }

    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        // check path at some point - for now we just go go ws
        if (count($this->paths) > 0) {
            if (!in_array($request->getUri()->getPath(), $this->paths)) {
                return $next($request);
            }
        }

        $response = $this->negotiator->handshake($request);

        if ($response->getStatusCode() != '101') {
            // TODO: this should return an error or something not continue the chain
            return $next($request);
        }

        $inStream  = new ThroughStream();
        $outStream = new ThroughStream();

        $response = new Response(
            $response->getStatusCode(),
            $response->getHeaders(),
            new CompositeStream(
                $outStream,
                $inStream
            )
        );

        $conn = new WebSocketConnection(new CompositeStream($inStream, $outStream));

        call_user_func($this->connectionHandler, $conn, $request, $response);

        return $response;
    }
}
