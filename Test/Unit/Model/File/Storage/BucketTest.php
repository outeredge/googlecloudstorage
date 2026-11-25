<?php
/**
 * BucketTest.php
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT license, which
 * is bundled with this package in the file LICENSE.txt.
 *
 * It is also available on the Internet at the following URL:
 * https://docs.auroraextensions.com/magento/extensions/2.x/googlecloudstorage/LICENSE.txt
 *
 * @package     AuroraExtensions\GoogleCloudStorage\Test\Unit\Model\File\Storage
 * @copyright   Copyright (C) 2021 Aurora Extensions <support@auroraextensions.com>
 * @license     MIT
 */
declare(strict_types=1);

namespace AuroraExtensions\GoogleCloudStorage\Test\Unit\Model\File\Storage;

use AuroraExtensions\GoogleCloudStorage\Api\StorageObjectManagementInterface;
use AuroraExtensions\GoogleCloudStorage\Exception\ExceptionFactory;
use AuroraExtensions\GoogleCloudStorage\Model\Cache\Key;
use AuroraExtensions\GoogleCloudStorage\Model\Cache\Type\GcsCache;
use AuroraExtensions\GoogleCloudStorage\Model\File\Storage\Bucket;
use AuroraExtensions\GoogleCloudStorage\Model\System\ModuleConfig;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\MediaStorage\Helper\File\Storage\Database as StorageHelper;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \AuroraExtensions\GoogleCloudStorage\Model\File\Storage\Bucket::downloadFile
 * @covers \AuroraExtensions\GoogleCloudStorage\Model\File\Storage\Bucket::getStoreCode
 */
class BucketTest extends TestCase
{
    /** @var Bucket $bucket */
    private $bucket;

    /** @var CacheInterface|PHPUnit\Framework\MockObject\MockObject $cache */
    private $cache;

    /** @var Filesystem|PHPUnit\Framework\MockObject\MockObject $filesystem */
    private $filesystem;

    /** @var ReadInterface|PHPUnit\Framework\MockObject\MockObject $mediaDir */
    private $mediaDir;

    /** @var StorageObjectManagementInterface|PHPUnit\Framework\MockObject\MockObject $storageAdapter */
    private $storageAdapter;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $_GET    = [];
        $_SERVER = [
            'REQUEST_URI' => '/get.php',
            'SCRIPT_NAME' => 'pub/get.php',
        ];

        /** @var StoreInterface|PHPUnit\Framework\MockObject\MockObject $store */
        $store = $this->createStub(Store::class);
        $store->method('getCode')->willReturn('default');
        $store->method('getBaseUrl')->willReturn('http://localhost/media/');

        /** @var StoreManagerInterface|PHPUnit\Framework\MockObject\MockObject $storeManager */
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $storeManager->method('getStores')->willReturn([
            'default' => $store,
            'custom'  => $store,
        ]);

        $this->storageAdapter = $this->createMock(
            StorageObjectManagementInterface::class
        );

        $this->cache = $this->createMock(CacheInterface::class);

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('serialize')->willReturnCallback(
            static fn ($value): string => $value === true ? 'serialized:true' : 'serialized:false'
        );
        $serializer->method('unserialize')->willReturnCallback(
            static fn (string $value) => $value === 'serialized:true'
        );

        $this->mediaDir = $this->createMock(ReadInterface::class);

        $this->filesystem = $this->createMock(Filesystem::class);
        $this->filesystem->method('getDirectoryRead')->willReturn($this->mediaDir);

        $storageHelper = $this->createStub(StorageHelper::class);
        $storageHelper->method('getMediaRelativePath')->willReturnCallback(
            static fn (string $path): string => preg_replace('#^media/#', '', $path) ?? $path
        );

