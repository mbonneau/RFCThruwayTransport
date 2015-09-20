<?php


namespace RFCThruwayTransport;


use Ratchet\RFC6455\Messaging\Protocol\FrameInterface;
use Ratchet\RFC6455\Messaging\Protocol\MessageInterface;
use Ratchet\RFC6455\Messaging\Streaming\ContextInterface;
use Ratchet\WebSocket\Version\RFC6455\Frame;
use React\Http\Response;
use Thruway\Exception\DeserializationException;
use Thruway\Logging\Logger;
use Thruway\Message\HelloMessage;
use Thruway\Message\Message;
use Thruway\Transport\AbstractTransport;

class RFC6455Transport extends AbstractTransport implements ContextInterface
{
    private $_frame;
    private $_message;

    /**
     * @var \React\Http\Response
     */
    private $_conn;

    /**
     * @var callable
     */
    private $sender;

    /**
     * @return mixed
     */
    public function getTransportDetails()
    {
        return new \stdClass();
    }

    /**
     * @param \Thruway\Message\Message $msg
     */
    public function sendMessage(Message $msg)
    {
        $frame = new Frame($this->getSerializer()->serialize($msg), true, Frame::OP_TEXT);
        $this->_conn->write($frame->getContents());
    }

    public function __construct(Response $connectionContext) {
        $this->_conn = $connectionContext;
    }

    public function setFrame(FrameInterface $frame = null) {
        $this->_frame = $frame;

        return $frame;
    }

    public function getFrame() {
        return $this->_frame;
    }

    public function setMessage(MessageInterface $message = null) {
        $this->_message = $message;

        return $message;
    }

    public function getMessage() {
        return $this->_message;
    }

    public function onMessage(MessageInterface $msg) {
        try {
            $msg = $this->getSerializer()->deserialize($msg->getPayload());

            if ($msg instanceof HelloMessage) {

                $details = $msg->getDetails();

                $details->transport = (object) $this->getTransportDetails();

                $msg->setDetails($details);
            }

            $sender = $this->sender;
            if (is_callable($sender) !== null) {
                $sender($msg);
            }
        } catch (DeserializationException $e) {
            Logger::alert($this, "Deserialization exception occurred.");
        } catch (\Exception $e) {
            Logger::alert($this, "Exception occurred during onMessage: ".$e->getMessage());
        }
    }

    public function onPing(\Ratchet\RFC6455\Messaging\Protocol\FrameInterface $frame) {
        $pong = new Frame($frame->getPayload(), true, Frame::OP_PONG);
        $this->_conn->write($pong->getContents());
    }

    public function onPong(\Ratchet\RFC6455\Messaging\Protocol\FrameInterface $msg) {
        // TODO: Implement onPong() method.
    }

    public function onClose($code = 1000) {
        $frame = new Frame(
            pack('n', $code),
            true,
            Frame::OP_CLOSE
        );

        $this->_conn->end($frame->getContents());
    }

    /**
     * @param callable $sender
     */
    public function setSender($sender)
    {
        $this->sender = $sender;
    }
}