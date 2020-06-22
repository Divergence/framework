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

    /**
     * If path is /my/example this function returns my the first time and example the second time
     * False is returned once you run out of path.
     * @return string|false
     */
    public function peekPath()
    {
        return count($this->_path) ? $this->_path[0] : false;
    }

    /**
     * The shifted value, or null; if array is empty or is not an array
     *
     * @return mixed
     */
    public function shiftPath()
    {
        return array_shift($this->_path);
    }

    /**
     * Returns the path stack
     *
     * @return array
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * Adds a value to the path stack so that the next shiftPath or peekPath is what you provide here
     *
     * @param string $string
     * @return int Count of path stack after appending to it
     */
    public function unshiftPath($string)
    {
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
