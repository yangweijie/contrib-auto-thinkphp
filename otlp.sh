#!/usr/bin/bash

env OTEL_PHP_AUTOLOAD_ENABLED=true
env OTEL_SERVICE_NAME=default

env OTEL_TRACES_EXPORTER=otlp
env OTEL_LOGS_EXPORTER=otlp
env OTEL_METRICS_EXPORTER=none

env OTEL_PHP_DETECTORS=env,os,process
env OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
env OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:5080/api/default
env OTEL_PROPAGATORS=baggage,tracecontext
env OTEL_EXPORTER_OTLP_HEADERS="Authorization=Basic OTE3NjQ3Mjg4QHFxLmNvbTo3bWxGejg3VEdQR2pLZUN1,stream-name=default"

php think %*