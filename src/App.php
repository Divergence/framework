<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence;

use Divergence\Routing\Path;
use Divergence\Controllers\Main;
use Divergence\Responders\Emitter;
use GuzzleHttp\Psr7\ServerRequest;
use Divergence\Controllers\SiteRequestHandler;

class App
{
    public const VERSION = '1.1';
    public $ApplicationPath;
    public $Config;
    public Path $Path;
    public \Whoops\Run $whoops;
    public static App $App; // instance of this class for reference

    public function __construct($Path)
    {
        static::$App = $this;
        $this->init($Path);
    }

    public function init($Path)
    {
        $this->ApplicationPath = $Path;

        $this->Path = new Path($_SERVER['REQUEST_URI']);

        $this->Config = $this->config('app');

        $this->registerErrorHandler();
    }

    public function config($Label)
    {
        $Config = $this->ApplicationPath . '/config/' . $Label . '.php';
        if (!file_exists($Config)) {
            throw new \Exception($Config . ' not found in '.static::class.'::config()');
        }
        return require $Config;
    }

    public function handleRequest()
    {
        $main = new SiteRequestHandler();
        $response = $main->handle(ServerRequest::fromGlobals());
        (new Emitter($response))->emit();
    }

    public function registerErrorHandler()
    {
        // only show errors in dev environment
        if ($this->Config['environment'] == 'dev') {
            $this->whoops = new \Whoops\Run();

            $Handler = new \Whoops\Handler\PrettyPageHandler();
            $Handler->setPageTitle("Divergence Error");

            $this->whoops->pushHandler($Handler);
            $this->whoops->register();
        } else {
            error_reporting(0);
        }
    }
}
