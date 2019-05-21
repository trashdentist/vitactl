<?php
namespace App\Events;

interface IEventListener
{
    public function onMessage($message): void;
}