        $this->bucket = new Bucket(
            $this->createStub(ExceptionFactory::class),
            $this->createStub(FileDriver::class),
            $this->filesystem,
            $this->createStub(LoggerInterface::class),
            $this->createStub(ModuleConfig::class),
            $storageHelper,
            $this->storageAdapter,
            $this->cache,
            $serializer,
            $storeManager
        );
    }

    /**
     * @param string $relativePath
     * @param string $storeCode
     * @return string
     */
    private static function cacheIdFor(string $relativePath, string $storeCode): string
    {
        return Key::generate($relativePath, $storeCode);
    }

    /**
     * @return void
     */
    public function testDownloadFileReturnsCachedResultForKnownPath(): void
    {
        $this->cache->method('load')->willReturnMap([
            [self::cacheIdFor('catalog/product/image.jpg', 'default'), 'serialized:true'],
        ]);

        $this->filesystem->expects($this->never())->method('getDirectoryRead');

        $this->assertTrue($this->bucket->downloadFile('media/catalog/product/image.jpg'));
    }

    /**
     * @return void
     */
    public function testDownloadFileCachesPositiveResultPerPathWithLifetime(): void
    {
        $this->cache->method('load')->willReturnMap([
            [self::cacheIdFor('catalog/product/image.jpg', 'default'), false],
        ]);

        $this->mediaDir->method('isFile')->willReturnMap([
            ['catalog/product/image.jpg', true],
        ]);

        $this->cache->expects($this->once())
            ->method('save')
            ->with(
                'serialized:true',
                self::cacheIdFor('catalog/product/image.jpg', 'default'),
                [GcsCache::CACHE_TAG],
                Key::LIFETIME
            );

        $this->assertTrue($this->bucket->downloadFile('media/catalog/product/image.jpg'));
    }

    /**
     * @return void
     */
    public function testDownloadFileStoresNegativeMarkerWithLifetimeForGetPhp(): void
    {
        $this->cache->method('load')->willReturnMap([
            [self::cacheIdFor('catalog/product/missing.jpg', 'default'), false],
        ]);

        $this->mediaDir->method('isFile')->willReturnMap([
            ['catalog/product/missing.jpg', false],
        ]);

        $this->storageAdapter->expects($this->once())
            ->method('getObject')
            ->with('catalog/product/missing.jpg', 'default');

        $this->cache->expects($this->once())
            ->method('save')
            ->with(
                'serialized:false',
                self::cacheIdFor('catalog/product/missing.jpg', 'default'),
                [GcsCache::CACHE_TAG],
                Key::LIFETIME
            );

        $this->assertFalse($this->bucket->downloadFile('media/catalog/product/missing.jpg'));
    }

    /**
     * @return void
     */
    public function testDownloadFileDoesNotCacheNegativeMarkerOutsideGetPhp(): void
    {
        $_SERVER['SCRIPT_NAME'] = 'index.php';

        $this->cache->method('load')->willReturnMap([
            [self::cacheIdFor('catalog/product/missing.jpg', 'default'), false],
        ]);

        $this->mediaDir->method('isFile')->willReturnMap([
            ['catalog/product/missing.jpg', false],
        ]);

        $this->cache->expects($this->never())->method('save');

        $this->assertFalse($this->bucket->downloadFile('media/catalog/product/missing.jpg'));
    }

    /**
     * @return void
     */
    public function testDownloadFileUsesValidImgStoreValue(): void
    {
        $_GET['imgstore'] = 'custom';

        $this->cache->method('load')->willReturnMap([
            [self::cacheIdFor('catalog/product/missing.jpg', 'custom'), false],
        ]);

        $this->mediaDir->method('isFile')->willReturnMap([
            ['catalog/product/missing.jpg', false],
        ]);

        $this->storageAdapter->expects($this->once())
            ->method('getObject')
            ->with('catalog/product/missing.jpg', 'custom');

        $this->assertFalse($this->bucket->downloadFile('media/catalog/product/missing.jpg'));
    }

    /**
     * An untrusted imgstore value must be validated against the existing
     * store codes and fall back to the current store code.
     *
     * @return void
     */
    public function testDownloadFileFallsBackForUnknownImgStoreValue(): void
    {
        $_GET['imgstore'] = 'arbitrary-attacker-value';

        $this->cache->method('load')->willReturnMap([
            [self::cacheIdFor('catalog/product/missing.jpg', 'default'), false],
        ]);

        $this->mediaDir->method('isFile')->willReturnMap([
            ['catalog/product/missing.jpg', false],
        ]);

        $this->storageAdapter->expects($this->once())
            ->method('getObject')
            ->with('catalog/product/missing.jpg', 'default');

        $this->assertFalse($this->bucket->downloadFile('media/catalog/product/missing.jpg'));
    }

    /**
     * @return void
     */
    public function testDownloadFileFallsBackForNonStringImgStoreValue(): void
    {
        $_GET['imgstore'] = ['arbitrary-attacker-value'];

        $this->cache->method('load')->willReturnMap([
            [self::cacheIdFor('catalog/product/missing.jpg', 'default'), false],
        ]);

        $this->mediaDir->method('isFile')->willReturnMap([
            ['catalog/product/missing.jpg', false],
        ]);

        $this->storageAdapter->expects($this->once())
            ->method('getObject')
            ->with('catalog/product/missing.jpg', 'default');

        $this->assertFalse($this->bucket->downloadFile('media/catalog/product/missing.jpg'));
    }
}
