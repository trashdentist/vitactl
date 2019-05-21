<?php declare(strict_types=1);
namespace App\Presenters;
use Nette\Application\ForbiddenRequestException;
use Nette;

abstract class RestrictedPresenter extends BasePresenter
{
    const USER_REGISTERED      = "us";
    const SYSTEM_ADMINISTRATOR = "sa";

    protected $minimumRole    = RestrictedPresenter::USER_REGISTERED;
    protected $forbidIfBanned = true;
    protected $rateLimit      = true;

    protected function rateLimit(): void
    {
        $rl = $this->getSession("rateLimit");
        if(!isset($rl->tip)) $rl->tip = 0;
        if(!isset($rl->ctr)) $rl->ctr = 0;
        
        if($rl->tip + 60 <= time()) {
            $rl->tip = time();
            $rl->ctr = 0;
        } else if($rl->ctr >= $this->config->Security['rpm']) {
            header("HTTP/1.1 429 Too Many Requests");
            header("Content-Type: image/jpeg");
            exit(readfile(__DIR__."/templates/e429.jpeg"));
        } else {
            $this->rateLimit ? $rl->ctr++ : null;
        }
    }

    protected function startup()
    {
        parent::startup();
        $user = $this->getUser();
        if($user->isLoggedIn()) {
            $ban = (bool) $user->getIdentity()->getData()["banned"];
            if($ban && $this->forbidIfBanned) throw new ForbiddenRequestException;
            if($this->minimumRole == "sa" && !$user->isInRole("sa")) throw new ForbiddenRequestException;
            $this->template->balance = $this->getBalance();
        } else {
            $this->flashMessage("You need to be logged in to access this section.", "warning");
            $this->redirect("Login:login");
        }
        
        $this->rateLimit();
    }
    
    use TBalance;
}
