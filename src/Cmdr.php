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

    function add(string $command, callable $method, array $callargs, string $syntax = '') {
        if (str_contains($command, '#')) {
            throw new \Exception('Command name cannot contain #');
        }
        $this->cmds[$command] = new Cmd($command, $method, $callargs, $syntax);
    }

    function get(string $command, string $text) : Request {
	    $cmd = $this->cmds[$command];
	    $args = new Args($cmd->syntax);
	    $args->parse($text);
	    return new Request($args, $cmd);
    }

    function call(string $command, string $text) {
	    $req = $this->get($command, $text);
	    call_user_func_array($req->cmd->method, [$req->cmd->callargs, $req]);
    }

}