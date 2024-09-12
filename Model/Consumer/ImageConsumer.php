<?php

namespace AuroraExtensions\GoogleCloudStorage\Model\Consumer;

class ImageConsumer
{
    
    /**
     * @param OrderInterface $order
     */
    public function processMessage(OrderInterface $order): void
    {
    	// Implement your logic to here
        // This method will be executed when a message is available in the queue
    }
}