# Feature: DeletePrefix - Prefix-based Deletion

## Overview
Implement convenient deletion of all keys with common prefix.

## Reference Implementation
- **Go**: Not explicit, built on DeleteRange
- **Java**: `public synchronized void deletePrefix(ByteString key)`

## Implementation Details

### Algorithm
1. Calculate end key from prefix
2. Use deleteRange with calculated range
3. Return count of deleted keys

### API Design
```php
public function deletePrefix(string $prefix): int
{
    // Deletes all keys starting with $prefix
    // Returns: number of keys deleted
}
```

### Safety Features
- Require confirmation for large deletions
- Support dry-run mode
- Progress callback for large operations

## Testing Strategy
1. Test deleting keys with prefix
2. Test with no matching keys
3. Test safety limits
4. Test progress callback

## Priority: MEDIUM
Useful for cleaning up data by category.
