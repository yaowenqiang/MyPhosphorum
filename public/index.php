<?php

error_reporting(E_ALL);

if (!isset($_GET['_url'])) {
	$_GET['_url'] = '/';
}

/**
 * Read the configuration
 */
$appDir = dirname(dirname(__DIR__.'./../'));
$config = include $appDir . "/app/config/config.php";

/**
 * Include the loader
 */
require  $appDir."/app/config/loader.php";

/**
 * Include composer autoloader
 */
require $appDir . "/vendor/autoload.php";

try {

	/**
	 * The FactoryDefault Dependency Injector automatically register the right services providing a full stack framework
	 */
	$di = new \Phalcon\DI\FactoryDefault();

	/**
	 * Include the application services
	 */
	require __DIR__ . "/../app/config/services.php";

	/**
	 * Handle the request
	 */
	$application = new Phalcon\Mvc\Application($di);

	echo $application->handle()->getContent();

} catch (Exception $e) {
	echo 'Sorry, an error has ocurred :('; echo $e->getMessage();
}
