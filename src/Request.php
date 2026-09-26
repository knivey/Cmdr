<?php


namespace knivey\cmdr;


class Request
{
    public Args $args;
    public Cmd $cmd;
    /**
     * Extra arguments the command was called with (host context such as
     * ChatEvent / Client), set by Cmdr::call()/callPriv() when middleware
     * runs (stays empty on the no-middleware fast path). Middlewares may
     * mutate it before calling $next to change what the handler receives.
     * @var array<int, mixed>
     */
    public array $extraArgs = [];

    function __construct($args, $cmd)
    {
        $this->args = $args;
        $this->cmd = $cmd;
    }
}