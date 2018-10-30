<?php

namespace PhpTek\Verifiable\Test;

use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Dev\TestOnly;
use PhpTek\Verifiable\Extension\VerifiableExtension;

/**
 * Class fixture for use in tests.
 */
class MyTestDataObject extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar',
        'Content' => 'HTMLText',
    ];
    private static $table_name = 'MyTestDataObject';
    private static $extensions = [
        VerifiableExtension::class,
        Versioned::class,
    ];
    private static $verifiable_fields = [
        'Title',
        'Content',
    ];
}
