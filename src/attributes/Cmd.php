<?php


namespace knivey\cmdr\attributes;

#[\Attribute]
class Cmd
{
    public array $args;
    public function __construct(string ...$args)
    {
        $this->args = $args;
    }
}