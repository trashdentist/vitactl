<?php
namespace App\Presenters;
use Nette;
use Nette\Application\UI\Form;
use Nette\Security\AuthenticationException;
use Nette\Application\ForbiddenRequestException;
use App\Forms\FormFactory;
use App\Models\NewsManager;

final class AdminPanelPresenter extends RestrictedPresenter
{
    protected $minimumRole = RestrictedPresenter::SYSTEM_ADMINISTRATOR; //тело класса здесь
    private   $userId      = null;
    private   $newsId;
       private   $news;
    private   $serversId;
    private   $server_info;
    
    function __construct(FormFactory $ff, Nette\Database\Context $database, NewsManager $manager)
    {
        parent::__construct($ff, $database);
        $this->news = $manager;
    }

     /* ******************************************************
                            * Requests *
    ****************************************************** */

    private function createComponentNewsForm(): Form
    {
        $form = $this->ff->create();
        $form->addText('title', 'Название: ')->setRequired();
        $form->addText('content', 'Описание: ')->setRequired();
        $form->addHidden("newsId", $this->newsId);
        $form->addSubmit('change', 'Изменить')->setOmitted(false);
        $form->addSubmit('delete', 'Удалить')->setOmitted(false);
        $form->addSubmit('return', 'Восстановить')->setOmitted(false);
        $form->onSuccess[] = [$this, 'onNewsFormSucceeded'];
        FormFactory::bootstrapify($form);
        return $form;
    }

    private function editServerById(int $user, $label, $addr, $creds, int $slots, int $methods, int $active): void
    {
        $this->db->table("machines")
                 ->where("id", $user)
                 ->update([
                     "label" => $label,
                     "addr" => $addr,
                     "creds" => $creds,
                     "slots" => $slots,
                     "methods" => $methods,
                     "active" => $active,
                 ]);
    }

    private function unAdminById(int $user): void
    {
        $this->db->table("users")
                 ->where("id", $user)
                 ->update([
                     "role" => "us",
                 ]);
    }

    private function getUsers()
    {
        $selection = $this->db->table("users")->select("*")->where("banned", 0);
        
        foreach($selection as $users) {
            
            yield $users->id => $users->login;
        }
    }
    private function getPlans()
    {
        $selection = $this->db->table("plans")->select("*");
        
        foreach($selection as $plan) {
            
            yield $plan->id => $plan->label;
        }
    }

    private function addUserById(int $user, int $plan): void
    {
        $this->db->table("memberships")
                 ->where("id", $user)
                 ->insert([
                     "plan" => $plan,
                     "user" => $user,
                     "assigned" => time("H:i:s"),
                     "duration" => "2592000",
                 ]);
    }
    private function changeUserById(int $user, int $plan): void
    {
        $this->db->table("memberships")
                 ->where("id", $user)
                 ->update([
                     "plan" => $plan,
                     "user" => $user,
                     "assigned" => time("H:i:s"),
                     "duration" => "2592000",
                 ]);
    }

    private function deleteUserById(int $user): void
    {
        $this->db->table("memberships")
                 ->where("user", $user)
                 ->delete();
    }

    private function addPromo($code, int $plan, int $duration, $max): void
    {
        $this->db->table("promos")
                 ->insert([
                     "code" => $code,
                     "plan" => $plan,
                     "duration" => $duration,
                     "max" => $max,
                 ]);
    }

    private function editNewsById(int $nid, $title, $content, int $deleted): void
    {
        $this->db->table("news")
        		 ->where("id", $nid)
                 ->update([
                     "title" => $title,
                     "content" => $content,
                     "deleted" => $deleted,
                 ]);
    }
     /* ******************************************************
                            * Renders *
    ****************************************************** */

    function renderDefault() //не вызоветься из-за энкапсуляции (private)
    {
        $user = $this->getUser();
        if($user->isLoggedIn()) //не нужно, RestrictedPresenter сам кикнет не авторизованных
        {

        }
    }

