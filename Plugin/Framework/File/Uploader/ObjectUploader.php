<?php
/**
 * ObjectUploader.php
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT license, which
 * is bundled with this package in the file LICENSE.txt.
 *
 * It is also available on the Internet at the following URL:
 * https://docs.auroraextensions.com/magento/extensions/2.x/googlecloudstorage/LICENSE.txt
 *
 * @package     AuroraExtensions\GoogleCloudStorage\Plugin\Framework\File\Uploader
 * @copyright   Copyright (C) 2021 Aurora Extensions <support@auroraextensions.com>
 * @license     MIT
 */
declare(strict_types=1);

namespace AuroraExtensions\GoogleCloudStorage\Plugin\Framework\File\Uploader;

use Exception;
use AuroraExtensions\GoogleCloudStorage\{
    Api\StorageObjectManagementInterface,
    Component\ModuleConfigTrait,
    Component\StorageAdapterTrait,
    Model\System\ModuleConfig,
    Model\Utils\PathUtils
};
use Magento\Framework\{
    Exception\FileSystemException,
    File\Uploader,
    Filesystem\Driver\File as FileDriver
};
use Magento\MediaStorage\Helper\File\Storage\Database as StorageHelper;
use Psr\Log\LoggerInterface;

class ObjectUploader
{
    /**
     * @var ModuleConfig $moduleConfig
     * @method ModuleConfig getConfig()
     * ---
     * @var StorageObjectManagementInterface $storageAdapter
     * @method StorageObjectManagementInterface getStorage()
     */
    use ModuleConfigTrait, StorageAdapterTrait;

    /** @var FileDriver $fileDriver */
    private $fileDriver;

    /** @var LoggerInterface $logger */
    private $logger;

    /** @var PathUtils $pathUtils */
    private $pathUtils;

    /** @var StorageHelper $storageHelper */
    private $storageHelper;

    /**
     * @param FileDriver $fileDriver
     * @param LoggerInterface $logger
     * @param ModuleConfig $moduleConfig
     * @param PathUtils $pathUtils
     * @param StorageObjectManagementInterface $storageAdapter
     * @param StorageHelper $storageHelper
     * @return void
     */
    public function __construct(
        FileDriver $fileDriver,
        LoggerInterface $logger,
        ModuleConfig $moduleConfig,
        PathUtils $pathUtils,
        StorageObjectManagementInterface $storageAdapter,
        StorageHelper $storageHelper
    ) {
        $this->fileDriver = $fileDriver;
        $this->logger = $logger;
        $this->moduleConfig = $moduleConfig;
        $this->pathUtils = $pathUtils;
        $this->storageAdapter = $storageAdapter;
        $this->storageHelper = $storageHelper;
    }

    /**
     * @param Uploader $subject
     * @param array|bool $result
     * @param string $destinationFolder
     * @param string|null $newFileName
     * @return array|bool
     */
    public function afterSave(
        Uploader $subject,
        $result,
        $destinationFolder,
        $newFileName = null
    ) {
        if (!$this->storageAdapter->isEnabled()) {
            return $result;
        }

        if (!empty($result)) {
            /** @var string $basePath */
            $basePath = (string)($result['path'] ?? '');

            /** @var string $baseName */
            $baseName = (string)($result['file'] ?? '');

            if (!empty($basePath) && !empty($baseName)) {
                /** @var string $realPath */
                $realPath = $this->pathUtils->build($basePath, $baseName);
                $this->upload($realPath);
            }
        }

        return $result;
    }

    /**
     * @param string $path
     * @return void
     */
    private function upload(string $path): void
    {
        /** @var string $filePath */
        $filePath = $this->storageHelper->getMediaRelativePath($path);

        /** @var string $objectPath */
        $objectPath = $this->getStorage()->getObjectPath($filePath);

        /** @var string $aclPolicy */
        $aclPolicy = $this->getStorage()->getObjectAclPolicy();

        /** @var array $options */
        $options = [
            'name' => $objectPath,
            'predefinedAcl' => $aclPolicy,
        ];

        try {
            /** @var resource $handle */
            $handle = $this->fileDriver->fileOpen($path, 'r');
            $this->getStorage()->uploadObject($handle, $options);
        } catch (FileSystemException | Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }
}
