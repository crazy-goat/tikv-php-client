#!/bin/bash
set -e

echo "=========================================="
echo "TiKV PHP Client - Test Suite"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print section headers
print_section() {
    echo ""
    echo -e "${YELLOW}$1${NC}"
    echo "------------------------------------------"
}

# Check if we're in the right directory
if [ ! -f "composer.json" ]; then
    echo -e "${RED}Error: composer.json not found${NC}"
    echo "Please run this script from the project root"
    exit 1
fi

# Install dependencies if needed
if [ ! -d "vendor" ]; then
    print_section "Installing dependencies..."
    composer install --no-interaction
fi

# Run Unit tests
print_section "Running Unit Tests..."
vendor/bin/phpunit --testsuite Unit --testdox
UNIT_EXIT=$?

# Run Integration tests (if any)
print_section "Running Integration Tests..."
vendor/bin/phpunit --testsuite Integration --testdox || true

# Summary
echo ""
echo "=========================================="
if [ $UNIT_EXIT -eq 0 ]; then
    echo -e "${GREEN}Unit tests passed!${NC}"
else
    echo -e "${RED}Unit tests failed!${NC}"
fi
echo "=========================================="

exit $UNIT_EXIT
