<?php

namespace AuroraExtensions\GoogleCloudStorage\Model\Consumer;

use AuroraExtensions\GoogleCloudStorage\Model\Adapter\StorageObjectManagement;

class ImageConsumer
{
    public function __construct(
        protected StorageObjectManagement $storageObjectManagement
    ) {
    }

    public function processMessage($data): void
    {
    	/* Attempt to load the image from fallback URL and upload to GCS */
        $ch = curl_init($data[0]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($ch);

        if ($content && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
            $object = $this->storageObjectManagement->uploadObject($content, [
                'name' => $data[1],
                'predefinedAcl' => $this->storageObjectManagement->getObjectAclPolicy()
            ]);
        }

        curl_close($ch);
    }
}
