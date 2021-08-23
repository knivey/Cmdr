<?php
namespace knivey\cmdr;


class Arg {
    public bool $required;
    public bool $multiword;
    public string $name;
    public ?string $val = "";
    public ?string $validator = null;
    public array $validatorArgs = [];
    public function __construct(bool $required, string $name, bool $multiword)
    {
        $this->required = $required;
        $this->multiword = $multiword;
        $name = explode(":", $name, 1);
        $this->name = trim($name[0]);
        if(count($name) == 1) {
            return;
        }
        $validator = trim($name[1]);
        if(preg_match("@\(([^)]+)\)@", $validator[0], $m)) {
            $opts = array_map('trim', explode('|', $m[1]));
            $this->validator = 'options';
            $this->validatorArgs = ["opts"=>$opts];
            return;
        }
        $validator = array_filter(explode(' ', $validator));
        if(!isset($validator[0]))
            return;
        $this->validator = array_shift($validator);
        foreach($validator as $arg) {
            $arg = explode("=", $arg, 1);
            if(!isset($arg[1]))
                $arg[1] = true;
            $this->validatorArgs[$arg[0]] = $arg[1];
        }
    }

    public function __toString()
    {
        return $this->val;
    }
}