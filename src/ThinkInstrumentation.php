<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\SDK\Common\Configuration\Configuration;

class ThinkInstrumentation
{
    public const NAME = 'thinkphp';

    public static function register(): void
    {
        $instrumentation = new CachedInstrumentation(
            'io.opentelemetry.contrib.php.thinkphp',
            null,
            'https://opentelemetry.io/schemas/1.24.0'
        );


        hooks\console\Command::hook($instrumentation);
        hooks\contracts\console\Kernel::hook($instrumentation);
        hooks\contracts\http\kernel::hook($instrumentation);
//        Hooks\Illuminate\Contracts\Queue\Queue::hook($instrumentation);
        hooks\foundation\Application::hook($instrumentation);
        hooks\foundation\console\ServerCommand::hook($instrumentation);
    }

    public static function shouldTraceCli(): bool
    {
        return PHP_SAPI !== 'cli' || (
                class_exists(Configuration::class)
                && Configuration::getBoolean('OTEL_PHP_TRACE_CLI_ENABLED', false)
            );
    }
}