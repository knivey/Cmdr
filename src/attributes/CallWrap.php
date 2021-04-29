<?php


namespace knivey\cmdr\attributes;

#[\Attribute]
class CallWrap
{
    /**
     * This attribute tells Cmdr to use a wrapper to call the function, the wrapper should at least take
     * a callable for an argument additional arguments cant be specified.
     *
     * @param string|array $caller A callable function that will be expected in turn to call this function
     * @param array $preArgs Arguments to prepend the callable for this function
     * @param array $postArgs Arguments to append after the callable for this function
     */
    public function __construct(
        public string|array $caller,
        public array $preArgs = [],
        public array $postArgs = []
    )
    {
    }
}