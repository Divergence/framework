<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests\MockSite\Models;

use ReflectionClass;

use Divergence\Models\Versioning;
use Divergence\Models\Mapping\Column;
use Divergence\Tests\MockSite\Mock\Data;

/*
 *  The purpose of this Model is to provide an example of every field type
 *  hopefully in every possible configuration.
 *
 */
class Canary extends \Divergence\Models\Model
{
    use Versioning;

    // ActiveRecord configuration
    public static $tableName = 'canaries';

    // versioning
    public static $historyTable = 'canaries_history';
    public static $createRevisionOnDestroy = true;
    public static $createRevisionOnSave = true;

    #[Column(type: 'int', default:7)]
    private $ContextID;

    #[Column(type: 'enum', values: [Tag::class], default: Tag::class)]
    private $ContextClass;

    #[Column(type: 'clob', notnull:true)]
    private $DNA;

    #[Column(type: 'string', required: true, notnull:true)]
    private $Name;

    #[Column(type: 'string', blankisnull: true, notnull:false)]
    private $Handle;

    #[Column(type: 'boolean', default: true)]
    private $isAlive;

    #[Column(type: 'password')]
    private $DNAHash;

    #[Column(type: 'timestamp', notnull: false)]
    private $StatusCheckedLast;

    #[Column(type: 'serialized')]
    private $SerializedData;

    #[Column(type: 'set', values: [
        "red",
        "pink",
        "purple",
        "deep-purple",
        "indigo",
        "blue",
        "light-blue",
        "cyan",
        "teal",
        "green",
        "light-green",
        "lime",
        "yellow",
        "amber",
        "orange",
        "deep-orange",
        "brown",
        "grey",
        "blue-grey",
    ])]
    private $Colors;

    #[Column(type: 'list', delimiter: '|')]
    private $EyeColors;

    #[Column(type: 'float')]
    private $Height;

    #[Column(type: 'int', notnull: false)]
    private $LongestFlightTime;

    #[Column(type: 'uint')]
    private $HighestRecordedAltitude;

    #[Column(type: 'integer', notnull: true)]
    private $ObservationCount;

    #[Column(type: 'date')]
    private $DateOfBirth;

    #[Column(type: 'decimal', notnull: false, precision: 5, scale: 2)]
    private $Weight;

    public static $indexes = [
        'Handle' => [
            'fields' => [
                'Handle',
            ],
            'unique' => true,
        ],
        'DateOfBirth' => [
            'fields' => [
                'DateOfBirth',
            ],
        ],
    ];

    /* expose protected attributes for unit testing */
    public static function getProtected($field)
    {
        return static::$$field;
    }

    public static function getRecordClass($record)
    {
        return static::_getRecordClass($record);
    }

    public function getRecord()
    {
        return $this->_record;
    }

    /*
     *  manifests a canary
     */
    public static function mock(): array
    {
        $reflection = new ReflectionClass(static::class);
        while (!$reflection->hasProperty('Colors') && ($reflection = $reflection->getParentClass())) {
        }

        $attributes = $reflection->getProperty('Colors')->getAttributes();

        foreach ($attributes as $attribute) {
            if ($attribute->getName() === Column::class) {
                $allowedColors = $attribute->getArguments()['values'];
            }
        }

        $colors = array_rand($allowedColors, mt_rand(1, 5));
        if (is_array($colors)) {
            foreach ($colors as &$color) {
                $color = $allowedColors[$color];
            }
        } else {
            $colors = [$allowedColors[$colors]];
        }

        $EyeColors = [$allowedColors[array_rand($allowedColors)],$allowedColors[array_rand($allowedColors)]];

        $output = [
            'DNA' => static::randomDNA(),
            'Name' => Data::$names[array_rand(Data::$names)],
            'isAlive' => mt_rand(1, 100)<=90 ? true : false,
            'StatusCheckedLast' => time(),
            'Colors' => $colors,
            'EyeColors' => $EyeColors,
            'Height' => (mt_rand(30, 100)/100)*14,
            'LongestFlightTime' => mt_rand(1000, 5000),
            'HighestRecordedAltitude' => mt_rand(70, 2000),
            'ObservationCount' => 1,
            'DateOfBirth' => mt_rand(time()-315360000, time()),
            'Weight' => (mt_rand(10, 200) / 10),
        ];

        $output['Handle'] = static::getUniqueHandle($output['Name']);

        $output['DNAHash'] = md5($output['DNA']);

        $output['SerializedData'] = serialize($output);

        return $output;
    }

    public static function randomDNA()
    {
        $raw = '';
        $letters = ['A','T','G','C'];
        while (strlen($raw)<1000) {
            $raw .= $letters[mt_rand(0, 3)];
        }
        return $raw;
    }
}
