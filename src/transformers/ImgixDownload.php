<?php
/**
 * Imgix download transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2023 André Elvan
 */

namespace spacecatninja\imgixdownloadtransformer\transformers;

use Craft;
use craft\base\Component;
use craft\elements\Asset;

use craft\helpers\FileHelper;
use spacecatninja\imagerx\helpers\ImagerHelpers;
use spacecatninja\imagerx\ImagerX;
use spacecatninja\imagerx\models\ImgixSettings;
use spacecatninja\imagerx\models\ImgixTransformedImageModel;
use spacecatninja\imagerx\models\LocalSourceImageModel;
use spacecatninja\imagerx\models\LocalTargetImageModel;
use spacecatninja\imagerx\models\LocalTransformedImageModel;
use spacecatninja\imagerx\transformers\ImgixTransformer;
use spacecatninja\imagerx\services\ImagerService;
use spacecatninja\imagerx\transformers\TransformerInterface;
use spacecatninja\imagerx\exceptions\ImagerException;

class ImgixDownload extends Component implements TransformerInterface
{

    /**
     * @param Asset|string $image
     * @param array        $transforms
     *
     * @return array|null
     *
     * @throws ImagerException
     */
    public function transform(Asset|string $image, array $transforms): ?array
    {
        $originalTransforms = $transforms;
        $transforms = $this->ensureAutoFormatNotSet($transforms);
        
        $imgixTransformer = new ImgixTransformer();
        $imgixTransformedImages = $imgixTransformer->transform($image, $transforms);

        $transformedImages = [];
        $i = 0;

        foreach ($imgixTransformedImages as $imgixTransformedImage) {
            /* 
                Note to self: We use the original, normalized transforms from ImagerService
                when proceeding to store the images. This is to make the resulting filenames
                compatible with the native craft transformer, making it possible to rip out
                Imgix and keep the transforms   
            */ 
            $transformedImages[] = $this->processTransformedImage($image, $imgixTransformedImage, $originalTransforms[$i]);
            $i++;
        }
        
        ImagerX::getInstance()->imagerx->postProcessTransformedImages($transformedImages);

        return $transformedImages;
    }

    /**
     * @param array $transforms
     *
     * @return array
     * @throws ImagerException
     */
    private function ensureAutoFormatNotSet(array $transforms): array
    {
        $config = ImagerService::getConfig();
        $r = [];

        foreach ($transforms as $transform) {
            $profile = $config->getSetting('imgixProfile', $transform);
            $imgixConfigArr = $config->getSetting('imgixConfig', $transform);

            if (!isset($imgixConfigArr[$profile])) {
                $msg = 'Imgix profile “'.$profile.'” does not exist.';
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }

            $auto = null;

            $imgixConfig = new ImgixSettings($imgixConfigArr[$profile]);
            $transformerParams = $transform['transformerParams'] ?? [];

            if (!empty($imgixConfig->defaultParams) && isset($imgixConfig->defaultParams['auto'])) {
                $auto = $imgixConfig->defaultParams['auto'];
            }

            if (isset($transformerParams['auto'])) {
                $auto = $transformerParams['auto'];
            }

            if (is_string($auto)) {
                $auto = trim(str_replace(['format', ' ', ',,'], ['', '', ','], $auto), ', ');
                $transformerParams['auto'] = $auto;
                $transform['transformerParams'] = $transformerParams;
            }

            $r[] = $transform;
        }

        return $r;
    }

    /**
     * @param Asset|string               $image
     * @param ImgixTransformedImageModel $imgixTransformedImage
     * @param array                      $transform
     *
     * @return LocalTransformedImageModel|null
     * @throws ImagerException
     */
    private function processTransformedImage(Asset|string $image, ImgixTransformedImageModel $imgixTransformedImage, array $transform): ?LocalTransformedImageModel
    {
        $sourceModel = new LocalSourceImageModel($image);
        $targetModel = new LocalTargetImageModel($sourceModel, $transform);
        $saveResult = true;

        if (ImagerHelpers::shouldCreateTransform($targetModel, $transform)) {
            $this->ensureTargetPath($targetModel);

            // download and store imgix image to the correct path.
            $saveResult = $this->saveImgixUrlToLocalTarget($imgixTransformedImage->url, $targetModel);
            $targetModel->isNew = true;
        } 

        return $saveResult ? new LocalTransformedImageModel($targetModel, $sourceModel, $transform) : null;
    }

