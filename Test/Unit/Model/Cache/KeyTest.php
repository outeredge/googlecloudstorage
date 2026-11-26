<?php
/**
 * KeyTest.php
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT license, which
 * is bundled with this package in the file LICENSE.txt.
 *
 * It is also available on the Internet at the following URL:
 * https://docs.auroraextensions.com/magento/extensions/2.x/googlecloudstorage/LICENSE.txt
 *
 * @package     AuroraExtensions\GoogleCloudStorage\Test\Unit\Model\Cache
 * @copyright   Copyright (C) 2021 Aurora Extensions <support@auroraextensions.com>
 * @license     MIT
 */
declare(strict_types=1);

namespace AuroraExtensions\GoogleCloudStorage\Test\Unit\Model\Cache;

use AuroraExtensions\GoogleCloudStorage\Model\Cache\Key;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AuroraExtensions\GoogleCloudStorage\Model\Cache\Key
 */
class KeyTest extends TestCase
{
    /**
     * @return void
     */
    public function testGenerateReturnsHashedPerPathIdentifier(): void
    {
        $expected = 'outeredge_gcs_cache_' . hash('sha256', 'catalog/product/image.jpg?imgstore=default');

        $this->assertSame($expected, Key::generate('catalog/product/image.jpg', 'default'));
    }

    /**
     * @return void
     */
    public function testGenerateIsUniquePerPathAndStoreCode(): void
    {
        $base = Key::generate('catalog/product/image.jpg', 'default');

        $this->assertNotSame($base, Key::generate('catalog/product/other.jpg', 'default'));
        $this->assertNotSame($base, Key::generate('catalog/product/image.jpg', 'custom'));
    }

    /**
     * @return void
     */
    public function testLifetimeIsShortAndBounded(): void
    {
        $this->assertSame(300, Key::LIFETIME);
    }
}
