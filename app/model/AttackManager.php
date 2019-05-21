<?php
namespace App\Models;
use Nette;
use Nette\Database;

class AttackManager
{
    use Nette\SmartObject;
    private $db;
    private $raw_db;
    
    function __construct(Database\Context $database, Database\Connection $raw_connection)
    {
        $this->db     = $database;
        $this->raw_db = $raw_connection;
    }
    
    function stopAttack(int $id): void
    {
        $this->db->table("attacks")
                 ->where("id", $id)
                 ->update([
                     "duration" => 0
                 ]);
    }
    
    function getActiveAttacks(?int $user = null)
    {
        $args  = [];
        $date  = time();
        $query = "SELECT * FROM `attacks` WHERE (`begin` + `duration`) > $date";
        if(!is_null($user)) {
            $query .= " AND `invoker` = ?";
            $args[] = $query;
            $args[] = $user;
        } else {
            $args[] = $query;
        }
        
        return $this->raw_db->query(...$args);
    }
    
    /**
     * This methods removes old attacks.
     */
    function vaccum()
    {
        $date = time();
        return $this->raw_db->query("DELETE FROM `attacks` WHERE (`begin` + `duration`) < $date");
    }
}
