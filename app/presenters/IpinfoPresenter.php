<?php
namespace App\Presenters;
use Nette;
use Nette\Application\UI\Form;
use App\Forms\FormFactory;

final class IpinfoPresenter extends RestrictedPresenter
{
    
    function requestFormSucceeded(Form $form, \stdClass $values)
    {
        $ip                   = $values->ip;
        $this->template->info = json_decode(file_get_contents("http://api.ipstack.com/$ip?access_key=9a5969adc6f058e1981bd49b3698db84&security=1&hostname=1&fields=main,location.country_flag,security.is_tor,connection.isp"));
        $this->template->setFile(__DIR__."/templates/Ipinfo/results.latte");
        dump($this->template->info);
    }
    
    function createComponentRequestForm()
    {
        $form = $this->ff->create();
        $form->addText("ip", "IP: ")->setRequired();
        $form->addSubmit('rq', 'Submit');
        $form->onSuccess[] = [$this, 'requestFormSucceeded'];
        FormFactory::bootstrapify($form);
        $form->addReCaptcha('recaptcha', $label = '', $required = TRUE, $message = 'Verify, that you\'re not a robot');
        return $form;
    }
    
    function renderDefault()
    {
        
    }
}