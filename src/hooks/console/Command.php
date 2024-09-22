<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\console;

use think\console\Command as ThinkCommand;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\ThinkHook;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\ThinkHookTrait;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\PostHookTrait;
use think\console\input\Option;
use function OpenTelemetry\Instrumentation\hook;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;

class Command implements ThinkHook
{
    use ThinkHookTrait;
    use PostHookTrait;

    public function instrument(): void
    {
        hook(
            ThinkCommand::class,
            'execute',
            pre: function (ThinkCommand $command, array $params, string $class, string $function, ?string $filename, ?int $lineno) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = $this->instrumentation
                    ->tracer()
                    ->spanBuilder(sprintf('Command %s', $command->getName() ?: 'unknown'))
                    ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
                    ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINENO, $lineno);

                $parent = Context::getCurrent();
                $span = $builder->startSpan();
                Context::storage()->attach($span->storeInContext($parent));

                return $params;
            },
            post: function (ThinkCommand $command, array $params, ?int $exitCode, ?Throwable $exception) {
                $scope = Context::storage()->scope();
                if (!$scope) {
                    return;
                }

                $span = Span::fromContext($scope->context());
                $span->addEvent('command finished', [
                    'exit-code' => $exitCode?? 0,
                ]);

                $this->endSpan($span, $exception);
            }
        );
    }
}