<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP\Watchers;

use think\App;

abstract class Watcher
{
    /**
     * Register the watcher.
     */
    abstract public function register(App $app): void;
}