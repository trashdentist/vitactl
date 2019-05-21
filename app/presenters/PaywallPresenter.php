<?php
namespace App\Presenters;
use Nette;
use Nette\Application\UI\Form;
use App\Forms\FormFactory;

final class PaywallPresenter extends RestrictedPresenter
{
    private $plan;
    
    protected function createComponentInvoiceForm(): Form
    {
        $form = $this->ff->create();
        $form->addText("name", "Name: ")
             ->setValue("Osama Ben-Laden")
             ->setRequired();
        $form->addEmail("email", "E-Mail: ")
             ->setValue("osama@mspaintadventures.ru")
             ->setRequired();
        $form->addHidden("_planUID")
             ->setValue($this->plan->id)
             ->setRequired();
        $form->addSubmit('confirm', 'Checkout');
        $form->onSuccess[] = [$this, 'invoiceFormSucceeded'];
        FormFactory::bootstrapify($form);
        return $form;
    }
    
    private function createOpenNodeCharge(string $desc, int $amount, string $name, string $email): object
    {
        $key = $this->config->Payments["key"];
        
        $params = json_encode([
            "description"    => $desc,
            "amount"         => $amount,
            "currency"       => "USD",
            "customer_email" => $email,
            "customer_name"  => $name,
            "callback_url"   => $this->config->Website["url"]."paywall-signaling/",
            "success_url"    => $this->config->Website["url"]."hub/paywall/bills",
        ]);
        
        $context  = stream_context_create([
            "http" => [
                "header"  => "Content-type: application/json\r\nAuthorization:$key",
                "method"  => "POST",
                "content" => $params,
            ],
        ]);
        
        $result = file_get_contents("https://api.opennode.co/v1/charges", false, $context);
        if(!$result)
            throw new \Exception("An error occured during charging!");
        else
            $result = json_decode($result)->data;
        
        return $result;
    }
    
    function renderBuy(int $id = 1)
    {
        $plan = $this->db->table("plans")->get($id);
        if(!$plan) {
            $this->flashMessage("This plan doesn't exist.", "danger");
            $this->redirect("Paywall:plans");
            exit;
        } else if(is_null($plan->price)) {
            $this->flashMessage("Plan ".$plan->label." is for internal use only.", "danger");
            $this->redirect("Paywall:plans");
            exit;
        }
        
        $this->plan           = $plan;
        $this->template->plan = $plan;
    }
    
    function invoiceFormSucceeded(Form $form, \stdClass $values)
    {
        $plan = $this->db->table("plans")->get($values->_planUID);
        if(!$plan || is_null($plan->price)) {
            $this->flashMessage("State of this plan has changed during the checkout sequence.", "danger");
            $this->redirect("Paywall:plans");
            exit;
        }
        
        $charge = $this->createOpenNodeCharge($plan->label." 1 month subscription", $plan->price, $values->name, $values->email)->id;
        $this->db->table("invoices")->insert([
            "to"     => $this->getUser()->getId(),
            "plan"   => $values->_planUID,
            "name"   => $values->name,
            "email"  => $values->email,
            "placed" => time(),
            "external" => $charge,
        ]);
        
        $this->redirectUrl("https://checkout.opennode.co/$charge");
        exit;
    }
}