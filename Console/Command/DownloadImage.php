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

class DownloadImage extends Command
{
    const BUCKETPATH = 'bucketPath';
    const LOCALPATH  = 'localPath';

    public function __construct(
        protected StorageObjectManagement $storageObjectManagement,
        protected CacheInterface $cache,
        protected SerializerInterface $serializer,
        protected LoggerInterface $logger,
        protected Filesystem $filesystem,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('outeredge:gcs:download');
        $this->setDescription('Download image from GCS to local folder');
        $this->addArgument('bucketPath', InputArgument::REQUIRED);
        $this->addArgument('localPath', InputArgument::REQUIRED);

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

        $bucketPath = $input->getArgument(self::BUCKETPATH);
        $localPath  = $input->getArgument(self::LOCALPATH);

        try {
            $mediaPath = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $object    = $this->storageObjectManagement->getBucketObject($bucketPath);

            $file   = $mediaPath->openFile($localPath, 'w');
            $file->lock();
            $file->write($object->downloadAsString());
            $file->unlock();
            $file->close();
            $exists = true;
        } catch (FileSystemException $e) {
            $file->close();
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
}
