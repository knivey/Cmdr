<?php
namespace knivey\cmdr;

class Validate {
    private static array $validators = [
        'int' => [self::class, 'int'],
        'uint' => [self::class, 'uint'],
        'number' => [self::class, 'number'],
        'bool' => [self::class, 'bool'],
        'options' => [self::class, 'options'],
    ];

    private static array $filters = [
        'int' => [self::class, 'asInt'],
        'uint' => [self::class, 'asInt'],
        'number' => [self::class, 'asNumber'],
        'bool' => [self::class, 'asInt'],
    ];

    /**
     * @param string $validator
     * @param mixed $value
     * @param array $args indexed by either arg order or [arg name => value] for named args
     * @throws \Exception
     */
    static public function custom(string $validator, mixed $value, array $args = []) : bool {
        if(!isset(self::$validators[$validator]))
            throw new \Exception("validator $validator is not valid");
        return call_user_func_array(self::$validators[$validator], [$value, ...$args]);
    }

    /**
     * @param string $validator
     * @param mixed $value
     * @param array $args
     * @return mixed
     */
    static public function filter(string $validator, mixed $value, array $args = []) : mixed {
        if(!isset(self::$filters[$validator]))
            return $value;
        $fargs = self::getCallablesArgs(self::$filters[$validator]);
        $args = array_intersect_key($args, array_flip($fargs));
        return call_user_func_array(self::$filters[$validator], [$value, ...$args]);
    }

    /**
     * Get an array of argument names for the callable
     * @param callable $callable
     * @return array|false
     */
    static private function getCallablesArgs(callable $callable) : array | false {
        try {
            $rf = new \ReflectionFunction(\Closure::fromCallable($callable));
            return array_map(fn($v) => $v->name, $rf->getParameters());
        } catch (\ReflectionException $e) {
            return false;
        }
    }

    static public function setValidator(string $name, callable $validator) {
        self::$validators[$name] = $validator;
    }

    static public function setFilter(string $name, callable $filter) {
        self::$filters[$name] = $filter;
    }

    /**
     * Only integer values that will cast correctly to int
     * @param $val
     * @param int $min
     * @param int $max
     * @return bool
     */
    static public function int($val, $min = PHP_INT_MIN, $max = PHP_INT_MAX) : bool {
        if(!is_numeric($val))
            return false;
        if(!is_int(0+$val))
            return false;
        if($val < $min)
            return false;
        if($val > $max)
            return false;
        return true;
    }

    static public function asInt($val) {
        return (int)$val;
    }

    /**
     * Only positive integer values that will cast correctly to int
     * @param $val
     * @param int $min
     * @param int $max
     * @return bool
     */
    static public function uint($val, $min = 0, $max = PHP_INT_MAX) : bool {
        if($min < 0) // why would anyone do this?
            $min = 0;
        return self::int($val, $min, $max);
    }

    /**
     * Any numeric number, int or float
     * @param $val
     * @param null $min
     * @param null $max
     * @return bool
     */
    static public function number($val, $min = null, $max = null) : bool {
        if(!is_numeric($val))
            return false;
        if($min !== null && $val < $min)
            return false;
        if($max !== null && $val > $max)
            return false;
        return true;
    }

    static public function asNumber($val) {
        return $val+0;
    }

    /**
     * Can be interpreted as a bool
     * @param $val
     * @return bool
     */
    static public function bool($val) : bool {
        if(is_numeric($val))
            return true;
        if(is_bool($val))
            return true;
        if(!is_string($val))
            return false;
        $val = strtolower($val);
        if(in_array($val, ["yes", "on", "true", "no", "off", "false"]))
            return true;
        return false;
    }

    /**
     * @param $val
     * @param $opts
     * @return bool
     */
    static public function options($val, array $opts) : bool {
        return in_array($val, $opts);
    }

    /**
     * Cast to bool
     * Accepts things like true false yes no 0 1 on off
     * @param $val
     * @return bool
     */
    static function asBool($val) : bool {
        if(is_numeric($val))
            return (bool)($val+0);
        $val = strtolower($val);
        if(in_array($val, ["yes", "on", "true"]))
            return true;
        return false;
    }
}