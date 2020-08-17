# WebSocketMiddleware
WebSocket Middleware for react/http
# Try it out
Run `chat_ws_server.php` from the examples directory and navigate a few browser windows to http://127.0.0.1:4321/ (only tested briefly in Chrome)
# Simple Usage
A simple echo server:
```php
use Ratchet\RFC6455\Messaging\Message;
use React\EventLoop\Factory;
use React\Http\Server;
use Voryx\WebSocketMiddleware\WebSocketConnection;
use Voryx\WebSocketMiddleware\WebSocketMiddleware;

require __DIR__ . '/../../vendor/autoload.php';

$loop = Factory::create();

$ws = new WebSocketMiddleware([], function (WebSocketConnection $conn) {
    $conn->on('message', function (Message $message) use ($conn) {
        $conn->send($message);
    });
});

$server = new Server($loop, $ws);

$server->listen(new \React\Socket\Server('127.0.0.1:4321', $loop));

$loop->run();
```
# Options
By default `WebSocketMiddleware` uses the `ratchet/rfc6455` default max sizes for messages and frames and also disables compression.
These settings can be overridden with the `WebSocketOptions` object.
```php
$ws = new WebSocketMiddleware(
    [],
    $connectionHandler,
    [],
    WebSocketOptions::getDefault()
        ->withMaxFramePayloadSize(2048)
        ->withMaxMessagePayloadSize(4096)
        ->withPermessageDeflate());
``` 
