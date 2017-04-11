<?php
return array(
		"siteUrl"=>"%siteUrl%",
		"documentRoot"=>"%documentRoot%",
		"database"=>[
				"dbName"=>"%dbName%",
				"serverName"=>"%serverName%",
				"port"=>"%port%",
				"user"=>"%user%",
				"password"=>"%password%"
		],
		"onStartup"=>function($action){
		},
		"sessionToken"=>"%temporaryToken%",
		"namespaces"=>[],
		"templateEngine"=>'micro\views\engine\Twig',
		"templateEngineOptions"=>array("cache"=>false),
		"test"=>false,
		"debug"=>false,
		"di"=>[%injections%],
		"cacheDirectory"=>"cache/",
		"mvcNS"=>["models"=>"models","controllers"=>"controllers"]
);
