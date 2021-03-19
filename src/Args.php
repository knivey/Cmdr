<?php
namespace knivey\cmdr;

/**
 * Takes $txt and creates (argc, argv) multiple spaces ignored
 * @param string $txt
 * @return array
 */
function niceArgs(string $txt) : array {
    $txt = explode(' ', $txt);
    $txt = array_filter($txt);
    return Array(count($txt), $txt);
}


/**
 * Parse syntax and store arguments to command
 * Syntax rules:
 *  <arg> is a required arg
 *  <arg>... required multiword arg, must be last in list
 *  [arg] is an optional arg, all optionals must be at the end and no multiword arguments preceed
 *  [arg]... optional multiword arg, must be last in list
 * The arg name must not contain []<> or whitespace
 */
class Args implements \ArrayAccess
{
    public string $syntax;
    /**
     * @var Arg[] $args
     */
    public array $args = Array();

    /**
     * CmdArgs constructor.
     * @param string $syntax
     * @throws SyntaxException Will throw if syntax isnt valid
     */
    function __construct(string $syntax)
    {
        $this->syntax = $syntax;
        list($argc, $argv) = niceArgs($syntax);
        if ($argc == 0) {
            return;
        }
        foreach ($argv as $k => $a) {
            $matched = false;
            if (preg_match('/<([^>]+)>(\.\.\.)?/', $a, $m)) {
                if($m[0] != $a) {
                    throw new SyntaxException("Invalid syntax: problem with $a");
                }
                //check that the last arg wasn't optional
                if($k != 0 && !$this->args[$k-1]->required) {
                    throw new SyntaxException("Invalid syntax: required argument given after optional");
                }
                //check that the last arg wasn't multiword
                if($k != 0 && $this->args[$k-1]->multiword) {
                    throw new SyntaxException("Invalid syntax: required argument given after multiword");
                }

                $mw = isset($m[2]);
                $this->args[$k] = new Arg(true, $m[1], $mw);
                $matched = true;
            }

            if (preg_match('/\[([^>]+)\](\.\.\.)?/', $a, $m)) {
                if($m[0] != $a) {
                    throw new SyntaxException("Invalid syntax: problem with $a");
                }
                //check that the last arg wasn't multiword
                if($k != 0 && $this->args[$k-1]->multiword) {
                    throw new SyntaxException("Invalid syntax: optional argument given after multiword");
                }

                $mw = isset($m[2]);
                $this->args[$k] = new Arg(false, $m[1], $mw);
                $matched = true;
            }
            if (!$matched) {
                throw new SyntaxException("Invalid syntax: unknown syntax for $a");
            }
        }
    }

    /**
     * @param string $msg
     * @throws ParseException throws exception if required args arent provided
     */
    public function parse(string $msg) {
        foreach ($this->args as &$arg) {
            if($arg->required && trim($msg) == '') {
                throw new ParseException("Missing a required arg: $arg->name");
            }
            if($arg->multiword) {
                $arg->val = $msg;
                $msg = '';
            } else {
                if(preg_match('/ ?+([^ ]+) ?+/', $msg, $m)) {
                    $msg = substr($msg, strlen($m[0]));
                    $arg->val = $m[1];
                }
            }
        }
    }

    public function getArg(string $name): ?Arg {
        foreach ($this->args as &$arg) {
            if ($arg->name == $name) {
                if($arg->val == null) {
                    return null;
                }
                return $arg;
            }
        }
        return null;
    }

    //Readonly
    public function offsetSet($offset, $value) {
        return;
    }
    public function offsetExists($offset) {
        if(is_numeric($offset)) {
            return isset($this->args[$offset]) && $this->args[$offset]->val != null;
        }
        foreach ($this->args as &$arg) {
            if ($arg->name == $offset && $arg->val != null) {
                return true;
            }
        }
        return false;
    }
    public function offsetUnset($offset) {
        return;
    }
    public function offsetGet($offset): ?string {
        if(is_numeric($offset)) {
            if(isset($this->args[$offset]) && $this->args[$offset]->val != null) {
                return $this->args[$offset]->val;
            }
            return null;
        }
        $arg = $this->getArg($offset);
        if($arg) {
            return $arg->val;
        }
        return null;
    }
}