    function renderAdminlist(?int $id = null) //не вызоветься из-за энкапсуляции (private)
    {
        if(is_null($id)) {
             $this->template->admin      = $this->db->table("users")->select("*")->where("role", "sa");     
        }else{  
            $this->template->admin1      = $this->db->table("users")->select("*")->where("role", "sa")->get($id);
            if($this->template->admin1){
            $this->unAdminById($id);
            $this->flashMessage("Administrator role has been removed.", "success");
            $this->redirect('AdminPanel:adminlist');
            }else{
            $this->flashMessage("User does not have administrator rights.", "error");
            $this->redirect('AdminPanel:adminlist');
            }
        }
    }
    function renderUsers(?int $id = null)
    {
        if(is_null($id)) {
             $this->template->users1      = $this->db->table("memberships")->select("*");     
        }else{
            $this->deleteUserById($id);
            $this->flashMessage("The tariff has been removed.", "success");
            $this->redirect('AdminPanel:users');
        }
    }

    function renderNews(?int $id = null)
    {
       if(is_null($id)) {
            $this->template->news      = $this->db->table("news")->select("*");   
        }else{
            $this->template->news_inf  = $this->db->table("news")->select("*")->get($id);
            $this->newsId = $id;
            if($this->template->news_inf){
            $this->template->setFile(__DIR__."/templates/AdminPanel/newsinf.latte");
            }else{
                $this->error("Новость не найдена.");
            }
        } 
    }

    function renderPromo(?int $id = null)
    {
        if(is_null($id)) {
            $this->template->invites = $this->db->table("promos")->select("*");
        } else {
            $invite = $this->db->table("promos")->select("*")->get($id);
            if(!$invite) return $this->error("Инвайт-код не найден.");
            $this->inviteId = $id;
            $this->template->invite = $invite;
            $this->template->setFile(__DIR__."/templates/AdminPanel/inviteinf.latte");
        }
    }

    function renderServers(?int $id = null)
    {
       if(is_null($id)) {
            $this->template->servers      = $this->db->table("machines")->select("*");   
        }else{
            $this->template->servers_inf  = $this->db->table("machines")->select("*")->get($id);
            $this->server_info = (object) [];
            $this->server_info  = $this->db->table("machines")->select("*")->get($id);
            $this->serversId = $id;
            if($this->template->servers_inf){
            $this->template->setFile(__DIR__."/templates/AdminPanel/serversinf.latte");
            }else{
                $this->error("Сервер не найден.");
            }
        } 
    }

    /* ******************************************************
                            * Forms *
    ****************************************************** */
    
    function createComponentPromoaddForm(): Form
    {
        $form = $this->ff->create();
        $form->addText('code', 'Promo: ')->setRequired();
        $form->addSelect('plan', 'Plan: ', iterator_to_array($this->getPlans()))
             ->setPrompt('Choose plan')
             ->setRequired();
        $form->addInteger('duration', 'Duration: ')->setRequired();
        $form->addInteger('maxused', 'Max. used: ')->setRequired();
        $form->addSubmit('create', 'Create')->setOmitted(false);
        $form->onSuccess[] = [$this, 'onPromoaddFormSucceeded'];
        FormFactory::bootstrapify($form);
        return $form;
    }

    function createComponentAddnewsForm(): Form
    {
        $form = $this->ff->create();
        $form->addText('title', 'Название: ')->setRequired();
        $form->addText('content', 'Описание: ')->setRequired();
        $form->addHidden("newsId", $this->newsId);
        $form->addSubmit('change', 'Добавить')->setOmitted(false);
        $form->onSuccess[] = [$this, 'onAddnewsFormSucceeded'];
        FormFactory::bootstrapify($form);
        return $form;
    }

    function createComponentEditNewsForm(): Form
    {
    	$this->news = (object) [];
        $this->news  = $this->db->table("news")->select("*")->get($this->newsId);
        $form = $this->ff->create();
        $form->addText('title', 'Title: ')->setRequired()->setValue($this->news['title']);
        $form->addText('content', 'Content: ')->setRequired()->setValue($this->news['content']);
        $status = [
			    '0' => 'Оставить',
			    '1' => 'Удалить',
			];
		$form->addHidden("newsId", $this->newsId);
		$form->addSelect('status', 'Status:', $status)->setPrompt('Pick a stats')->setValue($this->news['deleted']);
        $form->addSubmit('change', 'Change')->setOmitted(false);
        $form->onSuccess[] = [$this, 'onEditNewsFormSucceeded'];
        FormFactory::bootstrapify($form);
        return $form;
    }

