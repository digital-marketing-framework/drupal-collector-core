<?php

declare(strict_types=1);

use Mediatis\DrupalCodingStandards\Php\DrupalRectorSetup;
use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;

return static function (RectorConfig $rectorConfig): void {
    DrupalRectorSetup::setup($rectorConfig, __DIR__, 10, PhpVersion::PHP_82);
};
