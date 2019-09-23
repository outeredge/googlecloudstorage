<?php
/**
 * GoogleCloudStorageOption.php
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License, which
 * is bundled with this package in the file LICENSE.txt.
 *
 * It is also available on the Internet at the following URL:
 * https://docs.auroraextensions.com/magento/extensions/2.x/googlecloudstorage/LICENSE.txt
 *
 * @package       AuroraExtensions_GoogleCloudStorage
 * @copyright     Copyright (C) 2019 Aurora Extensions <support@auroraextensions.com>
 * @license       MIT License
 */
declare(strict_types=1);

namespace AuroraExtensions\GoogleCloudStorage\Plugin\MediaStorage\Config;

use AuroraExtensions\GoogleCloudStorage\Model\File\Storage;
use Magento\Framework\Option\ArrayInterface;

class GoogleCloudStorageOption
{
    /**
     * @param ArrayInterface $subject
     * @param array $result
     * @return array
     */
    public function afterToOptionArray(
        ArrayInterface $subject,
        $result
    ) {
        $result[] = [
            'value' => Storage::STORAGE_MEDIA_GCS,
            'label' => __('Google Cloud Storage'),
        ];

        return $result;
    }
}