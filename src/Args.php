<?php
namespace knivey\cmdr;


use knivey\cmdr\exceptions\OptNotFound;
use knivey\cmdr\exceptions\ParseException;
use knivey\cmdr\exceptions\SyntaxException;
use \Ayesh\CaseInsensitiveArray\Strict as CIArray;

/**
 * Parse syntax and store arguments to command
 * Syntax rules:
 *  <arg> is a required arg
 *  <arg>... required multiword arg, must be last in list
 *  [arg] is an optional arg, all optionals must be at the end and no multiword arguments preceed
 *  [arg]... optional multiword arg, must be last in list
 * The arg name must not contain []<> or whitespace
 */
class Args implements \ArrayAccess, \Countable
{
    /**
     * @var Arg[] $args
     */
    protected array $args = Array();

    /**
     * @var Arg[] $parsed
     */
    protected array $parsed = Array();

    /**
     * @var CIArray $parsedOpts
     */
    protected CIArray $parsedOpts;

    protected CIArray $opts;
    /**
     * constructor.
     * @param string $syntax
     * @param Option[] $opts
     * @throws SyntaxException Will throw if syntax is invalid
     */
    function __construct(
        public string $syntax,
        array $opts = []
    ) {
        $this->opts = new CIArray();
        foreach ($opts as $opt)
            $this->opts[$opt->option] = $opt;
        $argv = array_filter(explode(' ', $syntax));
        if (count($argv) == 0) {
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

    protected function findOpt(string $name): bool {
        return isset($this->opts[$name]);
    }

    /**
     * Parse arguments given to a command using its syntax rules
     * @param string $msg
     * @return Args Returns a cloned object
     * @throws ParseException throws exception if required args arent provided
     */
    public function parse(string $msg) : Args {
        $this->parsedOpts = new CIArray();
        $msg = explode(' ', $msg);
        $msgb = [];
        foreach ($msg as $w) {
            if(str_contains($w, "=")) {
                list($lhs, $rhs) = explode("=", $w, 2);
            } else {
                $lhs = $w;
                $rhs = null;
            }
            if($this->findOpt($lhs))
                $this->parsedOpts[$lhs] = $rhs;
            else
                $msgb[] = $w;
        }
        $msg = implode(' ', $msgb);

        $this->parsed = [];
        foreach ($this->args as $k => $v)
            $this->parsed[$k] = clone $v;
        foreach ($this->parsed as &$arg) {
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
        return clone $this;
    }

    public function getOpts(): CIArray {
        return $this->parsedOpts;
    }

    /**
     * @throws OptNotFound
     */
    public function optEnabled($name): bool {
        if(!$this->findOpt($name))
            throw new OptNotFound($name);
        return isset($this->parsedOpts[$name]);
    }

    /**
     * @throws OptNotFound
     */
    public function getOpt($name) {
        if(!$this->optEnabled($name))
            return false;
        return $this->parsedOpts[$name] ?? true;
    }

    public function getArg(string $name): ?Arg {
        foreach ($this->parsed as &$arg) {
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
    public function offsetSet($offset, $value): void {
        return;
    }
    public function offsetExists($offset): bool {
        if(is_numeric($offset)) {
            return isset($this->parsed[$offset]) && $this->parsed[$offset]->val != null;
        }
        foreach ($this->parsed as &$arg) {
            if ($arg->name == $offset && $arg->val != null) {
                return true;
            }
        }
        return false;
    }
    public function offsetUnset($offset): void {
        return;
    }
    public function offsetGet($offset): ?string {
        if(is_numeric($offset)) {
            if(isset($this->parsed[$offset]) && $this->parsed[$offset]->val != null) {
                return $this->parsed[$offset]->val;
            }
            return null;
        }
        $arg = $this->getArg($offset);
        if($arg) {
            return $arg->val;
        }
        return null;
    }

    public function count(): int {
        return count($this->parsed);
    }
}

