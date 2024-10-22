<?php
/**
 * Bucket.php
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT license, which
 * is bundled with this package in the file LICENSE.txt.
 *
 * It is also available on the Internet at the following URL:
 * https://docs.auroraextensions.com/magento/extensions/2.x/googlecloudstorage/LICENSE.txt
 *
 * @package     AuroraExtensions\GoogleCloudStorage\Model\File\Storage
 * @copyright   Copyright (C) 2021 Aurora Extensions <support@auroraextensions.com>
 * @license     MIT
 */
declare(strict_types=1);

namespace AuroraExtensions\GoogleCloudStorage\Model\File\Storage;

use Exception;
use AuroraExtensions\GoogleCloudStorage\{
    Api\StorageObjectManagementInterface,
    Component\ModuleConfigTrait,
    Component\StorageAdapterTrait,
    Model\Cache\Type\GcsCache,
    Model\System\ModuleConfig,
    Exception\ExceptionFactory
};
use Google\Cloud\{
    Storage\StorageObject,
    Storage\ObjectIterator
};
use Magento\Framework\{
    App\CacheInterface,
    App\Filesystem\DirectoryList,
    Exception\LocalizedException,
    Filesystem,
    Filesystem\Driver\File as FileDriver,
    Model\AbstractModel,
    Phrase,
    Serialize\SerializerInterface,
    UrlInterface
};
use Magento\MediaStorage\Helper\File\Storage\Database as StorageHelper;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

use const DIRECTORY_SEPARATOR;
use function implode;
use function iterator_count;
use function ltrim;
use function rtrim;
use function strlen;
use function substr;
use function __;

class Bucket extends AbstractModel
{
    /**
     * @var ModuleConfig $moduleConfig
     * @var StorageObjectManagementInterface $storageAdapter
     * @method ModuleConfig getConfig()
     * @method StorageObjectManagementInterface getStorage()
     */
    use ModuleConfigTrait, StorageAdapterTrait;

    /** @var ExceptionFactory $exceptionFactory */
    private $exceptionFactory;

    /** @var FileDriver $fileDriver */
    private $fileDriver;

    /** @var Filesystem $filesystem */
    private $filesystem;

    /** @var LoggerInterface $logger */
    private $logger;

    /** @var ObjectIterator<StorageObject> $objects */
    private $objects;

    /** @var StorageHelper $storageHelper */
    private $storageHelper;

    /** @var CacheInterface $cache */
    private $cache;

    /** @var SerializerInterface $serializer */
    private $serializer;

    /** @var StoreManagerInterface $storeManager */
    private $storeManager;

    /**
     * @param ExceptionFactory $exceptionFactory
     * @param FileDriver $fileDriver
     * @param Filesystem $filesystem
     * @param LoggerInterface $logger
     * @param ModuleConfig $moduleConfig
     * @param StorageHelper $storageHelper
     * @param StorageObjectManagementInterface $storageAdapter
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @return void
     */
    public function __construct(
        ExceptionFactory $exceptionFactory,
        FileDriver $fileDriver,
        Filesystem $filesystem,
        LoggerInterface $logger,
        ModuleConfig $moduleConfig,
        StorageHelper $storageHelper,
        StorageObjectManagementInterface $storageAdapter,
        CacheInterface $cache,
        SerializerInterface $serializer,
        StoreManagerInterface $storeManager
    ) {
        $this->exceptionFactory = $exceptionFactory;
        $this->fileDriver = $fileDriver;
        $this->filesystem = $filesystem;
        $this->logger = $logger;
        $this->moduleConfig = $moduleConfig;
        $this->storageHelper = $storageHelper;
        $this->storageAdapter = $storageAdapter;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->storeManager = $storeManager;
    }

    /**
     * @return Phrase
     */
    public function getStorageName(): Phrase
    {
        return __('Google Cloud Storage');
    }

    /**
     * @return $this
     */
    public function init()
    {
        return $this;
    }

    /**
     * @return bool
     */
    public function hasObjects(): bool
    {
        return ($this->objects !== null && iterator_count($this->objects) > 0);
    }

    /**
     * @return ObjectIterator|null
     */
    public function getObjects(): ?ObjectIterator
    {
        return $this->objects;
    }

    /**
     * @param ObjectIterator|null $objects
     * @return $this
     */
    public function setObjects(?ObjectIterator $objects)
    {
        $this->objects = $objects;
        return $this;
    }

