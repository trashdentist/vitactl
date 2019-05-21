<?php
namespace App;
use Nette\Database;
use Nette\Security\AuthenticationException;
use Nette\Security\Identity;
use Nette\Security\IAuthenticator;

final class Authenticator implements IAuthenticator
{
    private $database;
    
    public function __construct(Database\Context $database)
    {
        $this->database = $database;
    }
    
    static function verifyPassword(string $password, string $hashedPassword): bool
    {
        list($hash, $salt) = explode("$", $hashedPassword);
        return hash_equals(hash("whirlpool", $password.$salt), $hash);
    }
    
    public function authenticate(array $credentials): \Nette\Security\IIdentity
    {
        list($username, $password) = $credentials;
        $user = $this->database->table('users')->where('login', $username)->fetch();
        
        if(!$user) throw new AuthenticationException('User not found.');
        if(!$this->verifyPassword($password, $user->hash)) throw new AuthenticationException('Invalid password.');
        return new Identity($user->id, $user->role, [
            'username' => $user->login,
            'email'    => $user->email,
            'balance'  => $user->coins,
            'banned'   => $user->banned,
        ]);
    }
}