    function createComponentEditServersForm(): Form
    {
        $form = $this->ff->create();
        $form->addText('label', 'Название: ')->setRequired();
        $form->addText('addr', 'Адрес: ')->setRequired();
        $form->addText('creds', 'Ключ: ')->setRequired();
        $form->addInteger('slots', 'Слоты: ')->setRequired();
        $form->addText('methods', 'Методы: ')->setRequired();
        $form->addText('active', 'Работа: ')->setRequired();
        $form->addHidden("serversId", $this->serversId);
        $form->addSubmit('change', 'Добавить')->setOmitted(false);
        $form->onSuccess[] = [$this, 'onEditServersFormSucceeded'];
        FormFactory::bootstrapify($form);
        return $form;
    }

    function createComponentUseraddForm(): Form
    {
        $form = $this->ff->create();
        $form->addSelect('user', 'User: ', iterator_to_array($this->getUsers()))
             ->setPrompt('Choose user')
             ->setRequired();
        $form->addSelect('plan', 'Plan: ', iterator_to_array($this->getPlans()))
             ->setPrompt('Choose plan')
             ->setRequired();
        $form->addSubmit('changeUser', 'Change')->setOmitted(false);
        $form->addSubmit('addUser', 'Add')->setOmitted(false);
        $form->onSuccess[] = [$this, 'onAddUsersFormSucceeded'];
        FormFactory::bootstrapify($form);
        return $form;
    }

     /* ******************************************************
                            * Actions *
    ****************************************************** */

    function onNewsFormSucceeded(Form $form, \stdClass $values)
    {
        if($values->offsetExists('delete')) {
            $this->flashMessage("Статья удалена.", "success");
            return $this->news->deleteNewsById($values->newsId);
        }

        if($values->offsetExists('return')) {
            $this->flashMessage("Статья восстановлена.", "success");
            return $this->news->returnNewsById($values->newsId1);
        }
        
        $result = $this->news->updateNewsById($values->newsId, $values->title, $this->getUser()->getId());
        if($result)
            $this->flashMessage("Произошла ошибка.", "error");
        else
            $this->flashMessage("Статья изменена.", "success");
    }

    function onAddnewsFormSucceeded(Form $form, \stdClass $values)
    {
        $result = $this->news->addNewsById($values->title, $values->content, $this->getUser()->getId());
        if($result)
            $this->flashMessage("Произошла ошибка.", "error");
        else
            $this->flashMessage("Статья добавлена.", "success");
    }

    function onEditServersFormSucceeded(Form $form, \stdClass $values)
    {
        $result = $this->editServerById($values->serversId, $values->label, $values->addr, $values->creds, $values->slots, $values->methods, $values->active);
    }

    function onPromoaddFormSucceeded(Form $form, \stdClass $values)
    {
    	if($values->offsetExists('create')){
    	$result = $this->addPromo($values->code, $values->plan, $values->duration, $values->maxused);
    	$this->flashMessage("Promocode create.", "success");	
        $this->redirect('AdminPanel:promo');
        }
    }

    function onAddUsersFormSucceeded(Form $form, \stdClass $values)
    {
        if($values->offsetExists('deleteUser')) {
            $this->flashMessage("Memberships deleted.", "success");
            return $this->deleteUserById($values->user);
        }

        if($values->offsetExists('changeUser')) {
            $this->flashMessage("Memberships changed.", "success");
            return $this->changeUserById($values->user);
        }

        if($values->offsetExists('addUser')) {
        $result = $this->addUserById($values->user, $values->plan);
        if($result)
            $this->flashMessage("Error.", "error");
        else
            $this->flashMessage("Memberships added.", "success");
        }
    }

    function onEditNewsFormSucceeded(Form $form, \stdClass $values)
    {
    	if($values->offsetExists('change')){
    	$this->flashMessage("News changed.", "success");
    	$result = $this->editNewsById($values->newsId, $values->title, $values->content, $values->status);
        }
    }
    use TBalance;
}
