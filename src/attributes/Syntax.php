<?php


namespace knivey\cmdr\attributes;

#[\Attribute]
class Syntax
{
    public function __construct(public string $syntax)
    {
    }
}