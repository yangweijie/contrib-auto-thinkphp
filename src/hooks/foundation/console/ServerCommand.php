<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\foundation\console;

use think\console\command\RunServer as FoundationServeCommand;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\ThinkHook;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\ThinkHookTrait;
use think\console\input\Option;
use function OpenTelemetry\Instrumentation\hook;

/**
 * Instrument ThinkPHP's local PHP development server.
 */
class ServerCommand implements ThinkHook
{
    use ThinkHookTrait;

    public function instrument(): void
    {
        hook(
            FoundationServeCommand::class,
            'handle',
            pre: static function (FoundationServeCommand $serveCommand, array $params, string $class, string $function, ?string $filename, ?int $lineno) {
                $options = $serveCommand->getDefinition()->getOptions();
                $option_names = [];
                foreach ($options as $option) {
                    $option_names[] = $option->getName();
                }
                foreach ($_ENV as $key => $value) {
                    if (str_starts_with($key, 'OTEL_') && !in_array($key, $option_names)) {
                        $serveCommand->getDefinition()->addOption(new Option($key, null, Option::VALUE_OPTIONAL, 'OTEL_ENV_'.$key, $value));
                    }
                }
            },
        );
    }
}