<?php
namespace App\Events;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class EventManager
{
    protected $queue;
    protected $exchange;
    protected $tag;
    
    private $conn;
    private $chan;
    private $listener = NULL;
    
    function __construct(string $addr, int $port, string $username, string $password, string $queue, string $tag)
    {
        $this->tag      = $tag;
        $this->exchange = "pool";
        
        $this->conn = new AMQPStreamConnection($addr, $port, $username, $password);
        $this->chan = $this->conn->channel();
        $this->chan->exchange_declare($this->exchange, "fanout", false, false, false);
    }
    
    function broadcast($event): bool
    {
        $message = new AMQPMessage(json_encode($event));
        
        try {
            $this->chan->basic_publish($message, $this->exchange);
        } catch(\Exception $ex) {
            return false;
        }
        
        return true;
    }
}