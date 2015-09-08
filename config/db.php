<?php
return [
	/*
	 *	MySQL database configuration
	 *
	 *		Socket is available instead of host.
	 *
	 */
    'mysql' => [
        'host'      =>  'localhost'
        ,'database' =>  'divergence'
        ,'username' =>  'divergence'
        ,'password' =>  'abc123'
    ]
    /*
     *	This configuration will be used instead of the default if
     *	the file .dev is detected inside of $_SERVER['DOCUMENT_ROOT']
     *
     *	You may override this behaviour in /config/DB.config.php
     *
     */
    ,'dev-mysql' => [
        'host'      =>  'localhost'
        ,'database' =>  'divergence'
        ,'username' =>  'divergence'
        ,'password' =>  'abc123'
    ]
];