<?php
namespace App\Models;
use Nette;
use Nette\Database;
use App\Models\AttackManager;

class ServerManager
{
    use Nette\SmartObject;
    private $db;
    private $am;
    
    function __construct(Database\Context $database, AttackManager $manager)
    {
        $this->db = $database;
        $this->am = $manager;
    }
    
    function isServerBusy(int $id): bool
    {
        $busy  = 0;
        $slots = $this->db->table("machines")->get($id)->slots;
        foreach($this->am->getActiveAttacks() as $attack)
        {
            if($busy >= $slots) return true;
            if($attack->machine == $id) $busy += 1;
        }
        
        return false;
    }
}