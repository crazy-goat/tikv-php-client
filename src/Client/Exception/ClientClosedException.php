<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Exception;

final class ClientClosedException extends TiKvException
{
    public function __construct()
    {
        parent::__construct('Client is closed');
    }
}
