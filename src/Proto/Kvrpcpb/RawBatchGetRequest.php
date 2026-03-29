<?php
declare(strict_types=1);

namespace Kvrpcpb;

use Google\Protobuf\Internal\Message;

class RawBatchGetRequest extends Message
{
    protected $context = null;
    protected $keys = [];
    
    public function __construct($data = null)
    {
        parent::__construct($data);
    }
    
    public function getContext(): ?Context
    {
        return $this->context;
    }
    
    public function setContext(Context $context): void
    {
        $this->context = $context;
    }
    
    public function getKeys(): array
    {
        return $this->keys;
    }
    
    public function setKeys(array $keys): void
    {
        $this->keys = $keys;
    }
}
