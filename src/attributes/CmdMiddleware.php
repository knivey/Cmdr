<?php
namespace knivey\cmdr\attributes;

use knivey\cmdr\MiddlewareAttribute;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_FUNCTION | \Attribute::TARGET_METHOD)]
class CmdMiddleware implements MiddlewareAttribute
{
    /** @param string $name alias registered via Cmdr::aliasMiddleware()
     *  @param mixed ...$args forwarded to the middleware after ($req, $next) */
    public function __construct(public string $name, mixed ...$args)
    {
        $this->mwArgs = $args;
    }
    /** @var array<int, mixed> */
    public array $mwArgs = [];
    public function name(): string { return $this->name; }
    public function args(): array { return $this->mwArgs; }
}
