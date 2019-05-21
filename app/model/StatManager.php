<?php
namespace App\Models;
use Nette;
use Nette\Database;

class StatManager
{
    use Nette\SmartObject;
    
    const ATTACKS = "ATTACKS";
    const REGS    = "REGS";
    
    private $db;
    private $stats;
    
    function __construct(Database\Context $database, Database\Connection $raw_connection)
    {
        $this->db    = $database;
        $this->stats = $this->db->table("statistics");
    }
    
    function pushStats(string $type = "ATTACKS", int $value = 0, int $timestamp = null)
    {
        $this->stats->insert([
            "type"  => $type,
            "value" => $value,
            "time"  => $timestamp ?? time(),
        ]);
    }
    
    protected function getStats($type = "ATTACKS", $limit = true)
    {
        $selection = $this->stats->select("*")->where("type", $type)->order("time ASC");
        if($limit) $selection->limit($type === "ATTACKS" ? 120 : 7);
        
        return $selection;
    }
    
    function getRegistrationStats()
    {
        return iterator_to_array($this->getStats(self::REGS));
    }
    
    function getLiveAttacksStats()
    {
        return iterator_to_array($this->getStats(self::ATTACKS));
    }
}