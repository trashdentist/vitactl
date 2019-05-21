<?php
namespace App\Presenters;
use Nette;

final class SignalingPresenter extends BasePresenter
{
    function renderPaywall()
    {
        if($_POST["status"] !== "paid") exit(header("HTTP/1.1 200 ok"));
        
        $invoice = $this->db->table("invoices")->select("*")->where("external", $_POST["id"]);
        if(!$invoice) return $this->error();
        
        $this->db->table("invoices")->update([
            "paid" => true,
        ])->where("id", $invoice->id);
        $this->db->table("memberships")->insert([
            "plan"     => $invoice->plan,
            "user"     => $invoice->to,
            "assigned" => time(),
            "duration" => 2332800, #assign for 27 days
        ]);
        
        exit(header("HTTP/1.1 202 Accepted"));
    }
}