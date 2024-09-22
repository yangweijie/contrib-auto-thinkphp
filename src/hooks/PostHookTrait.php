<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks;

use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;

trait PostHookTrait
{
    private function endSpan(Span $span, ?Throwable $exception = null): void
    {
//        var_dump(__FUNCTION__);
//        var_dump($span);
        if (!$span) {
            return;
        }

        if ($exception) {
            $span->recordException($exception, [
                TraceAttributes::EXCEPTION_ESCAPED => true,
            ]);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        }

        $span->end();
    }
}