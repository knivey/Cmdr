<?php


namespace knivey\cmdr\attributes;

#[\Attribute]
class Desc
{
    public function __construct(public string $desc)
    {
    }
}