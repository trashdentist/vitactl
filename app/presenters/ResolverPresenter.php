<?php
namespace App\Presenters;
use Nette;

final class ResolverPresenter extends RestrictedPresenter
{
    function renderDomain($domain)
    {
        if(isset($_GET["domain"])) {
            $ip4 = @file_get_contents("https://dns-api.org/A/$_GET[domain]");
            if(!$ip4) $ip4 = '[]';
            $ip6 = @file_get_contents("https://dns-api.org/AAAA/$_GET[domain]");
            if(!$ip6) $ip6 = '[]';
            
            $this->template->results = [
                json_decode($ip4),
                json_decode($ip6)
            ];
        }
    }
}
