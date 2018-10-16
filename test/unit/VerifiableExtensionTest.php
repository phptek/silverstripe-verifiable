<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Test;

use SilverStripe\Dev\SapphireTest;
use PhpTek\Verifiable\Test\MyTestDataObjectNoValidate;
use PhpTek\Verifiable\Test\MyTestDataObjectVerify;
use PhpTek\Verifiable\Test\MyTestDataObjectSource01;
use SilverStripe\Core\Config\Config;

/**
 * Simple tests of the key methods found in our JSONText subclass `ChainpointProof`.
 */
class VerifiableExtensionTest extends SapphireTest
{
    protected $usesDatabase = false;

    // Exercise VerifiableExtension.php::sourceMode()
    public function testSourceMode()
    {
        $this->assertEquals(1, MyTestDataObjectNoValidate::create()->sourceMode());
        $this->assertEquals(2, MyTestDataObjectVerify::create()->sourceMode());
    }

    // Exercise VerifiableExtension.php::source()
    public function testSourceProofIsSkipped()
    {
        // Check that the extension has been applied by virtue of the existance of a "Proof" field
        $test = MyTestDataObjectSource01::create();
        $this->assertArrayHasKey('Proof', $test->getSchema()->databaseFields(MyTestDataObjectSource01::class));

        Config::nest();
        Config::modify()->update(MyTestDataObjectSource01::class, 'verifiable_fields', ['Proof']);

        // Ensure the "Proof" field-value will not become hashed
        $this->assertCount(2, MyTestDataObjectSource01::create()->source());

        Config::unnest();
    }
}

namespace PhpTek\Verifiable\Test;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;
use PhpTek\Verifiable\Extension\VerifiableExtension;

class MyTestDataObjectNoValidate extends DataObject implements TestOnly
{
    use Injectable;

    private static $extensions = [
        VerifiableExtension::class,
    ];
}

namespace PhpTek\Verifiable\Test;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;
use PhpTek\Verifiable\Extension\VerifiableExtension;

class MyTestDataObjectVerify extends DataObject implements TestOnly
{
    use Injectable;

    private static $extensions = [
        VerifiableExtension::class,
    ];

    public function verify()
    {
        // noop
    }
}

namespace PhpTek\Verifiable\Test;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use PhpTek\Verifiable\Extension\VerifiableExtension;

class MyTestDataObjectSource01 extends DataObject implements TestOnly
{
    use Injectable;

    private static $extensions = [
        VerifiableExtension::class,
    ];

    private static $verifiable_fields = [
        'Foo',
        'Bar',
        'Proof',
    ];
}
