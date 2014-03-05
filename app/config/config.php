<?php

return new \Phalcon\Config(array(
	'database' => array(
		'adapter'  => 'Mysql',
		'host'     => 'localhost',
		'username' => 'root',
		'password' => '1987123',
		'name'     => 'forum',
	),
	'application' => array(
		'controllersDir' => $appDir.'/app/controllers/',
		'modelsDir'      => $appDir.'/app/models/',
		'viewsDir'       => $appDir.'/app/views/',
		'pluginsDir'     => $appDir.'/app/plugins/',
		'libraryDir'     => $appDir.'/app/library/',
		'baseUri'        => '/',
		'debug'          => true,
	),
	'models' => array(
		'metadata' => array(
			'adapter' => 'Memory'
		)
	),
	'github' => array(
		'clientId' => '21ac346fbbde907a9f51',
		'clientSecret' => '370427b8b42a91d3510edea6e3304b7f1f57b36b',
		'redirectUri' => 'http://115.28.221.31/forum/login/oauth/access_token'
	),
	'amazon' => array(
		'AWSAccessKeyId' => '',
		'AWSSecretKey' => ''
	)
));
