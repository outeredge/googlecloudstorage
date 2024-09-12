<?php

namespace AuroraExtensions\GoogleCloudStorage\Model\Consumer;

use AuroraExtensions\GoogleCloudStorage\Api\StorageObjectManagementInterface;

class ImageConsumer
{
    
    /**
     * @param StorageObjectManagementInterface $storage
     */
    public function processMessage(StorageObjectManagementInterface $storage): void
    {
    	// Implement your logic to here
        // This method will be executed when a message is available in the queue
    }
}