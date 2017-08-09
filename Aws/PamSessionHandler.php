<?php
namespace Pam\Aws;

use Aws\DynamoDb\SessionHandler;

class PamSessionHandler extends SessionHandler
{
    public function gc($maxLifetime)
    {
        $this->garbageCollect();
    }
}