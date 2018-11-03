<?php

namespace PhpTek\Verifiable\Test;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;
use PhpTek\Verifiable\Extension\VerifiableExtension;

/**
 * Stub class without a userland-declared `verify()` method
 */
class MyTestDataObjectNoVerify extends DataObject implements TestOnly
{
    private static $table_name = 'MyTestDataObjectNoVerify';
    private static $extensions = [
        VerifiableExtension::class,
    ];
}
