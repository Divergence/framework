<?php
namespace Divergence\Tests\Controllers;

use Divergence\App;
use Divergence\Routing\Path;
use PHPUnit\Framework\TestCase;
use Divergence\Models\Media\Media;
use Divergence\Responders\Emitter;
use GuzzleHttp\Psr7\ServerRequest;
use Divergence\Controllers\MediaRequestHandler;

class MediaRequestHandlerTest extends TestCase
{
    public function testEmptyUpload()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        App::$App->Path = new Path('/json/upload');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->expectOutputString('{"success":false,"failed":{"errors":"You did not select a file to upload"}}');
        (new Emitter($response))->emit();
    }

    public function testUploadErrNoFile()
    {
        $_FILES['test'] = [
            'error' => UPLOAD_ERR_NO_FILE,
            'tmp_name' => '/tmp/uploadedFile8190',
            'size' => 4096,
            'name' => 'example.jpg',
            'type' => ''
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        App::$App->Path = new Path('/json/upload');
        $controller = new MediaRequestHandler();
        $controller->uploadFileFieldName = 'test';
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->expectOutputString('{"success":false,"failed":{"errors":"You did not select a file to upload"}}');
        (new Emitter($response))->emit();
    }

    public function testUploadErrIniSize()
    {
        $_FILES['test'] = [
            'error' => UPLOAD_ERR_INI_SIZE,
            'tmp_name' => '/tmp/uploadedFile8190',
            'size' => 4096,
            'name' => 'example.jpg',
            'type' => ''
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        App::$App->Path = new Path('/json/upload');
        $controller = new MediaRequestHandler();
        $controller->uploadFileFieldName = 'test';
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->expectOutputString('{"success":false,"failed":{"errors":"Your file exceeds the maximum upload size. Please try again with a smaller file."}}');
        (new Emitter($response))->emit();
    }

    public function testUploadErrPartial()
    {
        $_FILES['test'] = [
            'error' => UPLOAD_ERR_PARTIAL,
            'tmp_name' => '/tmp/uploadedFile8190',
            'size' => 4096,
            'name' => 'example.jpg',
            'type' => ''
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        App::$App->Path = new Path('/json/upload');
        $controller = new MediaRequestHandler();
        $controller->uploadFileFieldName = 'test';
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->expectOutputString('{"success":false,"failed":{"errors":"Your file was only partially uploaded, please try again."}}');
        (new Emitter($response))->emit();
    }

    public function testUploadErrUnknown()
    {
        $_FILES['test'] = [
            'error' => UPLOAD_ERR_EXTENSION,
            'tmp_name' => '/tmp/uploadedFile8190',
            'size' => 4096,
            'name' => 'example.jpg',
            'type' => ''
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        App::$App->Path = new Path('/json/upload');
        $controller = new MediaRequestHandler();
        $controller->uploadFileFieldName = 'test';
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->expectOutputString('{"success":false,"failed":{"errors":"There was an unknown problem while processing your upload, please try again."}}');
        (new Emitter($response))->emit();
    }

    /*public function testUploadPNG()
    {
        $PNG = realpath(App::$App->ApplicationPath . 'tests/assets/logo.png');
        //dump($PNG);
        $_FILES['test'] = [
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => $PNG,
            'size' => filesize($PNG),
            'name' => 'logo.png',
            'type' => ''
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        App::$App->Path = new Path('/json/upload');
        $controller = new MediaRequestHandler();
        $controller->uploadFileFieldName = 'test';
        $response = $controller->handle(ServerRequest::fromGlobals());
        //$this->expectOutputString('{"success":false,"failed":{"errors":"The file you uploaded is not of a supported media format"}}');
        (new Emitter($response))->emit();
    }*/

    public function testCreatePNGFromFile()
    {
        $tempName = tempnam('/tmp', 'testmedia').'.png';
        $PNG = realpath(App::$App->ApplicationPath . '/tests/assets/logo.png');
        copy($PNG, $tempName);
        $media = Media::createFromFile($tempName);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        App::$App->Path = new Path('/json/'.$media->ID);
        $controller = new MediaRequestHandler();
        $controller->uploadFileFieldName = 'test';
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->expectOutputString(json_encode(['success'=>true,'data'=>$media]));
        (new Emitter($response))->emit();
        unlink(realpath(App::$App->ApplicationPath.'/media/original/'.$media->ID.'.png'));
        unlink(realpath(App::$App->ApplicationPath.'/media/original/'));
        unlink(realpath(App::$App->ApplicationPath.'/media/'));
    }
}
