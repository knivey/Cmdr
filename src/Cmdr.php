<?php
namespace knivey\cmdr;

use \Ayesh\CaseInsensitiveArray\Strict as CIArray;

class Cmdr
{
    public CIArray $cmds;

	public function __construct()
    {
        $this->cmds = new CIArray();
    }

    function add(string $command, callable $method, string $syntax = '') {
        if (str_contains($command, '#')) {
            throw new \Exception('Command name cannot contain #');
        }
        $this->cmds[$command] = new Cmd($command, $method, $syntax);
    }

    function get(string $command, string $text) : Request {
	    $cmd = $this->cmds[$command];
	    $args = new Args($cmd->syntax);
	    $args->parse($text);
	    return new Request($args, $cmd);
    }

}