    /**
     * @param LocalTargetImageModel $target
     *
     * @return void
     * @throws ImagerException
     */
    private function ensureTargetPath(LocalTargetImageModel $target): void
    {
        if (!realpath($target->path)) {
            try {
                FileHelper::createDirectory($target->path);
            } catch (\Throwable) {
                // ignore for now, trying to create
            }

            if (!realpath($target->path)) {
                $msg = Craft::t('imager-x', 'Target folder “{targetPath}” does not exist and could not be created', ['targetPath' => $targetModel->path]);
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }
        }

        try {
            $targetPathIsWriteable = FileHelper::isWritable($target->path);
        } catch (\Throwable) {
            $targetPathIsWriteable = false;
        }

        if ($target->path && !$targetPathIsWriteable) {
            $msg = Craft::t('imager-x', 'Target folder “{targetPath}” is not writeable', ['targetPath' => $target->path]);
            Craft::error($msg, __METHOD__);
            throw new ImagerException($msg);
        }
    }

    /**
     * @param string                $url
     * @param LocalTargetImageModel $target
     *
     * @return bool
     * @throws ImagerException
     */
    private function saveImgixUrlToLocalTarget(string $url, LocalTargetImageModel $target): bool
    {
        $config = ImagerService::getConfig();

        $targetFilePath = $target->getFilePath();
        $targetFileTempPath = $targetFilePath.'.tmp';

        if (\function_exists('curl_init')) {
            $ch = curl_init($url);
            $fp = fopen($targetFileTempPath, 'wb');

            $defaultOptions = [
                CURLOPT_FILE => $fp,
                CURLOPT_HEADER => 0,
                CURLOPT_FOLLOWLOCATION => 1,
                CURLOPT_TIMEOUT => 30,
            ];

            // merge default options with config setting, config overrides default.
            $options = $config->getSetting('curlOptions') + $defaultOptions;

            curl_setopt_array($ch, $options);
            curl_exec($ch);
            $curlErrorNo = curl_errno($ch);
            $curlError = curl_error($ch);
            $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);

            if ($curlErrorNo !== 0) {
                @unlink($targetFileTempPath);
                $msg = Craft::t('imager-x', 'cURL error “{curlErrorNo}” encountered while attempting to download “{imageUrl}”. The error was: “{curlError}”', ['imageUrl' => $url, 'curlErrorNo' => $curlErrorNo, 'curlError' => $curlError]);
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }

            if ($httpStatus !== 200 && !($httpStatus === 404 && strrpos(mime_content_type($targetFileTempPath), 'image') !== false)) {
                // remote server returned a 404, but the contents was a valid image file
                @unlink($targetFileTempPath);
                $msg = Craft::t('imager-x', 'HTTP status “{httpStatus}” encountered while attempting to download “{imageUrl}”', ['imageUrl' => $url, 'httpStatus' => $httpStatus]);
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }
        } elseif (ini_get('allow_url_fopen')) {
            if (!@copy($url, $targetFileTempPath)) {
                @unlink($targetFileTempPath);
                $errors = error_get_last();
                $msg = Craft::t('imager-x', 'Error “{errorType}” encountered while attempting to download “{imageUrl}”: {errorMessage}', ['imageUrl' => $url, 'errorType' => $errors['type'], 'errorMessage' => $errors['message']]);
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }
        } else {
            $msg = Craft::t('imager-x', 'Looks like allow_url_fopen is off and cURL is not enabled. To download external files, one of these methods has to be enabled.');
            Craft::error($msg, __METHOD__);
            throw new ImagerException($msg);
        }

        if (file_exists($targetFileTempPath)) {
            copy($targetFileTempPath, $targetFilePath);
            @unlink($targetFileTempPath);

            return true;
        }

        return false;
    }
}
