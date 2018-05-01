<?php
namespace Divergence\Tests\MockSite\Models;

use Divergence\Models\Relations;
use Divergence\Models\Versioning;

use Divergence\Tests\MockSite\Mock\Data;

/*
 *  The purpose of this Model is to provide an example of every field type
 *  hopefully in every possible configuration.
 *
 */
class Canary extends \Divergence\Models\Model
{
    use Versioning;
    //use \Divergence\Models\Relations;
    
    // support subclassing
    public static $rootClass = __CLASS__;
    public static $defaultClass = __CLASS__;
    public static $subClasses = [__CLASS__];


    // ActiveRecord configuration
    public static $tableName = 'canaries';
    public static $singularNoun = 'canary';
    public static $pluralNoun = 'canaries';
    
    // versioning
    static public $historyTable = 'canaries_history';
    static public $createRevisionOnDestroy = true;
    static public $createRevisionOnSave = true;

    public static $fields = [
        'ContextID' => [
            'type' => 'int',
            'default' => 7,
        ],
        'ContextClass' => [
            'type' => 'enum',
            'values' => [Tag::class],
            'default' => Tag::class,
        ],
        'DNA' => [
            'type' => 'clob',
            'notnull' => true,
        ],
        'Name' => [
            'type' => 'string',
            'required' => true,
            'notnull' => true,
        ],
        'Handle' => [
            'type' => 'string',
            'blankisnull' => true,
        ],
        'isAlive' => [
            'type' => 'boolean',
            'default' => true,
        ],
        'DNAHash' => [
            'type' => 'password',
        ],
        'StatusCheckedLast' => [
            'type' => 'timestamp',
            'notnull' => false,
        ],
        'SerializedData' => [
            'type' => 'serialized',
        ],
        'Colors' => [
            'type' => 'set',
            'values' => [
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
            ],
        ],
        'EyeColors' => [
            'type' => 'list',
            'delimiter' => '|',
        ],
        'Height' => [
            'type' => 'float',
        ],
        'LongestFlightTime' => [
            'type' => 'int',
        ],
        'HighestRecordedAltitude' => [
            'type' => 'uint',
        ],
        'ObservationCount' => [
            'type' => 'integer',
        ],
        'DateOfBirth' => [
            'type' => 'date',
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

    /*
     *  @incantation        (AH-viss)
     *  @url                http://harrypotter.wikia.com/wiki/Bird-Conjuring_Charm
     *  @desc_for_muggles   Bird-Conjuring Charm
     */
    public static function avis()
    {
        $allowedColors = static::$fields['Colors']['values'];

        $colors = array_rand($allowedColors, mt_rand(1, 5));
        foreach ($colors as &$color) {
            $color = $allowedColors[$color];
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
        ];

        $output['Handle'] = static::getUniqueHandle($output['Name']);

        $output['DNAHash'] = password_hash($output['DNA'], PASSWORD_DEFAULT);

        $output['SerializedData'] = serialize($output);

        return $output;
    }

    public static function randomDNA()
    {
        $raw = '';
        while (strlen($raw)<1000) {
            $raw .= str_replace([0,1,2,3], ['A','T','G','C'], mt_rand(0, 3));
        }
        return $raw;
    }
}
