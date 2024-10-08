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
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\StrTrait;
class QueryWatcher extends Watcher
{
    use StrTrait;

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
            $this->handle($sql, (float)$runtime, $master, $database, $driver, $username);
        });
    }

    public function handle($sql, $runtime, $master, $database, $driver, $username){
        $nowInNs = (int) (microtime(true) * 1E9);
        $operationName = Str::upper(self::before($sql, ' '));
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
            TraceAttributes::DB_NAMESPACE => $database,
            TraceAttributes::DB_OPERATION_NAME => $operationName,
            'db.user' => $username,
        ];

        $attributes[TraceAttributes::DB_QUERY_TEXT] = $sql;
        $attributes['db_master'] = $master;
        if(str_contains($sql, 'CONNECT:')){
            $span->addEvent('database.connected');
        }
        /** @psalm-suppress PossiblyInvalidArgument */
        $span->setAttributes($attributes);
        $span->end($nowInNs);
    }

    private function calculateQueryStartTime(int $nowInNs, float $queryTimeMs): int
    {
        return (int) ($nowInNs - ($queryTimeMs * 1E6));
    }
}