<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Controllers\Records;

use Psr\Http\Message\ResponseInterface;

abstract class AbstractRecordsEndpoint
{
    abstract public function handle(...$arguments): ResponseInterface;
}
