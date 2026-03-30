<?php

declare(strict_types=1);

// Bootstrap file for PHPStan to define Google\Protobuf\RepeatedField
// This class is normally provided by the protobuf PHP extension

if (!class_exists(\Google\Protobuf\RepeatedField::class)) {
    class_alias(\Google\Protobuf\Internal\RepeatedField::class, \Google\Protobuf\RepeatedField::class);
}
