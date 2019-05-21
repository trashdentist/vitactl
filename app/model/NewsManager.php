<?php
namespace App\Models;
use Nette;
use Nette\Database;
use Nette\Database\Table\ActiveRow;

class NewsManager
{
    use Nette\SmartObject;
    private $db;
    private $raw_db;
    
    function __construct(Database\Context $database, Database\Connection $raw_connection)
    {
        $this->db     = $database;
        $this->raw_db = $raw_connection;
    }

 	function deleteNewsById(int $id): void
    {
        $this->db->table("news")
                 ->where("id", $id)
                 ->update([
                     "deleted" => 1,
                 ]);
    }

    function returnNewsById(int $id): ActiveRow
    {
        return $this->db->table("news")
                 ->where("id", $id)
                 ->update([
                     "deleted" => 0,
                 ]);
    }

    function updateNewsById(int $id, $title, $content): void
    {
        $this->db->table("news")
                 ->where("id", $id)
                 ->update([
                     "title" => $title,
                     "content" => $content,
                 ]);
    }

    function addNewsById($title, $content, $author): void
    {
        $this->db->table("news")
                 ->insert([
                     "title" => $title,
                     "content" => $content,
                     "author" => $author,
                 ]);
    }    

}
