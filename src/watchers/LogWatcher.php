<?php

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP\Watchers;

class LogWatcher
{
    public function __construct(
        private CachedInstrumentation $instrumentation,
    ) {
    }

    /** @psalm-suppress UndefinedInterfaceMethod */
    public function register(App $app): void
    {
        /** @phan-suppress-next-line PhanTypeArraySuspicious */
        $app['events']->listen(LogWriter::class, [$this, 'recordLog']);
    }

    /**
     * Record a log.
     */
    public function recordLog($channel, LogWriter $log): void
    {
        $attributes = [
            'context' => json_encode(array_filter($log->context)),
        ];

        $logger = $this->instrumentation->logger();

        $record = (new LogWriter($channel, $log->message))
            ->setSeverityText($log->level)
            ->setSeverityNumber(Psr3::severityNumber($log->level))
            ->setAttributes($attributes);

        $logger->emit($record);
    }
}