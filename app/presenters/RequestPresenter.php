<?php
namespace App\Presenters;
use Nette;
use Nette\Application\UI\Form;
use App\Forms\FormFactory;

final class RequestPresenter extends RestrictedPresenter
{
    private function request(string $url): object
    {
        $time    = microtime(true);
        $url     = str_replace("http://", "", $url);
        $result  = @file_get_contents("http://$url");
        $headers = $http_response_header;
        if(!$result) {
            $failed = true;
            $result = '';
        }
        
        return (object) [
            "meta"    => [
                "time"    => (microtime(true) - $time)*1000,
                "success" => !isset($failed),
            ],
            "request" => [
                "headers" => ["HTTP/2.0 GET $url", "User-Agent: libwww-perl/1"],
                "body"    => ["<no body>"],
            ],
            "response" => [
                "headers" => $headers,
                "body"    => [$result],
            ],
        ];
    }
    
    function requestFormSucceeded(Form $form, \stdClass $values)
    {
        $request                 = $this->request($values->url);
        $this->template->request = $request;
        $this->template->setFile(__DIR__."/templates/Request/results.latte");
        if($request->meta["success"])
            $this->flashMessage("Request OK", "info");
        else
            $this->flashMessage("Web-server is returning error", "danger");
    }
    
    function createComponentRequestForm()
    {
        $form = $this->ff->create();
        $form->addText("url", "Website: ")->setRequired();
        $form->addSubmit('rq', 'Request');
        $form->onSuccess[] = [$this, 'requestFormSucceeded'];
        FormFactory::bootstrapify($form);
        $form->addReCaptcha('recaptcha', $label = '', $required = TRUE, $message = 'Verify, that you\'re not a robot');
        return $form;
    }
    
    function renderDefault()
    {
        
    }
}