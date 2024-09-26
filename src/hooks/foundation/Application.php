<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\foundation;

use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\PostHookTrait;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\Reflect;
use OpenTelemetry\SemConv\ResourceAttributes;
use OpenTelemetry\SemConv\TraceAttributes;
use think\App as FoundationalApplication;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\ThinkHook;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\ThinkHookTrait;
//use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\watchers\CacheWatcher;
//use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\watchers\ClientRequestWatcher;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\watchers\LogWatcher;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\watchers\QueryWatcher;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\watchers\Watcher;
use function OpenTelemetry\Instrumentation\hook;
use Throwable;

class Application implements ThinkHook
{
    use ThinkHookTrait;
    use PostHookTrait;

    public function instrument(): void
    {
        hook(
            FoundationalApplication::class,
            'initialize',
            pre: function (FoundationalApplication $kernel, array $params, string $class, string $function, ?string $filename, ?int $lineno) {
                $builder = $this->instrumentation
                    ->tracer()
                    ->spanBuilder('app initialize')
                    ->setSpanKind(SpanKind::KIND_PRODUCER)
                    ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
                    ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINENO, $lineno);

                $parent = Context::getCurrent();
                $span = $builder->startSpan();
                Context::storage()->attach($span->storeInContext($parent));
            },
            post: function (FoundationalApplication $application, array $params, mixed $returnValue, ?Throwable $exception) {

                $application->bind('think\exception\Handle', exception\Handle::class);
                $this->registerWatchers($application, new LogWatcher($this->instrumentation));
                $this->registerWatchers($application, new QueryWatcher($this->instrumentation));

                $scope = Context::storage()->scope();
                if (!$scope) {
                    return;
                }

                $span = Span::fromContext($scope->context());

                $envName = Reflect::getClassProperty($application, 'envName');
                $deploy_env = (getenv('APP_DEBUG') == true || $envName != '') ?'test':'prod';
                $span->setAttribute(ResourceAttributes::DEPLOYMENT_ENVIRONMENT_NAME, $deploy_env);
                $span->addEvent('app initialize finished', []);
                $this->endSpan($span, $exception);
            }
        );
    }

    private function registerWatchers(FoundationalApplication $app, Watcher $watcher): void
    {
        $watcher->register($app);
    }
}