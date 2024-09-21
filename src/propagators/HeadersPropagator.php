<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\ThinkPHP\propagators;

use function assert;
use think\Request;
use OpenTelemetry\Context\Propagation\PropagationGetterInterface;

/**
 * @internal
 */
class HeadersPropagator implements PropagationGetterInterface
{
    public static function instance(): self
    {
        static $instance;

        return $instance ??= new self();
    }

    /** @psalm-suppress MoreSpecificReturnType */
    public function keys($carrier): array
    {
        assert($carrier instanceof Request);

        /** @psalm-suppress LessSpecificReturnStatement */
        return $carrier->header()?array_keys($carrier->header()):[];
    }

    public function get($carrier, string $key) : ?string
    {
        assert($carrier instanceof Request);
        return $carrier->header($key);
    }
}