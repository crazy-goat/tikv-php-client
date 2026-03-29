<?php
declare(strict_types=1);

namespace Kvrpcpb;

use Google\Protobuf\Internal\Message;

class RawBatchGetResponse extends Message
{
    protected $pairs = [];
    
    public function __construct($data = null)
    {
        parent::__construct($data);
    }
    
    public function getPairs(): array
    {
        return $this->pairs;
    }
    
    public function setPairs(array $pairs): void
    {
        $this->pairs = $pairs;
    }
}
