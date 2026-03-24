
This is a fork of https://github.com/opentelemetry-php/contrib-auto-cakephp

# OpenTelemetry CakePHP auto-instrumentation
Please read https://opentelemetry.io/docs/instrumentation/php/automatic/ for instructions on how to
install and configure the extension and SDK.

## Overview
Auto-instrumentation hooks are registered via composer, and spans will automatically be created for:
- Controller invoke
- ORM Select, Insert, Update, Delete, Count

## Configuration

The extension can be disabled via [runtime configuration](https://opentelemetry.io/docs/instrumentation/php/sdk/#configuration):

```shell
OTEL_PHP_DISABLED_INSTRUMENTATIONS=cakephp
```
