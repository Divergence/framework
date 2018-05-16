<?php
use \Divergence\App as App;

return [
    'debug'			=>	file_exists(App::$ApplicationPath . '/.debug'),
    'environment'	=>	(file_exists(App::$ApplicationPath . '/.dev') ? 'dev' : 'production'),
];
