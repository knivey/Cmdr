<?php


namespace knivey\cmdr;


class Option
{
    public function __construct(public string $option, public string $desc = "No description")
    {
    }
}