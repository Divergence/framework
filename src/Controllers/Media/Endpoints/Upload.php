<?php

namespace Divergence\Controllers\Media\Endpoints;

use Divergence\Controllers\Media\AbstractMediaEndpoint;
use Divergence\Controllers\MediaRequestHandler;
use Divergence\Models\ActiveRecord;
use Divergence\Models\Media\Media;
use Exception;
use Psr\Http\Message\ResponseInterface;

class Upload extends AbstractMediaEndpoint
{
    protected MediaRequestHandler $handler;

    public function __construct(MediaRequestHandler $handler)
    {
        $this->handler = $handler;
    }

    public function handle(...$arguments): ResponseInterface
    {
        [$options] = array_pad($arguments, 1, []);

        $this->handler->checkUploadAccess();
        $uploadResult = $this->handleUploadRequest($options);

        if ($uploadResult instanceof ResponseInterface) {
            return $uploadResult;
        }

        $uploadResult = $this->attachContext($uploadResult);

        if ($uploadResult instanceof ResponseInterface) {
            return $uploadResult;
        }

        return $this->respondUploadComplete($uploadResult);
    }

    protected function handleUploadRequest(array $options)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            return $this->handlePostUpload($options);
        }

        if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
            return $this->handlePutUpload($options);
        }

        return $this->handler->respond('upload');
    }

    protected function respondUploadComplete($uploadResult): ResponseInterface
    {
        return $this->handler->respond('uploadComplete', [
            'success' => (bool) $uploadResult,
            'data' => $uploadResult,
        ]);
    }

    protected function handlePostUpload(array $options)
    {
        $options = $this->preparePostOptions($options);
        $uploadError = $this->validatePostUpload($options);

        if ($uploadError !== null) {
            return $uploadError;
        }

        $options = $this->populatePostCaption($options);

        try {
            return Media::createFromUpload($_FILES[$options['fieldName']]['tmp_name'], $options);
        } catch (Exception $e) {
            return $this->handler->throwUploadError($e->getMessage());
        }
    }

    protected function preparePostOptions(array $options): array
    {
        return array_merge([
            'fieldName' => $this->handler->uploadFileFieldName,
        ], $options);
    }

    protected function validatePostUpload(array $options): ?ResponseInterface
    {
        if (empty($_FILES[$options['fieldName']])) {
            return $this->handler->throwUploadError('You did not select a file to upload');
        }

        if ($_FILES[$options['fieldName']]['error'] == UPLOAD_ERR_OK) {
            return null;
        }

        switch ($_FILES[$options['fieldName']]['error']) {
            case UPLOAD_ERR_NO_FILE:
                return $this->handler->throwUploadError('You did not select a file to upload');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return $this->handler->throwUploadError('Your file exceeds the maximum upload size. Please try again with a smaller file.');
            case UPLOAD_ERR_PARTIAL:
                return $this->handler->throwUploadError('Your file was only partially uploaded, please try again.');
            default:
                return $this->handler->throwUploadError('There was an unknown problem while processing your upload, please try again.');
        }
    }

    protected function populatePostCaption(array $options): array
    {
        if (isset($options['Caption'])) {
            return $options;
        }

        if (!empty($_REQUEST['Caption'])) {
            $options['Caption'] = $_REQUEST['Caption'];
            return $options;
        }

        $options['Caption'] = preg_replace('/\.[^.]+$/', '', $_FILES[$options['fieldName']]['name']);

        return $options;
    }

    protected function handlePutUpload(array $options)
    {
        $tmp = $this->copyPutStreamToTemporaryFile();

        try {
            return Media::createFromFile($tmp, $options);
        } catch (Exception $e) {
            return $this->handler->throwUploadError('The file you uploaded is not of a supported media format');
        }
    }

    protected function copyPutStreamToTemporaryFile(): string
    {
        $put = fopen($this->handler::$inputStream, 'r');
        $tmp = tempnam('/tmp', 'dvr');
        $fp = fopen($tmp, 'w');

        while ($data = fread($put, 1024)) {
            fwrite($fp, $data);
        }

        fclose($fp);
        fclose($put);

        return $tmp;
    }

    protected function attachContext($Media)
    {
        if (!$Media || !$this->hasContextRequest()) {
            return $Media;
        }

        $contextError = $this->validateContextRequest();

        if ($contextError !== null) {
            return $contextError;
        }

        if (!$Media->Context = $_REQUEST['ContextClass']::getByID($_REQUEST['ContextID'])) {
            return $this->handler->throwUploadError('Context class not found');
        }

        $Media->save();

        return $Media;
    }

    protected function hasContextRequest(): bool
    {
        return !empty($_REQUEST['ContextClass']) && !empty($_REQUEST['ContextID']);
    }

    protected function validateContextRequest(): ?ResponseInterface
    {
        if (
            !is_subclass_of($_REQUEST['ContextClass'], ActiveRecord::class)
            || !in_array($_REQUEST['ContextClass']::getStaticRootClass(), Media::$fields['ContextClass']['values'])
            || !is_numeric($_REQUEST['ContextID'])
        ) {
            return $this->handler->throwUploadError('Context is invalid');
        }

        return null;
    }
}
