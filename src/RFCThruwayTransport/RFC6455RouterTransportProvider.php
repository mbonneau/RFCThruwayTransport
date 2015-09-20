<?php


namespace RFCThruwayTransport;


use Ratchet\RFC6455\Encoding\Validator;
use Ratchet\RFC6455\Handshake\NegotiatorInterface;
use Ratchet\RFC6455\Messaging\Streaming\MessageStreamer;
use React\Http\Request;
use React\Http\Response;
use React\Socket\Server;
use Thruway\Event\ConnectionOpenEvent;
use Thruway\Event\RouterStartEvent;
use Thruway\Event\RouterStopEvent;
use Thruway\Message\Message;
use Thruway\Serializer\JsonSerializer;
use Thruway\Transport\AbstractRouterTransportProvider;

class RFC6455RouterTransportProvider extends AbstractRouterTransportProvider
{
    private $bindAddress;
    private $port;

    /** @var Server */
    private $socketServer;

    /** @var \React\Http\Server */
    private $httpServer;

    /** @var Validator */
    private $encodingValidator;

    /** @var NegotiatorInterface */
    private $negotiator;

    /** @var MessageStreamer */
    private $messageStreamer;

    /** @var \SplObjectStorage */
    //private $sessions;

    /**
     * RFC6455RouterTransportProvider constructor.
     * @param string $bindAddress
     * @param int $port
     */
    public function __construct($bindAddress = "127.0.0.1", $port = 9090)
    {
        $this->bindAddress = $bindAddress;
        $this->port        = $port;
        //$this->sessions    = new \SplObjectStorage();

        $this->encodingValidator = new \Ratchet\RFC6455\Encoding\Validator();
        $this->negotiator        = new \Ratchet\RFC6455\Handshake\Negotiator($this->encodingValidator);
        $this->negotiator->setSupportedSubProtocols(["wamp.2.json"]);

        $this->messageStreamer   = new \Ratchet\RFC6455\Messaging\Streaming\MessageStreamer($this->encodingValidator);
    }

    public function onHttpRequest(Request $request, Response $response)
    {
        $psrRequest = new \GuzzleHttp\Psr7\Request($request->getMethod(), $request->getPath(), $request->getHeaders());

        $negotiatorResponse = $this->negotiator->handshake($psrRequest);

        $response->writeHead(
            $negotiatorResponse->getStatusCode(),
            array_merge(
                $negotiatorResponse->getHeaders(),
                ["Content-Length" => "0"]
            )
        );

        if ($negotiatorResponse->getStatusCode() !== 101) {
            $response->end();
            return;
        }

        $transport = new RFC6455Transport($response);
        $transport->setSerializer(new JsonSerializer());

        $session = $this->router->createNewSession($transport);

        $transport->setSender(function (Message $message) use ($session) {
            $session->dispatchMessage($message);
        });

        //$this->sessions->attach($conn, $session);

        $request->on('data', function ($data) use ($transport) {
            $this->messageStreamer->onData($data, $transport);
        });

        $this->router->getEventDispatcher()->dispatch("connection_open", new ConnectionOpenEvent($session));
    }

    public function handleRouterStart(RouterStartEvent $event)
    {
        $this->socketServer = new Server($this->getLoop());
        $this->httpServer   = new \React\Http\Server($this->socketServer);

        $this->httpServer->on('request', [$this, 'onHttpRequest']);
        $this->socketServer->listen($this->port, $this->bindAddress);
    }

    public function handleRouterStop(RouterStopEvent $event)
    {
        if ($this->httpServer) {
            $this->socketServer->shutdown();
        }

//        foreach ($this->sessions as $k) {
//            $this->sessions[$k]->shutdown();
//        }
    }

    public static function getSubscribedEvents()
    {
        return [
            "router.start" => ["handleRouterStart", 10],
            "router.stop"  => ["handleRouterStop", 10]
        ];
    }
}