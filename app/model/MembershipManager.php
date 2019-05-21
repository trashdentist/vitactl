<?php
namespace App\Models;
use Nette;
use Nette\Database;
use Nette\Database\Table\ActiveRow;

//моча какая-то
//вот не трогайте эту парашу просто пиздец
//вильям я тя проклинаю блять уёбак(((
class MembershipManager
{
    use Nette\SmartObject;
    
    //Рури сосёт хуи
    const PLAN_STARTER   = 1;
    const PLAN_ADVANTAGE = 2;
    const PLAN_VIP       = 3;
    
    const SUBSCRIPTION_NO        = NULL;
    const SUBSCRIPTION_EXHAUSTED = FALSE;
    const SUBSCRIPTION_ACTIVE    = TRUE;
    
    protected $vaccumOnEachRequest = TRUE;
    
    private $db;
    private $raw_db;
    private $memberships;
    private $attacks;
    
    function __construct(Database\Context $database, Database\Connection $raw_connection, AttackManager $attacks)
    {
        $this->db          = $database;
        $this->raw_db      = $raw_connection;
        $this->memberships = $this->db->table("memberships");
        $this->attacks     = $attacks;
    }
    
    /**
     * @returns latest assigned subscription or null if there is none.
    */
    protected function getSubscription(int $user): ?ActiveRow
    {
        //Nette optimization bug; messes up queries lol
        $query = "SELECT id FROM `memberships` WHERE user = ? AND (`assigned` + `duration`) > ? ORDER BY `assigned` DESC LIMIT 1";
        $sub   = $this->raw_db->query($query, $user, time())->fetch( );
        if(!$sub) return NULL;
        
        return $this->memberships->get($sub->id);
    }
    
    protected function vaccum(): void
    {
        $this->memberships->where("(`assigned` + `duration`) <= ?", time())
                          ->delete();
    }
    
    protected function getAllowedMethods(int $plan): ?iterable
    {
        foreach($this->db->table('methods')->select('id')->where('min_plan <= ?', $plan) as $method) {
            yield $method->id;
        }
    }
    
    function getSubscriptionStatus(int $user): ?object
    {
        $subscription = $this->getSubscription($user);
        if(is_null($subscription)) return MembershipManager::SUBSCRIPTION_NO;
        
        $threads   = $subscription->ref("plan")->threads;
        $remaining = max($threads - $this->attacks->getActiveAttacks($user)->getRowCount(), 0);
        $status    = $remaining > 0 ? MembershipManager::SUBSCRIPTION_ACTIVE : MembershipManager::SUBSCRIPTION_EXHAUSTED;
        
        $result = (object) [
            "STATUS"    => $status,
            "PLAN_ID"   => $subscription->ref("plan")->id,
            "PLAN_NAME" => $subscription->ref("plan")->label,
            "THREADS"   => $threads,
            "REMAINING" => $remaining,
            "TIME"      => $subscription->ref("plan")->time,
            "METHODS"   => iterator_to_array($this->getAllowedMethods($subscription->ref("plan")->id)),
        ];
        
        if($this->vaccumOnEachRequest) $this->vaccum();
        
        return $result;
    }
    
    function cancelSubscription(int $user): void
    {
        $this->memberships->where("user", $user)
                          ->update([
                                "duration" => 0,
                            ]);
    }
    
    function verifySecondsWithdrawal(int $user, int $amount = 0): bool
    {
        $sub = $this->getSubscriptionStatus($user);
        if(is_null($sub) || $sub->STATUS === MembershipManager::SUBSCRIPTION_EXHAUSTED) return false;
        
        return $sub->REMAINING - $amount >= 0;
    }
    
    /**
     * Don't use this shit. No, really, you should not.
     * 
     * Why not? This immediately makes you fucking gay.
     * Also, if your DB is configured correctly, "boots" automatically increment expenses, so if using this function is the answer to your question,
     * then you're either implementing another one paid killer feature which is as unnescessary as this entire subscription-based system
     * OR you're asking WRONG QUESTION!!!
     * 
     * Examples of WRONG questions:
     * Q: What should I do to verify I have enough seconds to launch "boot"?
     * Wrong Answer: Disable all triggers in DB and use this instead.
     * Real Answer: see MembershipManager->verifySecondsWithdrawal
     * 
     * Examples of somewhat meaningful use of this function:
     * You don't have a DB engine, that supports triggers and/or saved procedures, and to use Vitalina SC you remove all trigers and use this everywhere.
     * 
     * What to do if your code relies on this function?
     * Well get cursed then LMAO
     * 
     * Who wrote this? Malvina, of course, she speaks english, and she didn't stole this. In fact, only she can write shit like that.
     * 
     * A very important warning: This function is really expensive, if you have 128GB ram, you should defenitely use this function, otherwise, forget about it.
     * 
     * @deprecated
     * @see MembershipManager->verifySecondsWithdrawal
     * 
     * @param int $user   - user ID
     * @param int $amount - amount to withdraw
     * 
     * @returns bool      - has operation completed successfully? (Fails in case of insufficent funds)
     */
    function forceSecondsWithdrawal(int $user, int $amount = 0): bool
    {
        if(!$this->verifySecondsWithdrawal($user, $amount)) return false;
        $sub = $this->getSubscription($user);
        $sid = $sub->id;
        $sex = $sub->expense; #Виталина Павленко
        
        $this->memberships->where("id", $sub)->update(["expense" => $sex - $amount]);
        
        return true;
    }
    
    function forceSecondsTop(int $user, int $amount = 0): bool
    {
        return $this->forceSecondsWithdrawal($user, $amount*-1);
    }
}