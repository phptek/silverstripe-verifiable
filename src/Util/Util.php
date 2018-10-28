<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Util;

use SilverStripe\Dev\Backtrace;
use SilverStripe\Control\Director;

/**
 * Some utility routines.
 */
class Util
{
    /**
     * Is the current request, one from a the test runner?
     *
     * @return bool
     * @todo   Use this as the basis of Injector-backed stubbing
     */
    public static function is_running_test() : bool
    {
        $trace = Backtrace::backtrace(true, true);

        // Test for CLI SAPI first, so we don't waste time during legit TTW writes
        return Director::is_cli() && stristr($trace, 'PHPUnit') !== false;
    }

}
