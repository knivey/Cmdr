<?php


namespace knivey\cmdr\attributes;

#[\Attribute]
class Option
{
    public array $options;
    public string $desc;
    public function __construct(string|array $option, string $desc = "No description")
    {
        if(!is_array($option))
            $this->options = [$option];
        else
            $this->options = $option;
        $this->desc = $desc;
    }
}
