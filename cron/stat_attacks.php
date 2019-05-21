<?php
$container = require __DIR__ . '/../app/bootstrap.php';
$am        = $container->getByType(App\Model\AttackManager::class);
$sm        = $container->getByType(App\Model\StatManager::class);

$currentAttacks = sizeof(iterator_to_array($am->getActiveAttacks()));

$sm->pushStats("ATTACKS", $currentAttacks, time());