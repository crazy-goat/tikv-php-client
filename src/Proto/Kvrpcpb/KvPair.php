<?php
declare(strict_types=1);

namespace Kvrpcpb;

use Google\Protobuf\Internal\Message;
use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\GPBUtil;

class KvPair extends Message
{
    protected $key = '';
    protected $value = '';
    
    public function __construct($data = null)
    {
        \GPBMetadata\Kvrpcpb::initOnce();
        parent::__construct($data);
    }
    
    public function getKey()
    {
        return $this->key;
    }
    
    public function setKey($var)
    {
        GPBUtil::checkString($var, true);
        $this->key = $var;
        return $this;
    }
    
    public function getValue()
    {
        return $this->value;
    }
    
    public function setValue($var)
    {
        GPBUtil::checkString($var, true);
        $this->value = $var;
        return $this;
    }
}
