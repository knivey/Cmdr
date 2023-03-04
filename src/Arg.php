<?php
namespace knivey\cmdr;


class Arg {
    public bool $required;
    public bool $multiword;
    public string $name;
    public string $syntax;
    public ?string $val = "";
    public ?string $validator = null;
    public array $validatorArgs = [];
    public function __construct(bool $required, string $name, bool $multiword)
    {
        $this->required = $required;
        $this->multiword = $multiword;
        $this->syntax = $name;
        $name = explode(":", $name, 2);
        $this->name = trim($name[0]);
        if(count($name) == 1) {
            return;
        }
        $validator = trim($name[1]);
        //handle optionals
        if(preg_match("@\(([^)]+)\)@", $validator, $m)) {
            $opts = array_map('trim', explode('|', $m[1]));
            $this->validator = 'options';
            $this->validatorArgs = ["opts"=>$opts];
            return;
        }
        $validator = array_values(array_filter(explode(' ', $validator)));
        if(!isset($validator[0]))
            return;
        $this->validator = array_shift($validator);
        foreach($validator as $arg) {
            $arg = explode("=", $arg, 2);
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