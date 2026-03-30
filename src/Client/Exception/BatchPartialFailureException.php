<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Exception;

final class BatchPartialFailureException extends TiKvException
{
    /**
     * @param array<int, TiKvException> $regionErrors regionId => exception
     * @param int $totalRegions Total number of regions in batch
     */
    public function __construct(
        private readonly array $regionErrors,
        private readonly int $totalRegions,
    ) {
        $firstError = reset($regionErrors);
        parent::__construct(
            sprintf(
                'Batch operation partially failed: %d of %d regions failed. First error: %s',
                count($regionErrors),
                $totalRegions,
                $firstError instanceof TiKvException ? $firstError->getMessage() : 'Unknown',
            )
        );
    }

    /**
     * @return array<int, TiKvException>
     */
    public function getRegionErrors(): array
    {
        return $this->regionErrors;
    }

    public function getTotalRegions(): int
    {
        return $this->totalRegions;
    }
}
