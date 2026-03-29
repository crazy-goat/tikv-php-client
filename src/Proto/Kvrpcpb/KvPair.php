<?php
declare(strict_types=1);

namespace Kvrpcpb;

use Google\Protobuf\Internal\Message;

class KvPair extends Message
{
    protected $key = '';
    protected $value = '';
    
    public function __construct($data = null)
    {
        parent::__construct($data);
    }
    
    public function getKey(): string
    {
        return $this->key;
    }
    
    public function setKey(string $key): void
    {
        $this->key = $key;
    }
    
    public function getValue(): string
    {
        return $this->value;
    }
    
    public function setValue(string $value): void
    {
        $this->value = $value;
    }
}
