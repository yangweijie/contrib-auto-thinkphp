<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

trait ThinkHookTrait
{
    private static ThinkHook $instance;

    protected function __construct(
        protected CachedInstrumentation $instrumentation,
    ) {
    }

    abstract public function instrument(): void;

    public static function hook(CachedInstrumentation $instrumentation): ThinkHook
    {
        /** @psalm-suppress RedundantPropertyInitializationCheck */
        if (!isset(self::$instance)) {
            /** @phan-suppress-next-line PhanTypeInstantiateTraitStaticOrSelf,PhanTypeMismatchPropertyReal */
            self::$instance = new self($instrumentation);
            self::$instance->instrument();
        }

        return self::$instance;
    }
}