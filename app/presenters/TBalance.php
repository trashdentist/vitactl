<?php
namespace App\Presenters;

trait TBalance
{
    private function getBalanceById(int $user): float
    {
        return $this->db->table("users")
                        ->select("coins")
                        ->where("id", $user)
                        ->fetch()
                        ->coins;
    }
    
    private function alterBalanceById(int $user, int $by, bool $top = false, bool $dissmissInfinite = false): bool
    {
        $coins = $this->db->table("users")->get($user)->coins;
        if($coins == -1 && !$dissmissInfinite) return true; #infinite balance is infinite)
        
        $coins = $top ? ($coins + $by) : ($coins - $by);
        if($coins < 0 && !$top) return false;

        $this->db->table("users")
                 ->where("id", $user)
                 ->update([
                     "coins" => $coins,
                 ]);
        
        return true;
    }
    
    private function topBalanceById(int $user, int $by): bool
    {
        return $this->alterBalanceById($user, $by, true);
    }
    
    private function withdrawBalanceById(int $user, int $by): bool
    {
        return $this->alterBalanceById($user, $by);
    }
    
    private function setInfiniteBalanceById(int $user): void
    {
        $this->db->table("users")
                 ->where("id", $user)
                 ->update([
                     "coins" => -1,
                 ]);
    }
    /* function for servers */
    private function setBanUserById(int $user): void
    {
        $this->db->table("users")
                 ->where("id", $user)
                 ->update([
                     "banned" => 1,
                 ]);
    }

    private function getBalance(): float
    {
        return $this->getBalanceById($this->getUser()->getId());
    }
    
    private function topBalance(int $by): bool
    {
        return $this->topBalanceById($this->getUser()->getId(), $by);
    }
    
    private function withdrawBalance(int $by): bool
    {
        return $this->withdrawBalanceById($this->getUser()->getId(), $by);
    }
    
    private function setInfiniteBalance()
    {
        return $this->setInfiniteBalanceById($this->getUser()->getId());
    }
}
