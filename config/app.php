<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use \Divergence\App as App;

return [
    'debug'			=>	file_exists(App::$App->ApplicationPath . '/.debug'),
    'environment'	=>	(file_exists(App::$App->ApplicationPath . '/.dev') ? 'dev' : 'production'),
];
