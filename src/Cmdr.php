<?php
namespace knivey\cmdr;

use \Ayesh\CaseInsensitiveArray\Strict as CIArray;

class Cmdr
{
    /** @var Cmd[]  */
    public CIArray $cmds;
    /** @var Cmd[]  */
    public CIArray $privCmds;

	public function __construct()
    {
        $this->cmds = new CIArray();
        $this->privCmds = new CIArray();
    }

    function add(string $command, callable $method, array $preArgs = [], array $postArgs = [], string $syntax = '', array $opts = [], bool $priv = false, string $desc = "No description") {
        if (str_contains($command, '#')) {
            throw new \Exception('Command name cannot contain #');
        }
        if(!$priv) {
            if (isset($this->cmds[$command])) {
                throw new \Exception('Command already exists');
            }
            $this->cmds[$command] = new Cmd($command, $method, $preArgs, $postArgs, $syntax, $opts, $desc);
        } else {
            if (isset($this->privCmds[$command])) {
                throw new \Exception('Command already exists');
            }
            $this->privCmds[$command] = new Cmd($command, $method, $preArgs, $postArgs, $syntax, $opts, $desc);
        }
    }

    function get(string $command, string $text, $priv = false) : Request|false {
	    if(!$priv) {
            if (!isset($this->cmds[$command])) {
                return false;
            }
            $cmd = $this->cmds[$command];
        } else {
            if (!isset($this->privCmds[$command])) {
                return false;
            }
            $cmd = $this->privCmds[$command];
        }
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
        $privCmdAttr = $rf->getAttributes(attributes\PrivCmd::class);
        $pub = false;
        $priv = false;
        if (count($cmdAttr) > 0)
            $pub = true;
        if (count($privCmdAttr) > 0)
            $priv = true;
        if (!$pub && !$priv) {
            return;
        }
        if($pub)
            $cmdAttr = $cmdAttr[0]->newInstance();
        if($priv)
            $privCmdAttr = $privCmdAttr[0]->newInstance();
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
        if(isset($optionsAttr[0])) {
            $options = $optionsAttr[0]->newInstance()->options;
            foreach ($options as $opt) {
                $opts[] = new Option($opt, "No description");
            }
        }

        $optionAttr = $rf->getAttributes(attributes\Option::class);
        foreach ($optionAttr as $attr) {
            $opt = $attr->newInstance();
            foreach ($opt->options as $v) {
                $opts[] = new Option($v, $opt->desc);
            }
        }

        $desc = "No description";
        $descAttr = $rf->getAttributes(attributes\Desc::class);
        if(isset($descAttr[0]))
            $desc = $descAttr[0]->newInstance()->desc;

        if($pub) {
            foreach ($cmdAttr->args as $command) {
                $this->cmds[$command] = new Cmd($command, $f, $callWrapperPre, $callWrapperPost, $syntax, $opts, $desc);
            }
        }
        if($priv) {
            foreach ($privCmdAttr->args as $command) {
                $this->privCmds[$command] = new Cmd($command, $f, $callWrapperPre, $callWrapperPost, $syntax, $opts, $desc);
            }
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

    function callPriv(string $command, string $text, ...$extraArgs) {
        $req = $this->get($command, $text, priv: true);
        if(!$req)
            return false;
        return call_user_func_array($req->cmd->method, [...$req->cmd->preArgs, ...$req->cmd->postArgs, ...$extraArgs, $req]);
    }

}