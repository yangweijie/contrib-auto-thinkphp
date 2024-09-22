@echo off
set OTEL_PHP_AUTOLOAD_ENABLED=true
set OTEL_SERVICE_NAME=default

set OTEL_TRACES_EXPORTER=otlp
set OTEL_LOGS_EXPORTER=otlp
set OTEL_METRICS_EXPORTER=none

set OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
set OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:5080/api/default
set OTEL_PROPAGATORS=baggage,tracecontext
set OTEL_EXPORTER_OTLP_HEADERS="Authorization=Basic OTE3NjQ3Mjg4QHFxLmNvbTo3bWxGejg3VEdQR2pLZUN1,stream-name=default"

@php think %*