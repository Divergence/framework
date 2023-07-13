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

    // support subclassing
    public static $rootClass = __CLASS__;
    public static $defaultClass = __CLASS__;
    public static $subClasses = [__CLASS__];


    // ActiveRecord configuration
    public static $tableName = 'canaries';
    public static $singularNoun = 'canary';
    public static $pluralNoun = 'canaries';

    // versioning
    public static $historyTable = 'canaries_history';
    public static $createRevisionOnDestroy = true;
    public static $createRevisionOnSave = true;

    #[Column(type: 'int', default:7)]
    protected $ContextID;

    #[Column(type: 'enum', values: [Tag::class], default: Tag::class)]
    protected $ContextClass;

    #[Column(type: 'clob', notnull:true)]
    protected $DNA;

    #[Column(type: 'string', required: true, notnull:true)]
    protected $Name;

    #[Column(type: 'string', blankisnull: true, notnull:false)]
    protected $Handle; 

    #[Column(type: 'boolean', default: true)]
    protected $isAlive;

    #[Column(type: 'password')]
    protected $DNAHash;

    #[Column(type: 'timestamp', notnull: false)]
    protected $StatusCheckedLast;

    #[Column(type: 'serialized')]
    protected $SerializedData;

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
    protected $Colors;

    #[Column(type: 'list', delimiter: '|')]
    protected $EyeColors;

    #[Column(type: 'float')]
    protected $Height;

    #[Column(type: 'int', notnull: false)]
    protected $LongestFlightTime;

    #[Column(type: 'uint')]
    protected $HighestRecordedAltitude;

    #[Column(type: 'integer', notnull: true)]
    protected $ObservationCount;

    #[Column(type: 'date')]
    protected $DateOfBirth;

    #[Column(type: 'decimal', notnull: false, precision: 5, scale: 2)]
    protected $Weight;

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
    public static function mock():array
    {
        $properties = (new ReflectionClass(static::class))->getProperties();
        if(!empty($properties)) {
            foreach ($properties as $property) {
                if ($property->getName() === 'Colors') {
                    $attributes = $property->getAttributes();
                    foreach($attributes as $attribute) {
                        if ($attribute->getName()===Column::class) {
                            $allowedColors = $attribute->getArguments()['values'];
                        }
                    }
                }
            }
        }
        $colors = array_rand($allowedColors, mt_rand(1, 5));
        if (is_array($colors)) {
            foreach ($colors as &$color) {
                $color = $allowedColors[$color];
            }
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
