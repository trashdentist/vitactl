<?php
namespace App\Presenters;
use Nette;
use Nette\Application\UI\Form;
use App\Forms\FormFactory;

final class HomepagePresenter extends BasePresenter
{
	function renderDefault()
	{
		$this->template->loggedin = $this->getUser()->isLoggedIn();
		if($this->template->loggedin)
		{
			$this->template->login = $this->getUser()->getIdentity()->getData()["username"];
			$this->template->balance = $this->getUser()->getIdentity()->getData()["balance"];
		}
	}
}
