# contrib-auto-thinkphp
OpenTelemetry auto-instrumentation for ThinkPHP

This is a read-only subtree split of https://github.com/open-telemetry/opentelemetry-php-contrib.

# OpenTelemetry ThinkPHP auto-instrumentation

Please read https://opentelemetry.io/docs/instrumentation/php/automatic/ for instructions on how to
install and configure the extension and SDK.

## Overview
Auto-instrumentation hooks are registered via composer, and spans will automatically be created.

## requirements

- [opentelemetry extension](https://pecl.php.net/package/opentelemetry)

## Configuration

The extension can be disabled via [runtime configuration](https://opentelemetry.io/docs/instrumentation/php/sdk/#configuration):

```shell
OTEL_PHP_DISABLED_INSTRUMENTATIONS=thinkphp
```

### Environment Variable

[环境变量](https://opentelemetry.io/docs/specs/otel/configuration/sdk-environment-variables/)


## context extend

`deployment.environment.name` 开发环境