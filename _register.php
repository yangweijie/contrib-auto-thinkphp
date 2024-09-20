<?php


declare(strict_types=1);

use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\ThinkInstrumentation;
use OpenTelemetry\SDK\Sdk;
use think\Env;

$env = new Env();
$env->load(realpath('..').DIRECTORY_SEPARATOR.'.env');
foreach ($env->get() as $key => $value) {
    putenv($key.'='.$value);
}
if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled(ThinkInstrumentation::NAME) === true) {
    return;
}

if (extension_loaded('opentelemetry') === false) {
    trigger_error('The opentelemetry extension must be loaded in order to autoload the OpenTelemetry Laravel auto-instrumentation', E_USER_WARNING);
    return;
}

ThinkInstrumentation::register();