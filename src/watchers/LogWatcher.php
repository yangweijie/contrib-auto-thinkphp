<?php

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP\watchers;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\API\Logs\Map\Psr3;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\Context;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use think\App;
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
//        var_dump($this->instrumentation->logger());
        /** @phan-suppress-next-line PhanTypeArraySuspicious */
        $pre = static function (LoggerInterface $object, array $params, string $class, string $function): array {
            var_dump($params);
            var_dump([$class, $function]);
            $id = spl_object_id($object);
            if (!array_key_exists($id, self::$cache)) {
                $traits = self::class_uses_deep($object);
                self::$cache[$id] = in_array(LoggerTrait::class, $traits);
            }
            var_dump(4444);
            if (self::$cache[$id] === true && $function !== 'log') {
                //LoggerTrait proxies all log-level-specific methods to `log`, which leads to double-processing
                //Not all psr-3 loggers use AbstractLogger, so we check for the trait directly
                return $params;
            }
            $instrumentation = $this->instrumentation;
            if ($function === 'log') {
                $level = $params[0];
                $body = $params[1] ?? '';
                $context = $params[2] ?? [];
            } else {
                $level = $function;
                $body = $params[0] ?? '';
                $context = $params[1] ?? [];
            }

            $record = (new API\LogRecord($body))
                ->setSeverityNumber(API\Map\Psr3::severityNumber($level))
                ->setAttributes(Formatter::format($context));
            $instrumentation->logger()->emit($record);
            return $params;
        };

        foreach (['log', 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $f) {
            hook(class: LoggerInterface::class, function: $f, pre: $pre);
        }
    }

    /**
     * Record a log.
     */
    public function handle(\think\event\LogRecord $logRecord): void
    {
//        var_dump(222);
//        var_export(func_get_args());
        $record = (new API\LogRecord($logRecord->message))
            ->setSeverityNumber(API\Map\Psr3::severityNumber($logRecord->type))
            ->setAttributes(Formatter::format($context));
        $this->instrumentation->logger()->emit($record);

//        $logger = $this->instrumentation->logger();
//        var_dump($logger);
//        $record = (new LogRecord($logRecord->message))
//            ->setSeverityText($logRecord->type)
//            ->setSeverityNumber(Psr3::severityNumber($logRecord->type));

//        $logger->emit($record);
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