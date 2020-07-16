<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Messaging\Message;
use React\EventLoop\Factory;
use React\Http\Message\Response;
use React\Http\Server;
use React\Stream\ThroughStream;
use Voryx\WebSocketMiddleware\WebSocketConnection;
use Voryx\WebSocketMiddleware\WebSocketMiddleware;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

$frontend = file_get_contents(__DIR__ . '/test.html');
$uri      = '127.0.0.1:4321';

$broadcast = new ThroughStream();

$ws = new WebSocketMiddleware(['/ws'], function (WebSocketConnection $conn, ServerRequestInterface $request, ResponseInterface $response) use ($broadcast, $loop) {
    static $user = 0;

    // do not send on the connection before the react http server has a chance to start listening
    // on the streams
    $loop->addTimer(0, function () use ($conn, $user, $broadcast) {
        $broadcast->write('user ' . $user . ' connected');
        $conn->send('Welcome. You are user ' . $user);
    });

    $broadcastHandler = function ($data) use ($conn) {
        $conn->send($data);
    };

    $broadcast->on('data', $broadcastHandler);

    $conn->on('message', function (Message $message) use ($broadcast, $conn, $user) {
        $broadcast->write('user ' . $user . ': ' . $message->getPayload());
    });

    $conn->on('error', function (Throwable $e) use ($broadcast, $user, $broadcastHandler) {
        $broadcast->removeListener('data', $broadcastHandler);
        $broadcast->write('user ' . $user . ' left because of error: ' . $e->getMessage());
    });

    $conn->on('close', function () use ($broadcast, $user, $broadcastHandler) {
        $broadcast->removeListener('data', $broadcastHandler);
        $broadcast->write('user ' . $user . ' closed their connection');
    });

    $user++;
});

$server = new Server($loop,
    function (ServerRequestInterface $request, callable $next) use ($broadcast) {
        // lets let the people chatting see what requests are happening too.
        $broadcast->write('<i>Request: ' . $request->getUri()->getPath() . '</i>');
        return $next($request);
    },
    $ws,
    function (ServerRequestInterface $request, callable $next) {
        $request = $request->withHeader('Request-Time', time());
        return $next($request);
    },
    function (ServerRequestInterface $request) use ($frontend) {
        return new Response(200, [], $frontend);
    },
);

$server->listen(new \React\Socket\Server('127.0.0.1:4321', $loop));

openWebPage($loop, 'http://' . $uri);

$loop->run();

function openWebPage($loop, $url)
{
    $os = strtolower(php_uname(PHP_OS));

    if (strpos($os, 'darwin') !== false) {
        $open = 'open';
    } elseif (strpos($os, 'linux') !== false) {
        $open = 'xdg-open';
    } else {
        echo "Can't open your browser, you'll have to manually navigate to {$url}", PHP_EOL;
        return;
    }

    $process = new React\ChildProcess\Process("{$open} {$url}");

    try {
        $process->start($loop);
    } catch (Exception $e) {
        echo $e->getMessage(), PHP_EOL;
    }
}
