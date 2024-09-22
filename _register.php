<?php


declare(strict_types=1);

use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\ThinkInstrumentation;
use OpenTelemetry\SDK\Sdk;
use think\Env;

$app_path = realpath(\Composer\InstalledVersions::getRootPackage()['install_path']);
$env = new Env();
$env->load($app_path.DIRECTORY_SEPARATOR.'.env');
foreach ($env->get() as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
}
if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled(ThinkInstrumentation::NAME) === true) {
    return;
}

if (extension_loaded('opentelemetry') === false) {
    trigger_error('The opentelemetry extension must be loaded in order to autoload the OpenTelemetry ThinkPHP auto-instrumentation', E_USER_WARNING);
    return;
}

ThinkInstrumentation::register();