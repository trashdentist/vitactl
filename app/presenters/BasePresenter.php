<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use App\Forms\FormFactory;


/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{

    protected $config;
    protected $ff;
    protected $db;

    function __construct(FormFactory $ff, Nette\Database\Context $database)
    {
        $this->ff     = $ff;
        $this->db     = $database;
        $this->config = (object) parse_ini_file(__DIR__."/../../Vitaline.ini", true, INI_SCANNER_TYPED);
    }

    protected function startup()
    {
        parent::startup();
        $sknn = json_decode(file_get_contents(__DIR__."/../../SkinNames.js"), true); #SKiN Names
        $skin = $this->getHttpRequest()->getQuery("set_theme");
        if(!array_key_exists($skin, $sknn)) $skin = null;
        if(!is_null($skin)) $this->getHttpResponse()->setCookie("remixstyle", $skin, "2 years");
        
        $this->template->async = !is_null($this->getHttpRequest()->getQuery("_pjax"));
        $this->template->user  = $this->getUser();
        $this->template->conf  = $this->config;
        $this->template->sknn  = $sknn;
        $this->template->skin  = (object) [
            "current" => $skin ?? $this->getHttpRequest()->getCookie('remixstyle') ?? "minty",
            "list"    => json_decode(@file_get_contents('http://bootswatch.com/api/4.json')),
        ];
    }
}
