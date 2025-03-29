<?php
namespace knivey\cmdr;


use knivey\cmdr\exceptions\BadArgName;
use knivey\cmdr\exceptions\OptNotFound;
use knivey\cmdr\exceptions\ParseException;
use knivey\cmdr\exceptions\SyntaxException;
use Ayesh\CaseInsensitiveArray\Strict as CIArray;

/**
 * Parse syntax and store arguments to command
 * Syntax rules:
 *  <arg> is a required arg
 *  <arg>... required multiword arg, must be last in list
 *  [arg] is an optional arg, all optionals must be at the end and no multiword arguments preceded
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
     * Options that were found in parsing command
     * @var CIArray<Option>
     */
    protected CIArray $parsedOpts;

    /**
     * Possible options
     * @var CIArray<Option>
     */
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

        $argv = [];
        $offset = 0; // for error position
        $mode = 0; // 0 arg, 1 whitespace
        while($offset < strlen($syntax)) {
            $m = '';
            //we use substr here because from phpdoc:
            //Using offset is not equivalent to passing substr($subject, $offset) to preg_match() in place of the subject string, because pattern can contain assertions such as ^, $ or (?<=x).
            if($mode == 1) {
                if(!preg_match("/^ +/", substr($syntax, $offset), $m))
                    break;
                $offset += strlen($m[0]);
                $mode = 0;
            }
            if($mode == 0) {
                if(!preg_match('/^(?:(<[^>]+>(?:\.\.\.)?)|(\[[^]]+](?:\.\.\.)?))/', substr($syntax, $offset), $m))
                    break;
                $offset += strlen($m[0]);
                $argv[] = $m[0];
                $mode = 1;
            }
        }
        if(strlen($syntax) > $offset) {
            throw new SyntaxException("Invalid syntax: junk detected: \"$syntax\" at offset: $offset");
        }
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

            if (preg_match('/\[([^]]+)](\.\.\.)?/', $a, $m)) {
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
     * Check if an option is settable
     * @param string $name
     * @return bool
     */
    protected function findOpt(string $name): bool {
        return isset($this->opts[$name]);
    }

    /**
     * Parse arguments given to a command using its syntax rules
     * @param string $msg
     * @return Args Returns a cloned object
     * @throws ParseException throws exception if required args aren't provided
     * @throws \Exception
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
                if(preg_match('/ *([^ ]+) */', $msg, $m)) {
                    $msg = substr($msg, strlen($m[0]));
                    $arg->val = $m[1];
                }
            }
            if($arg->validator != null) {
                if(!Validate::runValidation($arg->validator, $arg->val, $arg->validatorArgs))
                    throw new ParseException("argument \"$arg->name\" did not pass validation: $arg->syntax");
                $arg->val = Validate::runFilter($arg->validator, $arg->val, $arg->validatorArgs);
            }
        }
        return clone $this;
    }

    /**
     * Get all options passed to command
     * @return CIArray<Option>
     */
    public function getOpts(): CIArray {
        return clone $this->parsedOpts;
    }

    /**
     * Checks if an option was passed to the command
     * @param string $name
     * @return bool
     * @throws OptNotFound
     */
    public function optEnabled(string $name): bool {
        if(!$this->findOpt($name))
            throw new OptNotFound($name);
        return isset($this->parsedOpts[$name]);
    }

    /**
     * Gets the value of an option passed to a command, if the option was given no =val then it will be true.
     * If the option was not passed then return is false.
     * @throws OptNotFound
     */
    public function getOpt($name) {
        if(!$this->optEnabled($name))
            return false;
        return $this->parsedOpts[$name] ?? true;
    }

    /**
     * Gets an Arg if no parsing has been done yet, useful in testing
     * @param string $name
     * @return Arg|null
     */
    public function getUnparsedArg(string $name): ?Arg {
        foreach ($this->args as &$arg) {
            if ($arg->name == $name) {
                return clone $arg;
            }
        }
        return null;
    }

    /**
     * Gets an argument value after having parsed a command request, returns null if that arg wasn't passed.
     * @param string $name
     * @return Arg|null
     * @throws BadArgName
     */
    public function getArg(string $name): ?Arg {
        foreach ($this->parsed as &$arg) {
            if ($arg->name == $name) {
                if($arg->val === null || $arg->val === '') {
                    return null;
                }
                return $arg;
            }
        }
        throw new BadArgName("Argument $name hasn't been defined. Syntax: {$this->syntax}");
    }

    //Readonly
    public function offsetSet($offset, $value): void {
        return;
    }
    public function offsetExists($offset): bool {
        if(is_numeric($offset)) {
            return isset($this->parsed[$offset]) && ($this->parsed[$offset]->val !== null && $this->parsed[$offset]->val !== '');
        }
        foreach ($this->parsed as &$arg) {
            if ($arg->name == $offset && ($arg->val !== null && $arg->val !== '')) {
                return true;
            }
        }
        return false;
    }
    public function offsetUnset($offset): void {
        return;
    }

    /**
     * @throws BadArgName
     */
    public function offsetGet($offset): mixed {
        if(is_numeric($offset)) {
            if(isset($this->parsed[$offset])) {
                return $this->parsed[$offset]->val;
            }
            throw new BadArgName("Argument index $offset hasn't been defined. Syntax: {$this->syntax}");
        }
        $arg = $this->getArg($offset);
        return $arg?->val;
    }

    public function count(): int {
        return count($this->parsed);
    }
}

