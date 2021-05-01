<?php
namespace knivey\cmdr;


class Cmd
{
    public Args $cmdArgs;

    public function __construct(
        public string $command,
        public $method,
        public array $preArgs,
        public array $postArgs,
        public string $syntax,
        public array $opts
    )
    {
        if(!is_callable($this->method)) {
            throw new \Exception("Method argument to Cmd isn't callable (" . print_r($method, 1) .")");
        }
        $this->cmdArgs = new Args($syntax, $opts);
    }
}
