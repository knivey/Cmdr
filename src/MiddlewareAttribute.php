<?php
namespace knivey\cmdr;

/**
 * Implement this on an Attribute class to have Cmdr attach an aliased
 * middleware to any command carrying that attribute. Hosts define pretty
 * attribute names (e.g. lolbot's #[Acl("botadmin")]) without cmdr knowing
 * anything about what the middleware does.
 */
interface MiddlewareAttribute
{
    /** Middleware alias name registered via Cmdr::aliasMiddleware() */
    public function name(): string;
    /** Arguments passed to the middleware after (Request $req, callable $next) */
    public function args(): array;
}
