<?php

namespace AuroraExtensions\GoogleCloudStorage\Model\Consumer;

use Magento\Framework\MessageQueue\PublisherInterface ;

class ImagePublisher
{
    
    const QUEUE_NAME = 'outeredge.fallback.image.queue';

    /**
     * @var \Magento\Framework\MessageQueue\PublisherInterface
     */
    private $publisher;

    /**
     * @param \Magento\Framework\MessageQueue\PublisherInterface $publisher
     */
    public function __construct(PublisherInterface $publisher)
    {
        $this->publisher = $publisher;
    }


    public function execute(OrderInterface $order)
    {
        $this->publisher->publish(self::QUEUE_NAME, $order);
    }
}