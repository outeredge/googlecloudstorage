<?php

namespace AuroraExtensions\GoogleCloudStorage\Model\Consumer;

use AuroraExtensions\GoogleCloudStorage\Model\Adapter\StorageObjectManagement;

class ImageConsumer
{
    const USER_AGENT = 'outeredge/gcs';

    public function __construct(
        protected StorageObjectManagement $storageObjectManagement
    ) {
    }

    public function processMessage($data): void
    {
    	/* Attempt to load the image from fallback URL and upload to GCS */
        $ch = curl_init($data[0]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        $content = curl_exec($ch);

        if ($content && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
            $this->storageObjectManagement->uploadObject($content, [
                'name' => $data[1],
                'predefinedAcl' => $this->storageObjectManagement->getObjectAclPolicy()
            ]);
        }

        curl_close($ch);
    }
}
