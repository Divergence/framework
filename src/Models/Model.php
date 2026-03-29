<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Models;

use Divergence\Models\Mapping\Column;

/**
 * Model.
 *
 * @author Henry Paradiz <henry.paradiz@gmail.com>
 *
 * {@inheritDoc}
 */
class Model extends ActiveRecord
{
    use Getters;

    #[Column(type: "integer", primary:true, autoincrement:true, unsigned:true)]
    private int $ID;

    #[Column(type: "enum", values:[])]
    private string $Class;

    #[Column(type: "timestamp", default:'CURRENT_TIMESTAMP')]
    private string $Created;

    #[Column(type: "integer")]
    private ?int $CreatorID;
}
