<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\contracts\console;

use think\Console as KernelContract;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
//use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\Queue\AttributesBuilder;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\ThinkHook;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\ThinkHookTrait;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\PostHookTrait;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\ThinkInstrumentation;
use function OpenTelemetry\Instrumentation\hook;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;

class Kernel implements ThinkHook
{
//    use AttributesBuilder;
    use ThinkHookTrait;
    use PostHookTrait;

    public function instrument(): void
    {
        hook(
            KernelContract::class,
            'handle',
            pre: function (KernelContract $kernel, array $params, string $class, string $function, ?string $filename, ?int $lineno) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = $this->instrumentation
                    ->tracer()
                    ->spanBuilder('think handler')
                    ->setSpanKind(SpanKind::KIND_PRODUCER)
                    ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
                    ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINENO, $lineno);

                $parent = Context::getCurrent();
                $span = $builder->startSpan();
                Context::storage()->attach($span->storeInContext($parent));

                return $params;
            },
            post: function (KernelContract $kernel, array $params, ?int $exitCode, ?Throwable $exception) {
                $scope = Context::storage()->scope();
                if (!$scope) {
                    return;
                }

                $span = Span::fromContext($scope->context());

                if ($exitCode !== 0) {
                    $span->setStatus(StatusCode::STATUS_ERROR);
                }

                $this->endSpan($span, $exception);
            }
        );
    }
}