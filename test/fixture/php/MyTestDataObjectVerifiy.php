<?php

namespace PhpTek\Verifiable\Test;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;
use PhpTek\Verifiable\Extension\VerifiableExtension;

/**
 * Stub class with a userland-declared `verify()` method
 */
class MyTestDataObjectVerify extends DataObject implements TestOnly
{
    private static $extensions = [
        VerifiableExtension::class,
    ];

    public function verify()
    {
        // noop
    }
}
