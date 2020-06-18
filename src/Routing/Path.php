<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Routing;

/**
 * Tree style router with path traversal
 * @author Henry Paradiz <henry.paradiz@gmail.com>
 */
class Path
{
    public array $pathStack;
    public array $requestPath;
    protected array $_path;

    public function __construct(string $path)
    {
        $this->setPath($path);
    }

    public function peekPath()
    {
        if (!isset($this->_path)) {
            $this->setPath();
        }
        return count($this->_path) ? $this->_path[0] : false;
    }

    public function shiftPath()
    {
        if (!isset($this->_path)) {
            $this->setPath();
        }
        return array_shift($this->_path);
    }

    public function getPath()
    {
        if (!isset($this->_path)) {
            $this->setPath();
        }
        return $this->_path;
    }

    public function unshiftPath($string)
    {
        if (!isset($this->_path)) {
            $this->setPath();
        }
        return array_unshift($this->_path, $string);
    }

    /**
     * Private makes this class immutable
     *
     * @param string $requestURI
     * @return void
     */
    protected function setPath($requestURI = null)
    {
        if (!isset($this->pathStack)) {
            $parsedURL = parse_url($requestURI);
            $this->pathStack = $this->requestPath = explode('/', ltrim($parsedURL['path'], '/'));
        }

        $this->_path = isset($path) ? $path : $this->pathStack;
    }
}
