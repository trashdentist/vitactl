<?php
namespace App\Presenters;
use Nette;
use Nette\Application\UI\Form;
use Nette\Application\ForbiddenRequestException;
use App\Models\AttackManager;
use App\Models\ServerManager;
use App\Models\MembershipManager;
use App\Forms\FormFactory;
use App\Exceptions\AttackIssueException;

final class AttacksPresenter extends RestrictedPresenter
{
    private $attacks;
    private $sman;
    private $mems;
    private $sub = NULL;
    private $vip = false;

    function __construct(FormFactory $ff, Nette\Database\Context $database, AttackManager $manager, ServerManager $sm, MembershipManager $mm)
    {
        parent::__construct($ff, $database);
        $this->attacks = $manager;
        $this->sman    = $sm;
        $this->mems    = $mm;
    }
    
    protected function startup()
    {
        parent::startup();
        $this->sub = $this->mems->getSubscriptionStatus($this->getUser()->getId());
        $this->template->sub = $this->sub;
    }

    private function getMethodsAsSelectPrompt()
    {
        $result    = [];
        $available = $this->sub->METHODS;
        
        $selection = $this->db->table("methods")->select("*")->where("vip = ?", $this->vip);
        
        foreach($selection as $method) {
            if(!in_array($method->id, $available)) continue;
            
            if(!isset($result[$method->type])) $result[$method->type] = [];
            
            $result[$method->type][$method->id] = $method->label;
        }
        
        return $result;
    }

    private function getServersAsSelectPrompt()
    {
        $selection = $this->db->table("machines")->select("*")->where("active", 1)->where("vip", $this->vip);
        
        foreach($selection as $machine) {
            if($this->isServerBusy($machine->id)) continue;
            
            yield $machine->id => $machine->label;
        }
    }
    
    private function isServerBusy(int $server): bool
    {
        return $this->sman->isServerBusy($server);
    }
    
    private function isGayAddress(string $domain): bool
    {
        foreach($this->config->Security["explict_blacklist"] as $k)
            if($k === $domain) return true;
        
        $IP4 = json_decode(@file_get_contents("https://dns-api.org/A/$domain"))[0];
        if(!isset($IP4->value)) {
            $IP4 = "0.0.0.0";
            if(preg_match("%^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$%", $domain)) $IP4 = $domain;
        }
        
        return $IP4 == "127.0.0.1" || $IP4 == "0.0.0.0";
    }
    
    private function validateChoice(int $server, int $method): ?string
    {
        $busy   = $this->isServerBusy($server);
        $server = $this->db->table("machines")->select("*")->where("active", 1)->where("id", $server)->fetch();
        $method = $this->db->table("methods")->get($method);
        if(!$server) return "Server state has been changed";
        if(!$method) return "This method doesn't exist!";
        if(!in_array($method->id, explode(",", $server->methods))) return "This server does not support this method";
        
        return !$busy ? null : "Server is busy";
    }

    protected function createComponentAttackForm(): Form
    {
        $form = $this->ff->create();
        $form->addText('target', 'Target: ')
             ->addRule(Form::URL, 'Please enter a valid URL.')
             ->setValue('https://www.target.ru:80')
             ->setRequired();
        $form->addSelect('machine', 'Server: ', iterator_to_array($this->getServersAsSelectPrompt()))
             ->setPrompt('Choose server')
             ->setRequired();
        $form->addSelect('method', 'Method: ', $this->getMethodsAsSelectPrompt())
             ->setPrompt('Choose method')
             ->setRequired();
        $form->addInteger('duration', 'Time (sec): ')
             ->addRule(Form::MIN, 'This is very short attack', 1)
             ->addRule(Form::MAX, 'This attack is too long', $this->sub->TIME)
             ->setValue(1)
             ->setRequired();
        $form->addCheckbox('immediate', 'Set immediate')
             ->setValue(TRUE)
             ->setDisabled();
        $form->addSubmit('go', 'Start');
        $form->onSuccess[] = [$this, 'attackFormSucceeded'];
        FormFactory::bootstrapify($form);
        return $form;
    }
    
