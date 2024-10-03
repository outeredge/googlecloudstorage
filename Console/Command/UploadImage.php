<?php

declare(strict_types=1);

namespace AuroraExtensions\GoogleCloudStorage\Console\Command;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Serialize\SerializerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AuroraExtensions\GoogleCloudStorage\Model\Adapter\StorageObjectManagement;
use AuroraExtensions\GoogleCloudStorage\Model\Cache\Type\GcsCache;
use Symfony\Component\Console\Input\InputArgument;
use Psr\Log\LoggerInterface;

class UploadImage extends Command
{
    const USER_AGENT = 'outeredge/gcs';
    const URL        = 'url';
    const BUCKETPATH = 'bucketPath';
    const LOCALPATH  = 'localPath';

    public function __construct(
        protected StorageObjectManagement $storageObjectManagement,
        protected CacheInterface $cache,
        protected SerializerInterface $serializer,
        protected Filesystem $filesystem,
        protected LoggerInterface $logger,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('outeredge:gcs:upload');
        $this->setDescription('Download image from fallback URL and upload to GCS');
        $this->addArgument(self::URL, InputArgument::REQUIRED);
        $this->addArgument(self::BUCKETPATH, InputArgument::REQUIRED);
        $this->addArgument(self::LOCALPATH, InputArgument::REQUIRED);

        parent::configure();
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $exitCode = 0;
        $exists   = false;

        $url        = $input->getArgument(self::URL);
        $bucketPath = $input->getArgument(self::BUCKETPATH);
        $localPath  = $input->getArgument(self::LOCALPATH);

        try {
            $content = $this->curlRequest($url);

            // If cached file can't be found, try the original source file instead
            if (!$content && strpos($url, 'product/cache/') !== false) {
                $url     = preg_replace('/cache\/[a-z0-9]{32}\//', '', $url);
                $content = $this->curlRequest($url);
            }

            if ($content) {
                $exists = true;

                // Store on the local filesystem
                $mediaPath = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);

                $file   = $mediaPath->openFile($localPath, 'w');
                $file->lock();
                $file->write($content);
                $file->unlock();
                $file->close();

                // Upload to GCS
                $this->storageObjectManagement->uploadObject($content, [
                    'name' => $bucketPath,
                    'predefinedAcl' => $this->storageObjectManagement->getObjectAclPolicy()
                ]);
            }
        } catch (FileSystemException $e) {
            if (isset($file)) {
                $file->close();
            }
        } catch (LocalizedException $e) {
            $this->logger->error($e->getMessage());
            $exitCode = 1;
        }

        $cache    = $this->cache->load(GcsCache::TYPE_IDENTIFIER);
        $cacheGcs = $cache ? $this->serializer->unserialize($cache) : [];

        $this->cache->save(
            $this->serializer->serialize(array_merge($cacheGcs, [$localPath => $exists])),
            GcsCache::TYPE_IDENTIFIER,
            [GcsCache::CACHE_TAG]
        );

        return $exitCode;
    }

    protected function curlRequest($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        $content = curl_exec($ch);

        if ($content && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
            curl_close($ch);
            return $content;
        }

        curl_close($ch);
        return false;
    }
}
