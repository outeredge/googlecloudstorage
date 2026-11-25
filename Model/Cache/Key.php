<?php
/**
 * Key.php
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT license, which
 * is bundled with this package in the file LICENSE.txt.
 *
 * It is also available on the Internet at the following URL:
 * https://docs.auroraextensions.com/magento/extensions/2.x/googlecloudstorage/LICENSE.txt
 *
 * @package     AuroraExtensions\GoogleCloudStorage\Model\Cache
 * @copyright   Copyright (C) 2021 Aurora Extensions <support@auroraextensions.com>
 * @license     MIT
 */
declare(strict_types=1);

namespace AuroraExtensions\GoogleCloudStorage\Model\Cache;

use AuroraExtensions\GoogleCloudStorage\Model\Cache\Type\GcsCache;

use function hash;

class Key
{
    /** @var int LIFETIME Default lifetime (in seconds) of a per-file cache entry */
    public const LIFETIME = 300;

    /**
     * Build a cache identifier for a single media file and store code.
     *
     * Using one identifier per file (instead of a single shared record)
     * bounds the size of each cache entry, regardless of how many
     * distinct paths are requested.
     *
     * @param string $path
     * @param string $storeCode
     * @return string
     */
    public static function generate(string $path, string $storeCode): string
    {
        return GcsCache::TYPE_IDENTIFIER
            . '_'
            . hash('sha256', $path . '?imgstore=' . $storeCode);
    }
}
