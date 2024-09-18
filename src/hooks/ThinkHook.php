<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

interface ThinkHook
{
    public static function hook(CachedInstrumentation $instrumentation): ThinkHook;

    public function instrument(): void;
}