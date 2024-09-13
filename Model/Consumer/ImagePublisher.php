<?php

namespace AuroraExtensions\GoogleCloudStorage\Model\Consumer;

use Magento\Framework\MessageQueue\PublisherInterface;

class ImagePublisher
{
    const QUEUE_NAME = 'outeredge.fallback.image.queue';

    public function __construct(
        protected PublisherInterface $publisher
    ) {
    }

    public function execute($data)
    {
        $this->publisher->publish(self::QUEUE_NAME, $data);
    }
}
