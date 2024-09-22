<?php

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP\watchers;

use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\foundation\log\Formatter;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\API\Logs\Map\Psr3;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\Context;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use think\App;
use think\log\Channel;
use function OpenTelemetry\Instrumentation\hook;

class LogWatcher extends Watcher
{
    static $cache = [];

    public function __construct(
        private CachedInstrumentation $instrumentation,
    ) {
    }

    /** @psalm-suppress UndefinedInterfaceMethod */
    public function register(App $app): void
    {
        hook(Channel::class,'record', post: function (Channel $object, array $params, ?Channel $return, ?Throwable $exception)
        {
            $instrumentation = $this->instrumentation;
            $body = $params[0] ?? '';
            $level = $params[1];
            if(in_array($level, ['log', 'sql'])){
                $level = $level == 'log'? 'debug' : 'info';
            }
            $context = $params[2] ?? [];
            $context['create_time'] = date('Y-m-d H:i:s');
            if($exception){
                $context['exception'] = $exception;
            }
            $spanContext = Context::getCurrent();
            $span = Span::fromContext($spanContext)->getContext();

            if ($span->isValid()) {
                $context['traceId'] = $span->getTraceId();
                $context['spanId'] = $span->getSpanId();
            }
            $record = (new LogRecord($body))
                ->setSeverityText($params[1])
                ->setSeverityNumber(Psr3::severityNumber($level))
                ->setAttributes(Formatter::format($context));
            $instrumentation->logger()->emit($record);
            return $object;
        });
    }

    /**
     * @see https://www.php.net/manual/en/function.class-uses.php#112671
     */
    private static function class_uses_deep(object $class): array
    {
        $traits = [];

        // Get traits of all parent classes
        do {
            $traits = array_merge(class_uses($class, false), $traits);
        } while ($class = get_parent_class($class));

        // Get traits of all parent traits
        $traitsToSearch = $traits;
        while (!empty($traitsToSearch)) {
            $newTraits = class_uses(array_pop($traitsToSearch), false);
            $traits = array_merge($newTraits, $traits);
            $traitsToSearch = array_merge($newTraits, $traitsToSearch);
        };

        foreach ($traits as $trait => $same) {
            $traits = array_merge(class_uses($trait, false), $traits);
        }

        return array_unique($traits);
    }
}