    /**
     * Get the object from GCS (runs in a background process)
     *
     * @param string $relativePath
     * @return $this
     */
    public function loadByFilename(string $relativePath)
    {
        $storeCode = $this->getStoreCode();

        if (stristr($_SERVER['SCRIPT_NAME'], 'get.php')) {
            // If the request is already async, get object immediately
            $this->getStorage()->getObject($relativePath, $storeCode);
        } else {
            // Otherwise make a CURL request to get.php so it runs "in the background",
            // ultimately calling the getObject() above (ideally we would have used a
            // message queue, but... we don't run crons in dev)
            $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            $curlUrl  = $mediaUrl . $relativePath . '?imgstore=' . $storeCode;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $curlUrl);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1);
            curl_exec($ch);
            curl_close($ch);
        }

        return $this;
    }

    protected function getStoreCode()
    {
        $storeCode = $_GET['imgstore'] ?? $this->storeManager->getStore()->getCode();
        if ($storeCode == 'admin' && stristr($_SERVER['REQUEST_URI'], '_admin')) {
            $storeCode = str_replace('_admin', '', explode('/', ltrim($_SERVER['REQUEST_URI'], '/'))[0]);
        }
        return $storeCode;
    }

    /**
     * @return $this
     */
    public function clear()
    {
        $this->getStorage()->deleteAllObjects();
        return $this;
    }

    /**
     * @param int $offset
     * @param int $count
     * @return bool
     * @see \Magento\MediaStorage\Model\File\Storage\File::exportDirectories()
     */
    public function exportDirectories(
        int $offset = 0,
        int $count = 100
    ) {
        return false;
    }

    /**
     * @param int $offset
     * @param int $count
     * @return array|bool
     */
    public function exportFiles(
        int $offset = 0,
        int $count = 1
    ) {
        /** @var array $files */
        $files = [];

        if (!$this->hasObjects()) {
            $this->setObjects(
                $this->getStorage()->getObjects(['maxResults' => $count])
            );
        } else {
            $this->setObjects(
                $this->getStorage()->getObjects([
                    'maxResults'    => $count,
                    'nextPageToken' => $this->getObjects()->nextPageToken,
                ])
            );
        }

        if (!$this->hasObjects()) {
            return false;
        }

        /** @var StorageObject $object */
        foreach ($this->getObjects() as $object) {
            /** @var string $name */
            $name = $object->name();

            if (!empty($name) && $name[0] !== DIRECTORY_SEPARATOR) {
                $files[] = [
                    'filename' => $name,
                    'content'  => $object->downloadAsString(),
                ];
            }
        }

        return $files;
    }

    /**
     * @param array $dirs
     * @return $this
     */
    public function importDirectories(array $dirs = [])
    {
        return $this;
    }

    /**
     * @param array $files
     * @return $this
     */
    public function importFiles(array $files = [])
    {
        /** @var array $file */
        foreach ($files as $file) {
            /** @var string $filePath */
            $filePath = $this->getFilePath($file['filename'], $file['directory']);

            /** @var string $relativePath */
            $relativePath = $this->storageHelper->getMediaRelativePath($filePath);

            try {
                /** @var string $aclPolicy */
                $aclPolicy = $this->getStorage()->getObjectAclPolicy();

                /* Upload file object to bucket. */
                $this->getStorage()->uploadObject($file['content'], [
                    'name' => $relativePath,
                    'predefinedAcl' => $aclPolicy,
                ]);

                if (!$this->getStorage()->objectExists($relativePath)) {
                    /** @var LocalizedException $exception */
                    $exception = $this->exceptionFactory->create(
                        LocalizedException::class,
                        __('Unable to save file: %1', $filePath)
                    );
                    throw $exception;
                }
            } catch (LocalizedException | Exception $e) {
                $this->errors[] = $e->getMessage();
                $this->logger->critical($e);
            }
        }

        return $this;
    }

    /**
     * @param string $filename
     * @return $this
     */
    public function saveFile(string $filename)
    {
        /** @var string $mediaPath */
        $mediaPath = $this->filesystem
            ->getDirectoryRead(DirectoryList::MEDIA)
            ->getAbsolutePath();

        /** @var string $filePath */
        $filePath = $this->getFilePath($filename, $mediaPath);

        try {
            /** @var resource $handle */
            $handle = $this->fileDriver->fileOpen($filePath, 'r');

            /** @var string $relativePath */
            $relativePath = $this->storageHelper->getMediaRelativePath($filePath);

            /** @var string $aclPolicy */
            $aclPolicy = $this->getStorage()->getObjectAclPolicy();

            /* Upload file object to bucket. */
            $this->getStorage()->uploadObject($handle, [
                'name' => $relativePath,
                'predefinedAcl' => $aclPolicy,
            ]);

            if (!$this->getStorage()->objectExists($relativePath)) {
                /** @var LocalizedException $exception */
                $exception = $this->exceptionFactory->create(
                    LocalizedException::class,
                    __('Unable to save file: %1', $filePath)
                );
                throw $exception;
            }
        } catch (LocalizedException | Exception $e) {
            $this->errors[] = $e->getMessage();
            $this->logger->critical($e);
        }

        return $this;
    }

    /**
     * @param string $filePath
     * @return bool Returns true on existing file, false if file is to be downloaded in background (or on failure)
     */
    public function downloadFile(string $filePath): bool
    {
        $mediaPrefix  = DirectoryList::MEDIA . DIRECTORY_SEPARATOR;
        $relativePath = $this->storageHelper->getMediaRelativePath($filePath);

        if (strpos($relativePath, $mediaPrefix) === 0) {
            $relativePath = substr($relativePath, strlen($mediaPrefix));
        }

        $cache    = $this->cache->load(GcsCache::TYPE_IDENTIFIER);
        $cacheGcs = $cache ? $this->serializer->unserialize($cache) : [];
        $cacheKey = $relativePath . '?imgstore=' . $this->getStoreCode();

        if (array_key_exists($cacheKey, $cacheGcs)) {
            return $cacheGcs[$cacheKey];
        }

        $mediaDir = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        if (!$mediaDir->isFile($relativePath)) {
            if (stristr($_SERVER['SCRIPT_NAME'], 'get.php')) {
                // Store the impending request in the cache to avoid duplicate attempts
                $this->cache->save(
                    $this->serializer->serialize(array_merge($cacheGcs, [$cacheKey => false])),
                    GcsCache::TYPE_IDENTIFIER,
                    [GcsCache::CACHE_TAG]
                );
            }

            $this->loadByFilename($relativePath);

            return false;
        }

        $this->cache->save(
            $this->serializer->serialize(array_merge($cacheGcs, [$cacheKey => true])),
            GcsCache::TYPE_IDENTIFIER,
            [GcsCache::CACHE_TAG]
        );

        return true;
    }

    /**
     * @param string $filePath
     * @return bool
     */
    public function fileExists(string $filePath): bool
    {
        return $this->getStorage()->objectExists($filePath);
    }

    /**
     * @param string $source
     * @param string $target
     * @return $this
     */
    public function copyFile(string $source, string $target)
    {
        if ($this->getStorage()->objectExists($source)) {
            $this->getStorage()->copyObject($source, $target);
        }

        return $this;
    }

    /**
     * @param string $source
     * @param string $target
     * @return $this
     */
    public function renameFile(string $source, string $target)
    {
        if ($this->getStorage()->objectExists($source)) {
            $this->getStorage()->renameObject($source, $target);
        }

        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function deleteFile(string $path)
    {
        if ($this->getStorage()->objectExists($path)) {
            $this->getStorage()->deleteObject($path);
        }

        return $this;
    }

    /**
     * @param string $path
     * @return array
     */
    public function getSubdirectories(string $path): array
    {
        /** @var array $subdirs */
        $subdirs = [];

        /** @var string $mediaPath */
        $mediaPath = $this->storageHelper->getMediaRelativePath($path);

        /** @var string $prefix */
        $prefix = rtrim($mediaPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        /** @var ObjectIterator<StorageObject> $objectsPrefixes */
        $objectsPrefixes = $this->getStorage()->getObjects([
            'delimiter' => DIRECTORY_SEPARATOR,
            'prefix'    => $prefix,
        ]);

        if (isset($objectsPrefixes['prefixes'])) {
            /** @var string $subdir */
            foreach ($objectsPrefixes['prefixes'] as $subdir) {
                $subdirs[] = [
                    'name' => substr($subdir, strlen($prefix)),
                ];
            }
        }

        return $subdirs;
    }

    /**
     * @param string $path
     * @return array
     */
    public function getDirectoryFiles(string $path): array
    {
        /** @var array $files */
        $files = [];

        /** @var string $mediaPath */
        $mediaPath = $this->storageHelper->getMediaRelativePath($path);

        /** @var string $prefix */
        $prefix = rtrim($mediaPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        /** @var ObjectIterator<StorageObject> $objectsPrefixes */
        $objectsPrefixes = $this->getStorage()->getObjects([
            'delimiter' => DIRECTORY_SEPARATOR,
            'prefix'    => $prefix,
        ]);

        if (isset($objectsPrefixes['objects'])) {
            /** @var StorageObject $object */
            foreach ($objectsPrefixes['objects'] as $object) {
                /** @var string $name */
                $name = $object->name();

                if ($name !== $prefix) {
                    $files[] = [
                        'filename' => $name,
                        'content'  => $object->downloadAsString(),
                    ];
                }
            }
        }

        return $files;
    }

    /**
     * @param string $path
     * @param string|null $prefix
     * @return string
     */
    public function getFilePath(
        string $path,
        ?string $prefix = null
    ): string
    {
        if (!empty($prefix)) {
            $path = implode(DIRECTORY_SEPARATOR, [
                rtrim($prefix, DIRECTORY_SEPARATOR),
                ltrim($path, DIRECTORY_SEPARATOR),
            ]);
        }

        return $path;
    }
}
