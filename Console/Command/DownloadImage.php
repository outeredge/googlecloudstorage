<?php

declare(strict_types=1);

namespace AuroraExtensions\GoogleCloudStorage\Console\Command;

use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AuroraExtensions\GoogleCloudStorage\Model\Adapter\StorageObjectManagement;

class DownloadImage extends Command
{
    const USER_AGENT = 'outeredge/gcs';
    const URL = 'url';
    const PREFIXEDPATH = 'prefixedPath';

    public function __construct(
        protected StorageObjectManagement $storageObjectManagement,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('outeredge:gcs:download');
        $this->setDescription('Download Image background process');

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

        $url = $input->getOption(self::URL);
        $prefixedPath = $input->getOption(self::PREFIXEDPATH);

        try {
            $ch = curl_init($ulr);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
            $content = curl_exec($ch);
            if ($content && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
                $this->storageObjectManagement->uploadObject($content, [
                    'name' => $prefixedPath,
                    'predefinedAcl' => $this->storageObjectManagement->getObjectAclPolicy()
                ]);
            }
            curl_close($ch);

            $output->writeln('<comment>Successfully donwload image</comment>');
        } catch (LocalizedException $e) {
            $output->writeln(sprintf(
                '<error>%s</error>',
                $e->getMessage()
            ));
            $exitCode = 1;
        }

        return $exitCode;
    }
}
