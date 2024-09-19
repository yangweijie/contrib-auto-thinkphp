<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\contracts\http;

use think\Http as KernelContract;
use think\Request;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\ThinkHook;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\ThinkHookTrait;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\hooks\PostHookTrait;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\propagators\HeadersPropagator;
use OpenTelemetry\Contrib\Instrumentation\ThinkPHP\propagators\ResponsePropagationSetter;
use think\Route\Rule;
use function OpenTelemetry\Instrumentation\hook;
use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Kernel implements ThinkHook
{
    use ThinkHookTrait;
    use PostHookTrait;

    public function instrument(): void
    {
        $this->hookHandle();
    }

    protected function hookHandle(): bool
    {
        return hook(
            KernelContract::class,
            'handle',
            pre: function (KernelContract $kernel, array $params, string $class, string $function, ?string $filename, ?int $lineno) {
                $request = ($params[0] instanceof Request) ? $params[0] : null;
                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = $this->instrumentation
                    ->tracer()
                    ->spanBuilder(sprintf('%s', $request?->method() ?? 'unknown'))
                    ->setSpanKind(SpanKind::KIND_SERVER)
                    ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
                    ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINENO, $lineno);
                $parent = Context::getCurrent();
                if ($request) {
                    /** @phan-suppress-next-line PhanAccessMethodInternal */
                    $parent = Globals::propagator()->extract($request, HeadersPropagator::instance());
                    $span = $builder
                        ->setParent($parent)
                        ->setAttribute(TraceAttributes::URL_FULL, $request->url())
                        ->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $request->method())
                        ->setAttribute(TraceAttributes::HTTP_REQUEST_BODY_SIZE, $request->header('Content-Length'))
                        ->setAttribute(TraceAttributes::URL_SCHEME, $request->scheme())
                        ->setAttribute(TraceAttributes::NETWORK_PROTOCOL_VERSION, $request->protocol())
                        ->setAttribute(TraceAttributes::NETWORK_PEER_ADDRESS, $request->ip())
                        ->setAttribute(TraceAttributes::URL_PATH, $this->httpTarget($request))
                        ->setAttribute(TraceAttributes::SERVER_ADDRESS, $this->httpHostName($request))
                        ->setAttribute(TraceAttributes::SERVER_PORT, $request->port())
                        ->setAttribute(TraceAttributes::CLIENT_PORT, $request->server('REMOTE_PORT'))
                        ->setAttribute(TraceAttributes::USER_AGENT_ORIGINAL, $request->server('HTTP_USER_AGENT'))
                        ->startSpan();
                    $request->attributes->set(SpanInterface::class, $span);
                } else {
                    $span = $builder->startSpan();
                }
                Context::storage()->attach($span->storeInContext($parent));

                return [$request];
            },
            post: function (KernelContract $kernel, array $params, ?Response $response, ?Throwable $exception) {
                $scope = Context::storage()->scope();
                if (!$scope) {
                    return;
                }
                $span = Span::fromContext($scope->context());

                $request = ($params[0] instanceof Request) ? $params[0] : null;
                $rule = $request?->rule();

                if ($request && $rule instanceof Rule) {
                    $route = $rule->getRoute();
                    $span->updateName("{$request->method()} /" . ltrim($route->uri, '/'));
                    $span->setAttribute(TraceAttributes::HTTP_ROUTE, $route->uri);
                }

                if ($response) {
                    if ($response->getStatusCode() >= 500) {
                        $span->setStatus(StatusCode::STATUS_ERROR);
                    }
                    $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());
                    $span->setAttribute(TraceAttributes::NETWORK_PROTOCOL_VERSION, $response->getProtocolVersion());
                    $span->setAttribute(TraceAttributes::HTTP_RESPONSE_BODY_SIZE, $response->headers->get('Content-Length'));

                    // Propagate server-timing header to response, if ServerTimingPropagator is present
                    if (class_exists('OpenTelemetry\Contrib\Propagation\ServerTiming\ServerTimingPropagator')) {
                        /** @phan-suppress-next-line PhanUndeclaredClassMethod */
                        $prop = new \OpenTelemetry\Contrib\Propagation\ServerTiming\ServerTimingPropagator();
                        /** @phan-suppress-next-line PhanAccessMethodInternal,PhanUndeclaredClassMethod */
                        $prop->inject($response, ResponsePropagationSetter::instance(), $scope->context());
                    }

                    // Propagate traceresponse header to response, if TraceResponsePropagator is present
                    if (class_exists('OpenTelemetry\Contrib\Propagation\TraceResponse\TraceResponsePropagator')) {
                        /** @phan-suppress-next-line PhanUndeclaredClassMethod */
                        $prop = new \OpenTelemetry\Contrib\Propagation\TraceResponse\TraceResponsePropagator();
                        /** @phan-suppress-next-line PhanAccessMethodInternal,PhanUndeclaredClassMethod */
                        $prop->inject($response, ResponsePropagationSetter::instance(), $scope->context());
                    }
                }

                $this->endSpan($exception);
            }
        );
    }

    private function httpTarget(Request $request): string
    {
//        $query = $request->get();
//        $question = $request->baseUrl() . $request->pathinfo() === '/' ? '/?' : '?';

        return $request->url(true);
    }

    private function httpHostName(Request $request): string
    {
        if (method_exists($request, 'host')) {
            return $request->host();
        }

        if (method_exists($request, 'getHost')) {
            return $request->getHost();
        }

        return '';
    }
}