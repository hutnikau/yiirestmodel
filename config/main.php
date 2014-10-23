<?php

// uncomment the following to define a path alias
// Yii::setPathOfAlias('local','path/to/local-folder');

// This is the main Web application configuration. Any writable
// CWebApplication properties can be configured here.
return array(
	'basePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
	'name'=>'YiiRestModel',

	// preloading 'log' component
	'preload'=>array('log'),
        'language'=>'en',
        'sourceLanguage'=>'en_us',

	// autoloading model and component classes
	'import'=>array(
		'application.models.*',
		'application.models.behaviors.*',
		'application.components.*',
	),

	'modules'=>array(
                'api'
	),

	// application components
	'components'=>array(
		'user'=>array(
			// enable cookie-based authentication
			'allowAutoLogin'=>true,
                        'class'=>'WebUser',
		),
                'authManager'=>array(
                        'class'=>'CDbAuthManager',
                        'connectionID'=>'db',
                ),
		// uncomment the following to enable URLs in path-format
		
		'urlManager'=>array(
			'urlFormat'=>'path',
                        'showScriptName'=>false,
			'rules'=>array(
                                array('site/index', 'pattern'=>'<controller:(?!gii|api|administrator|templates)\w+>/*'),
                                
                                /* RESTfull API */
                                // GET
                                array('api/<controller>/list', 'pattern'=>'api/<controller:\w+>', 'verb'=>'GET'),
                                array('api/<controller>/view', 'pattern'=>'api/<controller:\w+>/<id:\d+>', 'verb'=>'GET'),
                            
                                // POST
                                array('api/<controller>/create', 'pattern'=>'api/<controller:\w+>', 'verb'=>'POST'),
                            
                                // PUT
                                array('api/<controller>/update', 'pattern'=>'api/<controller:\w+>/<id:\d+>', 'verb'=>'PUT'),
                                array('api/<controller>/update', 'pattern'=>'api/<controller:\w+>', 'verb'=>'PUT'),
                            
                                // DELETE
                                array('api/<controller>/delete', 'pattern'=>'api/<controller:\w+>/<id:\d+>', 'verb'=>'DELETE'),
                                array('api/<controller>/delete', 'pattern'=>'api/<controller:\w+>', 'verb'=>'DELETE'),
			),
                    
		),
		
		'db'=>array(
			'connectionString' => 'mysql:host=localhost;dbname=yiirestmodel',
			'emulatePrepare' => true,
			'username' => 'root',
			'password' => '',
			'charset' => 'utf8',
                        //'enableProfiling' => true,
                        //'enableParamLogging' => true,
		),
		
		'errorHandler'=>array(
			// use 'site/error' action to display errors
			'errorAction'=>'site/error',
		),
		'log'=>array(
			'class'=>'CLogRouter',
			'routes'=>array(
				array(
					'class'=>'CFileLogRoute',
					'levels'=>'error, warning',
				),
				// uncomment the following to show log messages on web pages
				/*
				array(
					'class'=>'CWebLogRoute',
				),
				*/
			),
		),
	),

	// application-level parameters that can be accessed
	// using Yii::app()->params['paramName']
	'params'=>array(
		// this is used in contact page
	),
);