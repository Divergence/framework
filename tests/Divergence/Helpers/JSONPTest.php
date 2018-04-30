<?php
namespace Divergence\Tests\Helpers;

use Divergence\Helpers\JSONP;

use PHPUnit\Framework\TestCase;

class JSONPtest extends TestCase
{


    /**
     * @covers Divergence\Helpers\JSONP::respond
     */
    public function testRespond()
    {
        $json = '{"array":[1,2,3],"boolean":true,"null":null,"number":123,"object":{"a":"b","c":"d","e":"f"},"string":"Hello World"}';
        $obj = json_decode($json, true);
        $this->expectOutputString('var data = '.$json);
        JSONP::respond($obj);

        // this will throw "Class Malformed UTF-8 characters, possibly incorrectly encoded does not exist" from json_encode
        $this->expectException('Exception');
        $invalid = [utf8_decode("Düsseldorf"),
            "Washington",
            "Nairobi", ];
        JSONP::respond($invalid);
    }

    /**
     * @covers Divergence\Helpers\JSONP::translateAndRespond
     */
    public function testTranslateAndRespond()
    {
        $Objects = [new nothing(),'mocks'=>[new mock(), new mock(), new mock()], 'mocksWithData' => [new mockWithData(),new mockWithData()]];
        $Expected = [new nothing(), 'mocks'=>[['a'=>1,'b'=>2],['a'=>1,'b'=>2],['a'=>1,'b'=>2]], 'mocksWithData'=>[['a'=>1,'b'=>2,'c'=>3],['a'=>1,'b'=>2,'c'=>3]]];

        $b = 'var data = '.json_encode($Expected, true);
        $this->expectOutputString($b);
        JSONP::translateAndRespond($Objects);
    }
}
