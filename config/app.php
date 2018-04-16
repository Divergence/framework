<?php
use \App\App as App;

return [
    'debug'			=>	file_exists(App::$ApplicationPath . '/.debug')
    ,'environment'	=>	(file_exists(App::$ApplicationPath . '/.dev') ? 'dev' : 'production'),
];
