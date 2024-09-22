<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\foundation;

use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\PostHookTrait;
use think\App as FoundationalApplication;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\ThinkHook;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\ThinkHookTrait;
//use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\watchers\CacheWatcher;
//use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\watchers\ClientRequestWatcher;
//use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\watchers\ExceptionWatcher;
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
            post: function (FoundationalApplication $application, array $params, mixed $returnValue, ?Throwable $exception) {
                $application->bind('OpenTelemetry\API\Instrumentation\CachedInstrumentation', $this->instrumentation);
//                $this->registerWatchers($application, new CacheWatcher());
//                $this->registerWatchers($application, new ClientRequestWatcher($this->instrumentation));
//                $this->registerWatchers($application, new ExceptionWatcher());
                $this->registerWatchers($application, new LogWatcher($this->instrumentation));
                $this->registerWatchers($application, new QueryWatcher($this->instrumentation));
            }
        );
    }

    private function registerWatchers(FoundationalApplication $app, Watcher $watcher): void
    {
        $watcher->register($app);
    }
}