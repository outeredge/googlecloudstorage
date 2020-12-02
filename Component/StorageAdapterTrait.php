<?php
/**
 * StorageAdapterTrait.php
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT license, which
 * is bundled with this package in the file LICENSE.txt.
 *
 * It is also available on the Internet at the following URL:
 * https://docs.auroraextensions.com/magento/extensions/2.x/googlecloudstorage/LICENSE.txt
 *
 * @package       AuroraExtensions\GoogleCloudStorage\Component
 * @copyright     Copyright (C) 2019 Aurora Extensions <support@auroraextensions.com>
 * @license       MIT
 */
declare(strict_types=1);

namespace AuroraExtensions\GoogleCloudStorage\Component;

use AuroraExtensions\GoogleCloudStorage\Api\StorageObjectManagementInterface;

trait StorageAdapterTrait
{
    /** @var StorageObjectManagementInterface $storageAdapter */
    private $storageAdapter;

    /**
     * @return StorageObjectManagementInterface
     */
    public function getStorage(): StorageObjectManagementInterface
    {
        return $this->storageAdapter;
    }
}
