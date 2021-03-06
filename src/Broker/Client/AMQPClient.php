<?php declare(strict_types=1);

namespace FTCBotCore\Broker\Client;

use FTCBotCore\Broker\BrokerClient;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use FTCBotCore\Message\Message;
use FTCBotCore\Message\MessageFactory;

class AMQPClient implements BrokerClient
{
    
    /**
     * @var AMQPChannel
     */
    private $channel;
    
    /**
     * @var AMQPStreamConnection
     */
    private $connection;
    
    /**
     * @var \Closure
     */
    private $callback;
    
    /**
     * @var MessageFactory 
     */
    private $messageFactory;
    
    public function __construct(
        AMQPStreamConnection $connection,
        MessageFactory $messageFactory
     ) {
        $this->connection = $connection;
        $this->messageFactory = $messageFactory;
        $this->openChannel();
    }
    
    private function openChannel()
    {
        $this->channel = $this->connection->channel();
        $this->channel->basic_qos(null, 1, null);
        $this->channel->queue_declare('hello', false, true, false, false);
    }
    
    private function setCallback(\Closure $callback) : void
    {
        $this->callback = function(AMQPMessage $amqpMsg) use ($callback) {
            $message = $this->instantiateMessage($amqpMsg);
            
            if (!$message) {
                $this->ack($amqpMsg);
                return;
            }
            
            if($callback($message)) {
                $this->ack($amqpMsg);
            }
        };
    }
    
    public function reconnect()
    {
        $this->connection->reconnect();
        $this->openChannel();
    }
    
    public function consume(\Closure $callback)
    {
        $this->setCallback($callback);
        $this->channel->basic_consume('hello', '', false, false, false, false, $this->callback);
        
        while ($this->callback) {
            $this->channel->wait();
        }
        
        $this->closeConnection();
    }
    
    public function ack(AMQPMessage $message) : void
    {
        $messageTag = $message->delivery_info['delivery_tag'];
        $message->delivery_info['channel']->basic_ack($messageTag);
    }
    
    public function getChannel()
    {
        return $this->channel;
    }
    
    public function closeChannel()
    {
        $this->channel->close();
    }
    
    public function closeConnection()
    {
        $this->closeChannel();
        $this->connection->close();
    }
    
    private function instantiateMessage(AMQPMessage $amqpMessage) : ?Message
    {
        $amqpBody = json_decode($amqpMessage->body, true);
        $arr = [
            null,
            'RESUMED',
            'READY',
        ];
        if (!in_array($amqpBody['event'], $arr)) {
            $message = ($this->messageFactory)($amqpBody);
            return $message;
        }
        
        return null;
    }
    
}