    function attackFormSucceeded(Form $form, \stdClass $values)
    {
        $validation_result = $this->validateChoice($values->machine, $values->method);
        if(!is_null($validation_result)) return $form->addError($validation_result);
        if($this->isGayAddress(parse_url($values->target, PHP_URL_HOST))) return $form->addError("This domain is in the blacklist.");
        
        try {
            $this->dispatch(
                $this->db->table("machines")->get($values->machine),
                $this->db->table("methods")->get($values->method),
                $values->target,
                $values->duration
            );
        } catch(AttackIssueException $ex)
        {
            return $form->addError("Server rejected request, core dumped.", "danger");
        }
        
        $this->db->table("attacks")->insert([
            "type"     => $values->method,
            "target"   => $values->target,
            "machine"  => $values->machine,
            "invoker"  => $this->getUser()->getId(),
            "duration" => $values->duration,
            "begin"    => time(), #prevent time attack lul
        ]);
        
        $this->flashMessage("Boot launched successfully.", "success");
        $this->redirect("Attacks:mine");
    }

    function renderCreate(?int $server)
    {
        if($this->sub == NULL || $this->sub->STATUS === MembershipManager::SUBSCRIPTION_EXHAUSTED) {
            $this->flashMessage("It seems like you don't have any available threads for launching boots. Maybe you should consider an upgrade?)", "warning");
            return $this->redirect("Paywall:plans");
        } else if(sizeof(iterator_to_array($this->getServersAsSelectPrompt())) === 0) {
            return $this->redirect("Attacks:outage");
        }
    }
    
    function renderVip(?int $server)
    {
        if($this->sub == NULL || $this->sub->STATUS === MembershipManager::SUBSCRIPTION_EXHAUSTED || $this->sub->PLAN_ID < MembershipManager::PLAN_VIP) {
            $this->flashMessage("VIP Hub is available only for our VIP Customers. Maybe you should consider an upgrade?)", "warning");
            return $this->redirect("Paywall:plans");
        }
        $this->vip = true;
        return $this->renderCreate($server ?? NULL);
    }

    function renderMine()
    {
        $invoker                    = $this->getUser()->getId();
        $this->template->attacks    = $this->db->table("attacks")->where("invoker", $invoker);
        $this->template->lv_attacks = $this->attacks->getActiveAttacks($invoker);
    }
    
    function renderHalt(int $id)
    {
        $attack = $this->db->table("attacks")->get($id);
        if(!$attack) {
            $this->flashMessage("Invalid ID.", "danger");
            $this->redirect("mine");
            return;
        }
        
        if($attack->invoker != $this->user->getId()) {
            if(!$this->user->isInRole("sa")) throw new ForbiddenRequestException;
            
            $this->flashMessage("Foreign boot was stopped.", "warning");
        }
    
        try {
            if(!$this->haltServer($attack)) {
                $this->flashMessage("This attack has already been stopped, no action was taken.", "info");
            } else {
                $this->flashMessage("Boot has been canceled successfully.", "success");
                $this->attacks->stopAttack($id);
            }
        } catch(AttackIssueException $ex) {
            $this->flashMessage("Unknown error, core dumped.", "danger");
        }
        
        $this->redirect("mine");
    }
    
    function renderHaltAll()
    {
        $declined = false;
        $diff_sum = 0;
        $user     = $this->getUser()->getId();
        foreach($this->db->table("attacks")->select("*")->where("invoker", $user) as $attack) {
            if(($attack->begin + $attack->duration) <= time()) continue;
            
            $diff = $this->haltServer($attack);
            if(!$diff) $declined = true;
            
            $this->attacks->stopAttack($attack->id, 'imgay');
        }
        
        if($declined)
            $this->flashMessage("Some attacks were not stopped.", "warning");
        else
            $this->flashMessage("All attacks were stopped.", "success");
        $this->redirect("mine");
    }
    
    use TAttackDispatcher;
}
