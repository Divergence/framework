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
    protected $ID;

    #[Column(type: "enum", notnull:true, values:[])]
    protected $Class;

    #[Column(type: "timestamp", default:'CURRENT_TIMESTAMP')]
    protected $Created;

    #[Column(type: "integer", notnull:false)]
    protected $CreatorID;
}
