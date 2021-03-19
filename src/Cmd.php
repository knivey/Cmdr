<?php
namespace knivey\cmdr;


class Cmd
{
    public string $command;
    public string $syntax;
    public string $method;

    public function __construct(string $command, string $method, string $syntax)
    {
        $this->command = $command;
        $this->method = $method;
        $this->syntax = $syntax;
    }
}
