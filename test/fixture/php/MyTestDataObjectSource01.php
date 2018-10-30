<?php

namespace PhpTek\Verifiable\Test;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;
use PhpTek\Verifiable\Extension\VerifiableExtension;

class MyTestDataObjectSource01 extends DataObject implements TestOnly
{
    private static $extensions = [
        VerifiableExtension::class,
    ];

    private static $verifiable_fields = [
        'Foo',
        'Bar',
        'Proof',
    ];
}
