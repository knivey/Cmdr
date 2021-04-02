<?php
namespace knivey\cmdr;


class Cmd
{
    public string $command;
    public string $syntax;
    public string $method;
    public array $callargs;

    public function __construct(string $command, string $method, array $callargs, string $syntax)
    {
        $this->command = $command;
        $this->method = $method;
        $this->syntax = $syntax;
        $this->callargs = $callargs;
    }
}
