<?php
namespace App\Presenters;
use Nette;
use App\Forms\FormFactory;
use App\Events\{EventManager, CallbackEventListener};

final class PoolPresenter extends RestrictedPresenter 
{
    private $userID = 0;
    function startup()
    {
        parent::startup();
        $this->userID = $this->getUser()->getId();
    }
    
    function renderDefault()
    {
        ini_set('max_execution_time', 5);
        exit(file_get_contents($this->config->Events["server"].$this->userID));
    }
}