<?php
/**
 * Imgix download transformer for Imager X
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2023 André Elvan
 */
namespace spacecatninja\imgixdownloadtransformer;

use craft\base\Model;
use craft\base\Plugin;

use spacecatninja\imgixdownloadtransformer\models\Settings;
use spacecatninja\imgixdownloadtransformer\transformers\ImgixDownload;

use yii\base\Event;


class ImgixDownloadTransformer extends Plugin
{
    public function init(): void
    {
        parent::init();

        // Register transformer with Imager
        Event::on(\spacecatninja\imagerx\ImagerX::class,
            \spacecatninja\imagerx\ImagerX::EVENT_REGISTER_TRANSFORMERS,
            static function (\spacecatninja\imagerx\events\RegisterTransformersEvent $event) {
                $event->transformers['imgixdownload'] = ImgixDownload::class;
            }
        );
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model 
    {
        return new Settings();
    }

}
