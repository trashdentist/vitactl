<?php

declare(strict_types=1);

namespace App\Router;

use Nette;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;


final class RouterFactory
{
	use Nette\StaticClass;

	public static function createRouter(): RouteList
	{
		$router = new RouteList;
		$router[] = new Route('/', 'Homepage:default');
		$router[] = new Route('/paywall-signaling', 'Signaling:paywall');
		$router[] = new Route('/login', 'Login:login');
		$router[] = new Route('/reg', 'Login:register');
		$router[] = new Route('/logout', 'Login:logout');
		$router[] = new Route('/vitactl/<action>[/<id>]', 'AdminPanel:default');
		$router[] = new Route('hub/<presenter>/<action>[/<id>]', 'Dashboard:default');
		return $router;
	}
}
