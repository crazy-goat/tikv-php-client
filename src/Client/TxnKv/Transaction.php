<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\TxnKv;

final class Transaction
{
    private ?int $commitTs = null;
    private TransactionStatus $status = TransactionStatus::Active;
    /** @var array<string, string> */
    private array $writeSet = [];
    /** @var array<string, ?string> */
    private array $readSet = [];

    public function __construct(
        private readonly string $txnId,
        private readonly int $startTs,
        private readonly bool $pessimistic,
        private readonly int $priority,
    ) {
    }

    public function getTxnId(): string
    {
        return $this->txnId;
    }

    public function getStartTs(): int
    {
        return $this->startTs;
    }

    public function getCommitTs(): ?int
    {
        return $this->commitTs;
    }

    public function getStatus(): TransactionStatus
    {
        return $this->status;
    }

    public function isPessimistic(): bool
    {
        return $this->pessimistic;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function get(string $key): ?string
    {
        // Check writeSet first (own writes)
        if (isset($this->writeSet[$key])) {
            return $this->writeSet[$key];
        }

        // TODO: Read from TiKV with startTs
        $value = null; // Placeholder

        // Record in readSet
        $this->readSet[$key] = $value;

        return $value;
    }

    public function set(string $key, string $value): void
    {
        if ($this->pessimistic) {
            // TODO: Acquire lock immediately
        }

        $this->writeSet[$key] = $value;
    }

    public function delete(string $key): void
    {
        $this->set($key, ''); // Empty string means delete
    }

    /**
     * @return array<array{key: string, value: ?string}>
     */
    public function scan(): array
    {
        // TODO: Implement scan
        return [];
    }

    public function commit(): void
    {
        if ($this->status !== TransactionStatus::Active) {
            throw new \RuntimeException('Transaction is not active');
        }

        // TODO: Implement commit
        $this->status = TransactionStatus::Committed;
    }

    public function rollback(): void
    {
        if ($this->status !== TransactionStatus::Active) {
            throw new \RuntimeException('Transaction is not active');
        }

        $this->writeSet = [];
        $this->readSet = [];
        $this->commitTs = null;
        $this->status = TransactionStatus::RolledBack;
    }

    /**
     * @return array<string, string>
     */
    public function getWriteSet(): array
    {
        return $this->writeSet;
    }

    /**
     * @return array<string, ?string>
     */
    public function getReadSet(): array
    {
        return $this->readSet;
    }
}
