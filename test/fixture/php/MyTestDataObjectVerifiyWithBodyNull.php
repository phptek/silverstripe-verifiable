<?php

namespace PhpTek\Verifiable\Test;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;
use PhpTek\Verifiable\Extension\VerifiableExtension;

/**
 * Stub class with a userland-declared `verify()` method that returns null.
 */
class MyTestDataObjectVerifiyWithBodyNull extends DataObject implements TestOnly
{
    private static $extensions = [
        VerifiableExtension::class,
    ];

    /**
     * Useful for
     * @return null;
     */
    public function verify()
    {
        return null;
    }
}
