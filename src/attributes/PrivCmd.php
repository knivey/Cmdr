<?php


namespace knivey\cmdr\attributes;

#[\Attribute]
class PrivCmd
{
    public array $args;
    public function __construct(string ...$args)
    {
        $this->args = $args;
    }
}