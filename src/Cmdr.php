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

    function add(string $command, callable $method, array $preArgs = [], array $postArgs = [], string $syntax = '', array $opts = []) {
        if (str_contains($command, '#')) {
            throw new \Exception('Command name cannot contain #');
        }
        if(isset($this->cmds[$command])) {
            throw new \Exception('Command already exists');
        }
        $this->cmds[$command] = new Cmd($command, $method, $preArgs, $postArgs, $syntax, $opts);
    }

    function get(string $command, string $text) : Request|false {
	    if(!isset($this->cmds[$command])) {
	        return false;
        }
	    $cmd = $this->cmds[$command];
	    $args = $cmd->cmdArgs->parse($text);
	    return new Request($args, $cmd);
    }

    function loadMethods(object $obj) {
	    $objRef = new \ReflectionObject($obj);
	    foreach($objRef->getMethods(\ReflectionMethod::IS_PUBLIC) as $rf) {
            $this->attrAddCmd($rf, [$obj, $rf->name]);
        }
    }

    protected function attrAddCmd($rf, $f) {
        $cmdAttr = $rf->getAttributes(attributes\Cmd::class);
        if (count($cmdAttr) == 0)
            return;
        $cmdAttr = $cmdAttr[0]->newInstance();
        $syntaxAttr = $rf->getAttributes(attributes\Syntax::class);
        $syntax = '';
        $callWrapper = null;
        $callWrapperPre = [];
        $callWrapperPost = [];
        if (isset($syntaxAttr[0])) {
            $sa = $syntaxAttr[0]->newInstance();
            $syntax = $sa->syntax;
        }

        $callWrapAttr = $rf->getAttributes(attributes\CallWrap::class);
        if ($cw = ($callWrapAttr[0]??null)?->newInstance()) {
            $callWrapperPre = [...$cw->preArgs, $f];
            $f = $cw->caller;
            $callWrapperPost = $cw->postArgs;
        }

        $optionsAttr = $rf->getAttributes(attributes\Options::class);
        $opts = [];
        if(isset($optionsAttr[0]))
            $opts = $optionsAttr[0]->newInstance()->options;


        foreach ($cmdAttr->args as $command) {
            $this->cmds[$command] = new Cmd($command, $f, $callWrapperPre, $callWrapperPost, $syntax, $opts);
        }
    }

    function loadFuncs() {
	    $funcs = get_defined_functions(true)["user"];
	    foreach ($funcs as $f) {
	        $rf = new \ReflectionFunction($f);
	        $this->attrAddCmd($rf, $f);
        }
    }

    function call(string $command, string $text, ...$extraArgs) {
	    $req = $this->get($command, $text);
	    if(!$req)
	        return false;
	    return call_user_func_array($req->cmd->method, [...$req->cmd->preArgs, ...$req->cmd->postArgs, ...$extraArgs, $req]);
    }

}