#!/bin/sh
# Generate PHP message classes and gRPC service stubs from TiKV proto files.
#
# Usage:
#   docker-compose run --rm php-client sh /app/scripts/generate-proto.sh
#
# This script:
#   1. Copies only the needed .proto files to a temp directory
#   2. Strips gogoproto/rustproto imports and options (Go/Rust-specific, incompatible with PHP)
#   3. Runs protoc with --php_out (message classes) and --grpc_out (service stubs)
#   4. Outputs everything to src/Proto/

set -e

KVPROTO_DIR="/app/proto/kvproto"
PROTO_SRC="$KVPROTO_DIR/proto"
INCLUDE_DIR="$KVPROTO_DIR/include"
OUT_DIR="/app/src/Proto"

# All proto files needed (direct deps + transitive closure).
# Traced from: kvrpcpb.proto, pdpb.proto, metapb.proto, errorpb.proto, tikvpb.proto
PROTO_FILES="
  kvrpcpb.proto
  pdpb.proto
  metapb.proto
  errorpb.proto
  tikvpb.proto
  deadlock.proto
  resource_manager.proto
  tracepb.proto
  encryptionpb.proto
  replication_modepb.proto
  coprocessor.proto
  disaggregated.proto
  mpp.proto
  raft_serverpb.proto
  disk_usage.proto
  eraftpb.proto
"

echo "=== TiKV PHP Proto Generator ==="
echo ""

# --- Prepare temp workspace ---
WORK_DIR=$(mktemp -d)
trap "rm -rf $WORK_DIR" EXIT

echo "Copying proto files..."
for f in $PROTO_FILES; do
  COPIED=false

  # Prefer proto/ as primary source
  if [ -f "$PROTO_SRC/$f" ] && [ -s "$PROTO_SRC/$f" ]; then
    cp "$PROTO_SRC/$f" "$WORK_DIR/$f"
    COPIED=true
  fi

  # Fall back to include/ (e.g. eraftpb.proto lives there with actual content)
  if [ "$COPIED" = false ] && [ -f "$INCLUDE_DIR/$f" ]; then
    cp "$INCLUDE_DIR/$f" "$WORK_DIR/$f"
    COPIED=true
  fi

  if [ "$COPIED" = false ]; then
    echo "WARNING: $f not found (or empty) in proto/ or include/, skipping"
  fi
done

# --- Strip gogoproto and rustproto references ---
# These are Go/Rust-specific protobuf extensions that protoc for PHP doesn't understand.
# We handle three patterns:
#   1. import lines:          import "gogoproto/gogo.proto";
#   2. file-level options:    option (gogoproto.marshaler_all) = true;
#   3. inline field options:  bytes data = 1 [(gogoproto.nullable) = false];
#   4. multi-line field opts: bytes data = 1 [\n  (gogoproto.customtype) = ...,\n  ...\n];
echo "Stripping gogoproto/rustproto extensions..."
for f in "$WORK_DIR"/*.proto; do
  [ -f "$f" ] || continue

  # Pass 1: Remove import and file-level option lines
  sed -i \
    -e '/import "gogoproto\/gogo.proto"/d' \
    -e '/import "rustproto.proto"/d' \
    -e '/^option (gogoproto\./d' \
    -e '/^option (rustproto\./d' \
    "$f"

  # Pass 2: Remove single-line inline field options [(gogoproto.*)]
  # e.g.: repeated WaitForEntry entries = 1 [(gogoproto.nullable) = false];
  sed -i -E 's/ *\[(\(gogoproto\.[^]]*)\]//g' "$f"

  # Pass 3: Remove multi-line field options blocks that start with [
  # e.g.: bytes data = 1 [
  #          (gogoproto.customtype) = "...",
  #          (gogoproto.nullable) = false
  #        ];
  # Strategy: replace "= N [" at end of line with "= N;", then delete orphaned option/close lines
  sed -i -E 's/^(.*= [0-9]+) *\[$/\1;/' "$f"
  sed -i '/^ *[(]gogoproto\./d' "$f"
  sed -i '/^ *[(]rustproto\./d' "$f"
  sed -i '/^ *\];$/d' "$f"
done

# --- Clean output directory ---
echo "Cleaning output directory..."
rm -rf "$OUT_DIR"/*
mkdir -p "$OUT_DIR"

# --- Generate PHP classes ---
PROTO_LIST=""
for f in $PROTO_FILES; do
  if [ -f "$WORK_DIR/$f" ]; then
    PROTO_LIST="$PROTO_LIST $f"
  fi
done

FILE_COUNT=$(echo $PROTO_LIST | wc -w)
echo ""
echo "Generating PHP from $FILE_COUNT proto files..."

# Message classes (--php_out)
protoc \
  -I"$WORK_DIR" \
  --php_out="$OUT_DIR" \
  $PROTO_LIST

# gRPC service stubs (--grpc_out) — generates *Client classes for service definitions
protoc \
  -I"$WORK_DIR" \
  --grpc_out="$OUT_DIR" \
  --plugin=protoc-gen-grpc=/usr/bin/grpc_php_plugin \
  $PROTO_LIST

# --- Summary ---
echo ""
echo "=== Done ==="
PHP_COUNT=$(find "$OUT_DIR" -name "*.php" | wc -l)
echo "Generated $PHP_COUNT PHP files in $OUT_DIR"
echo ""
echo "Generated directories:"
find "$OUT_DIR" -mindepth 1 -maxdepth 1 -type d | sort | sed "s|$OUT_DIR/|  |"
echo ""
echo "gRPC service stubs:"
find "$OUT_DIR" -name "*Client.php" -path "*/Tikvpb/*" -o -name "*Client.php" -path "*/Pdpb/*" | sort | sed "s|$OUT_DIR/|  |"
