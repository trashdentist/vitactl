<?php
declare(strict_types=1);

$container = require __DIR__ . '/../app/bootstrap.php';
$db        = $container->getByType(Nette\Database\Context::class);
$sm        = $container->getByType(App\Model\StatManager::class);

$time = strtotime("midnight", time()) - 1;
$regs = $this->db->table("users")->select("count(*)")->where("since > ?", $time);

$sm->pushStats("REGS", $currentAttacks, $time);