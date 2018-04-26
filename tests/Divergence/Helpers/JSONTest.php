<?php
namespace Divergence\Tests\Helpers;

use Divergence\Helpers\JSON;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;

use PHPUnit\Framework\TestCase;

class JSONtest extends TestCase
{
    /**
     * @covers Divergence\Helpers\JSON::getRequestData
     */
    public function testGetRequestData()
    {
        $json = '{"array":[1,2,3],"boolean":true,"null":null,"number":123,"object":{"a":"b","c":"d","e":"f"},"string":"Hello World"}';
        vfsStream::setup('input', null, ['data' => $json]);
        JSON::$inputStream = 'vfs://input/data';

        $x = json_decode($json,true);
        $A = JSON::getRequestData();
        $B = JSON::getRequestData('object');

        $this->assertEquals($A, $x);
        $this->assertEquals($B, $x['object']);
    }
}