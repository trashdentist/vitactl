<?php
namespace App\Presenters;
use Nette;

final class DstatPresenter extends RestrictedPresenter
{
    protected $rateLimit = false;
    
    private function parseFile(string $output): int
    {
        $matches = [];
        if(!preg_match_all("%Active connections: ([0-9]++).*%", $output, $matches)) return 0;

        return (int) $matches[1][0] ?? 0;
    }
    
    private function requestStats(string $server): string
    {
        $result = file_get_contents("https://dstat.cc/api/L7request.php?id=$server");
        if(!$result) return "Active connections: 0 aa a a a a a";

        return $result;
    }
    
    function renderStatistics()
    {
        echo($this->parseFile($this->requestStats($_GET["s"] ?? "Hetzner")));
        die;
    }
}
