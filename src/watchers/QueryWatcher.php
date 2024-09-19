<?php

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP\watchers;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\API\Logs\Map\Psr3;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;
use think\App;
use think\facade\Db;
use think\helper\Str;
class QueryWatcher extends Watcher
{
    public function __construct(
        private CachedInstrumentation $instrumentation,
    ) {
    }

    /** @psalm-suppress UndefinedInterfaceMethod */
    public function register(App $app): void
    {

        $database = $app->db->getConfig('connections.'.$app->db->getConfig('default').'.database');
        $driver = $app->db->getConfig('connections.'.$app->db->getConfig('default').'.type');
        $username = $app->db->getConfig('connections.'.$app->db->getConfig('default').'.username');
        Db::listen(function($sql, $runtime, $master) use($database, $driver, $username){
            $this->handle($sql, $runtime, $master, $database, $driver, $username);
        });
    }

    public function handle($sql, $runtime, $master, $database, $driver, $username){
        $nowInNs = (int) (microtime(true) * 1E9);
        $operationName = Str::upper(Str::before($sql, ' '));
        if (! in_array($operationName, ['SELECT', 'INSERT', 'UPDATE', 'DELETE'])) {
            $operationName = null;
        }

        /** @psalm-suppress ArgumentTypeCoercion */
        $span = $this->instrumentation->tracer()->spanBuilder('sql ' . $operationName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setStartTimestamp($this->calculateQueryStartTime($nowInNs, $runtime))
            ->startSpan();

        $attributes = [
            TraceAttributes::DB_SYSTEM => $driver,
            TraceAttributes::DB_NAME => $database,
            TraceAttributes::DB_OPERATION => $operationName,
            TraceAttributes::DB_USER => $username,
        ];

        $attributes[TraceAttributes::DB_STATEMENT] = $sql;
        $attributes['db.master'] = $master;
        /** @psalm-suppress PossiblyInvalidArgument */
        $span->setAttributes($attributes);
        $span->end($nowInNs);
    }

    private function calculateQueryStartTime(int $nowInNs, float $queryTimeMs): int
    {
        return (int) ($nowInNs - ($queryTimeMs * 1E6));
    }
}