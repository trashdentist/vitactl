<?php
namespace App\Presenters;
use Nette;
use App\Forms\FormFactory;
use App\Models\AttackManager;
use App\Models\MembershipManager;

final class DashboardPresenter extends RestrictedPresenter 
{
    private $attacks;
    private $mems;
    private $sub = NULL;

    function __construct(FormFactory $ff, Nette\Database\Context $database, AttackManager $manager, MembershipManager $mm)
    {
        parent::__construct($ff, $database);
        $this->attacks = $manager;
        $this->mems    = $mm;
    }
    
    protected function startup()
    {
        parent::startup();
        $this->template->sub = $this->mems->getSubscriptionStatus($this->getUser()->getId());
    }

    private function getMetrics(): \stdClass
    {
        return (object) [
            "attacks"  => $this->db->table("attacks")->count("id"),
            "machines" => $this->db->table("machines")->count("id"),
            "users"    => $this->db->table("users")->count("id"),
            "live"     => sizeof(iterator_to_array($this->attacks->getActiveAttacks())),
        ];
    }

    function renderDefault()
    {
        $user    = $this->getUser();
        $metrics = $this->getMetrics();
        
        $this->template->attacks    = $metrics->attacks;
        $this->template->lv_attacks = $metrics->live;
        $this->template->machines   = $metrics->machines;
        $this->template->users      = $metrics->users;
        $this->template->balance    = $this->getBalance();
        $this->template->news       = $this->db->table("news")->select("*")->where("deleted", 0)->limit($this->config->Website["newsPerPage"]);
        $this->template->machinesL  = $this->db->table("machines")->select("*");
    }
    
    use TBalance;
}
