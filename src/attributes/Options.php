<?php


namespace knivey\cmdr\attributes;

#[\Attribute]
class Options
{
    public array $options;
    public function __construct(string ...$options)
    {
        $this->options = $options;
    }
}
