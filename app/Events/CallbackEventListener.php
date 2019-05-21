<?php
namespace App\Events;
use PhpAmqpLib\Message\AMQPMessage;

final class CallbackEventListener implements IEventListener
{
    private $callback;
    
    function __construct(\Closure $callback)
    {
        $this->callback = $callback;
    }
    
    function onMessage($message): void
    {
        $cb = $this->callback;
        $cb($message);
    }
}