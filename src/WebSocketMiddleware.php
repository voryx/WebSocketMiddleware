<?php

namespace Voryx\WebSocketMiddleware;

use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use React\Http\Message\Response;
use React\Stream\CompositeStream;
use React\Stream\ThroughStream;

final class WebSocketMiddleware
{
    private $paths;
    private $connectionHandler = null;
    private $subProtocols;

    public function __construct(array $paths = [], callable $connectionHandler = null, array $subProtocols = [])
    {
        $this->paths             = $paths;
        $this->connectionHandler = $connectionHandler ?: function () {};
        $this->subProtocols      = $subProtocols;
    }

    public function __invoke(ServerRequestInterface $request, callable $next = null)
    {
        // check path at some point - for now we just go go ws
        if (count($this->paths) > 0) {
            if (!in_array($request->getUri()->getPath(), $this->paths)) {
                if ($next === null) {
                    return new Response(404);
                }

                return $next($request);
            }
        }

        $negotiator = new ServerNegotiator(new RequestVerifier());
        $negotiator->setSupportedSubProtocols($this->subProtocols);
        $negotiator->setStrictSubProtocolCheck(true);

        $response = $negotiator->handshake($request);

        if ($response->getStatusCode() != '101') {
            if ($next === null) {
                return new Response(404);
            }

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
