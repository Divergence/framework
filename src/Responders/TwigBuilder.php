<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Responders;

use Divergence\App;
use Twig\Environment;
use GuzzleHttp\Psr7\Utils;
use Twig\Loader\FilesystemLoader;
use Psr\Http\Message\StreamInterface;
use Twig\Extension\StringLoaderExtension;

class TwigBuilder extends ResponseBuilder
{
    protected string $contentType = 'text/html; charset=utf-8';

    public function getBody(): StreamInterface
    {
        $loader = new FilesystemLoader([App::$App->ApplicationPath.'/views']);
        $env = new Environment($loader, ['strict_variables' => true]);
        $env->addExtension(new StringLoaderExtension());
        $output = $env->render($this->template, $this->data);
        return Utils::streamFor($output);
    }
}
