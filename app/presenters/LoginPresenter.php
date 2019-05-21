<?php
namespace App\Presenters;
use Nette;
use Nette\Application\UI\Form;
use Nette\Security\AuthenticationException;
use App\Forms\FormFactory;

final class LoginPresenter extends BasePresenter
{
    private $users;
    private $invites;
    
    function __construct(FormFactory $ff, Nette\Database\Context $database)
    {
        parent::__construct($ff, $database);
        $this->users   = $this->db->table("users");
        $this->invites = $this->db->table("promos");
    }

    private function mkhash(string $password): string
    {
        $salt  = bin2hex(openssl_random_pseudo_bytes(3));
        
        return hash("whirlpool", $password.$salt)."\$$salt";
    }
    
    private function promocode(string $promo, int $uid): bool
    {
        $promo = $this->db->table("promos")
                      ->select("*")
                      ->where("code", $promo)
                      ->where("used < max")
                      ->fetch();
        if(!$promo) return false;
        
        $this->db->table("promos")->where("id", $promo->id)->update(["used" => $promo->used + 1]);
        $this->db->table("memberships")->insert([
            "plan"     => $promo->plan,
            "user"     => $uid,
            "assigned" => time(),
            "duration" => $promo->duration,
        ]);
        
        return true;
    }
    
    protected function createComponentLoginForm(): Form
    {
        $form = $this->ff->create();
        $form->addText('uname', 'Username: ')->setRequired();
        $form->addPassword('password', 'Password: ')->setRequired();
        $form->addSubmit('login', 'Login');
        $form->onSuccess[] = [$this, 'loginFormSucceeded'];
        FormFactory::bootstrapify($form);
        $form->addReCaptcha('recaptcha', $label = '', $required = TRUE, $message = 'Verify, that you\'re not a robot');
        return $form;
    }
    
    protected function createComponentRegisterForm(): Form
    {
        $form = $this->ff->create();
        $form->addText('login', 'Username: ')
             ->setRequired()
             ->addRule(Form::MIN_LENGTH, 'Username too short! (minimum length: %d characters)', 3)
             ->addRule(Form::MAX_LENGTH, 'Username too long (minimum length: %d characters)', 36);
        $form->addText('invite', 'Promo-code: ');
        $form->addEmail('email', 'E-mail: ')->setRequired();
        $form->addPassword('password', 'Password: ')
             ->setRequired()
             ->addRule(Form::MIN_LENGTH, 'Password is weak! (minimum length: %d characters)', 9);
        $form->addPassword('password_c', 'Repeat password: ')->setRequired();
        $form->addSubmit('reg', 'Reigister');
        $form->onSuccess[] = [$this, 'registerFormSucceeded'];
        FormFactory::bootstrapify($form);
        $form->addReCaptcha('recaptcha', $label = '', $required = TRUE, $message = 'Verify that you\'re not a robot');
        return $form;
    }
    
    function registerFormSucceeded(Form $form, \stdClass $values)
    {
        if($values->password != $values->password_c) return $form->addError("Passwords don't match");
        
        try {
            $phash = $this->mkhash($values->password);
            
            $user = $this->users->insert([
                "login"    => $values->login,
                "email"    => $values->email,
                "hash"     => $phash,
                "coins"    => 0,
                "role"     => "us",
            ]);
            
            if(!$this->promocode($values->invite ?? "__DBG_NOCD", $user->id)) $this->flashMessage("This promocode is not applicable or does not exist.", "warning");
            
            $this->redirect('Login:login');
        } catch(\Nette\Database\DriverException $ex) {
            $form->addError("This user/email already exists.");
        }
    }

    function loginFormSucceeded(Form $form, \stdClass $values)
    {
        $user = $this->getUser();
        try {
            $user->login($values->uname, $values->password);
            $user->setExpiration("14 days");
            $this->redirect("Login:login");
        } catch(AuthenticationException $ex) {
            $form->addError("Incorrect username or password.");
        }
    }

    function renderLogin()
    {
        $user = $this->getUser();
        if($user->isLoggedIn()) return $this->redirect('Dashboard:default');
    }

    function renderRegister()
    {
        $user = $this->getUser();
        if($user->isLoggedIn()) return $this->redirect('Dashboard:default');
    }
    
    function renderLogout()
    {
        $user = $this->getUser();
        if($user->isLoggedIn()) $user->logout();
        $this->redirect('Homepage:default');
    }
}
