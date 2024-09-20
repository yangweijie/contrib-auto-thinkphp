<?php

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP\watchers;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\API\Logs\Map\Psr3;
use think\App;

class LogWatcher extends Watcher
{
    public function __construct(
        private CachedInstrumentation $instrumentation,
    ) {
    }

    /** @psalm-suppress UndefinedInterfaceMethod */
    public function register(App $app): void
    {
        /** @phan-suppress-next-line PhanTypeArraySuspicious */
        $app->event->listenEvents(['LogRecord'=>['OpenTelemetry\Contrib\Instrumentation\ThinkPHP\watchers\LogWatcher']]);
    }

    /**
     * Record a log.
     */
    public function handle(\think\event\LogRecord $logRecord): void
    {
//        var_dump(222);
//        var_export(func_get_args());
        $logger = $this->instrumentation->logger();
        var_dump($logger);
        $record = (new LogRecord($logRecord->message))
            ->setSeverityText($logRecord->type)
            ->setSeverityNumber(Psr3::severityNumber($logRecord->type));

        $logger->emit($record);
    }
}