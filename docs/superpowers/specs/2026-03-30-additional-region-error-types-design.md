# Additional Region Error Types Design

## Status

Draft

## Context

Currently RawKvClient handles basic region errors: NotLeader, EpochNotMatch, ServerIsBusy, StaleCommand, RegionNotFound, RaftEntryTooLarge, KeyNotInRegion.

TiKV returns additional error types that need specific backoff strategies or should be marked as non-retryable.

## Goals

- Add support for 9 additional region error types
- Configure appropriate backoff strategies for each
- Mark fatal errors as non-retryable
- Maintain backward compatibility

## Error Types to Add

| Error | BackoffType | baseMs | capMs | Jitter | Retryable |
|-------|-------------|--------|-------|--------|-----------|
| DiskFull | DiskFull | 500 | 5000 | NoJitter | Yes |
| RegionNotInitialized | RegionNotInitialized | 2 | 1000 | NoJitter | Yes |
| ReadIndexNotReady | ReadIndexNotReady | 2 | 500 | NoJitter | Yes |
| ProposalInMergingMode | ProposalInMergingMode | 2 | 500 | NoJitter | Yes |
| RecoveryInProgress | RecoveryInProgress | 100 | 10000 | EqualJitter | Yes |
| IsWitness | IsWitness | 1000 | 10000 | EqualJitter | Yes |
| MaxTimestampNotSynced | MaxTimestampNotSynced | 2 | 500 | NoJitter | Yes |
| FlashbackInProgress | None (fatal) | - | - | - | No |
| FlashbackNotPrepared | None (fatal) | - | - | - | No |

## Architecture

### BackoffType Enum Extension

```php
enum BackoffType
{
    // Existing cases...
    
    // New cases
    case DiskFull;
    case RegionNotInitialized;
    case ReadIndexNotReady;
    case ProposalInMergingMode;
    case RecoveryInProgress;
    case IsWitness;
    case MaxTimestampNotSynced;
}
```

### classifyError() Extension

```php
private function classifyError(TiKvException $e): ?BackoffType
{
    $message = $e->getMessage();
    
    // Existing checks...
    
    // New error types
    if (str_contains($message, 'DiskFull')) {
        return BackoffType::DiskFull;
    }
    if (str_contains($message, 'RegionNotInitialized')) {
        return BackoffType::RegionNotInitialized;
    }
    if (str_contains($message, 'ReadIndexNotReady')) {
        return BackoffType::ReadIndexNotReady;
    }
    if (str_contains($message, 'ProposalInMergingMode')) {
        return BackoffType::ProposalInMergingMode;
    }
    if (str_contains($message, 'RecoveryInProgress')) {
        return BackoffType::RecoveryInProgress;
    }
    if (str_contains($message, 'IsWitness')) {
        return BackoffType::IsWitness;
    }
    if (str_contains($message, 'MaxTimestampNotSynced')) {
        return BackoffType::MaxTimestampNotSynced;
    }
    
    // Fatal errors (non-retryable)
    if (str_contains($message, 'FlashbackInProgress')) {
        return null; // Fatal, no retry
    }
    if (str_contains($message, 'FlashbackNotPrepared')) {
        return null; // Fatal, no retry
    }
    
    // ... rest of existing checks
}
```

## Implementation

### Files to Modify

1. `src/Client/Retry/BackoffType.php` - Add new enum cases and configuration
2. `src/Client/RawKv/RawKvClient.php` - Update classifyError() method

### Testing

- Unit tests for each new BackoffType configuration
- Unit tests for classifyError() with new error types
- Integration tests for retry behavior

## Backward Compatibility

- Existing error handling unchanged
- New error types only add more retry scenarios
- Fatal errors (Flashback*) fail fast as intended
