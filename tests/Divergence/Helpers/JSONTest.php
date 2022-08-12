<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests\Helpers;

use Divergence\Helpers\JSON;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

use org\bovigo\vfs\vfsStreamWrapper;

class nothing
{
}

class mock
{
    private $data = ['a'=>1,'b'=>2];
    public function __get($field)
    {
        switch ($field) {
            case 'data':
                return $this->data;
        }
    }
}

class mockWithData extends mock
{
    private $data = ['a'=>1,'b'=>2,'c'=>3];
    public function getData()
    {
        return $this->data;
    }
}

class JSONtest extends TestCase
{
    /**
     *
     */
    public function testGetRequestData()
    {
        $json = '{"array":[1,2,3],"boolean":true,"null":null,"number":123,"object":{"a":"b","c":"d","e":"f"},"string":"Hello World"}';
        vfsStream::setup('input', null, ['data' => $json]);
        JSON::$inputStream = 'vfs://input/data';

        $x = json_decode($json, true);
        $A = JSON::getRequestData();
        $B = JSON::getRequestData('object');

        $this->assertEquals($A, $x);
        $this->assertEquals($B, $x['object']);

        vfsStream::setup('input', null, ['data' => '']);
        $this->assertEquals(JSON::getRequestData(), false);
    }

    /**
     *
     */
    public function testRespond()
    {
        $json = '{"array":[1,2,3],"boolean":true,"null":null,"number":123,"object":{"a":"b","c":"d","e":"f"},"string":"Hello World"}';
        $obj = json_decode($json, true);
        $this->expectOutputString($json);
        JSON::respond($obj);
    }

    /**
     *
     */
    public function testError()
    {
        $error = 'Something went wrong';
        $obj = json_encode(['success'=>false,'message'=>$error], true);
        $this->expectOutputString($obj);
        JSON::error($error);
    }

    /**
     *
     */
    public function testTranslateObjects()
    {
        $Objects = [new nothing(),'mocks'=>[new mock(), new mock(), new mock()], 'mocksWithData' => [new mockWithData(),new mockWithData()]];
        $Expected = [new nothing(), 'mocks'=>[['a'=>1,'b'=>2],['a'=>1,'b'=>2],['a'=>1,'b'=>2]], 'mocksWithData'=>[['a'=>1,'b'=>2,'c'=>3],['a'=>1,'b'=>2,'c'=>3]]];
        $a = JSON::translateObjects($Objects);
        $b = JSON::translateObjects($Expected);
        $this->assertEquals($a, $b);
    }

    /**
     *
     */
    public function testTranslateAndRespond()
    {
        $Objects = [new nothing(),'mocks'=>[new mock(), new mock(), new mock()], 'mocksWithData' => [new mockWithData(),new mockWithData()]];
        $Expected = [new nothing(), 'mocks'=>[['a'=>1,'b'=>2],['a'=>1,'b'=>2],['a'=>1,'b'=>2]], 'mocksWithData'=>[['a'=>1,'b'=>2,'c'=>3],['a'=>1,'b'=>2,'c'=>3]]];

        $b = json_encode($Expected, true);
        $this->expectOutputString($b);
        JSON::translateAndRespond($Objects);
    }
}
