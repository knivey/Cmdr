<?php
namespace knivey\cmdr;


class Arg {
    public bool $required;
    public bool $multiword;
    public string $name;
    public ?string $val = "";
    public function __construct(bool $required, string $name, bool $multiword)
    {
        $this->required = $required;
        $this->name = $name;
        $this->multiword = $multiword;
    }

    public function __toString()
    {
        return $this->val;
    }
}