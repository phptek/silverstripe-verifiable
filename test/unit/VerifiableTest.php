<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Test;

use SilverStripe\Dev\SapphireTest;
use PhpTek\Verifiable\Test\MyTestDataObjectNoVerify;
use PhpTek\Verifiable\Test\MyTestDataObjectVerify;
use PhpTek\Verifiable\Test\MyTestDataObjectSource01;
use SilverStripe\Core\Config\Config;

/**
 * Simple tests of the key methods found in our JSONText subclass `ChainpointProof`.
 */
class VerifiableTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $fixture_file = __DIR__ . '/../fixture/VerifiableTest.yml';

    // Exercise VerifiableExtension.php::getSourceMode()
    public function testSourceMode()
    {
        $this->assertEquals(1, MyTestDataObjectNoVerify::create()->getSourceMode());
        $this->assertEquals(2, MyTestDataObjectVerify::create()->getSourceMode());
    }

    // Exercise VerifiableExtension.php::getSource()
    public function testSourceProofIsSkipped()
    {
        // Check that the extension has been applied by virtue of the existance of a "Proof" field
        $test1 = MyTestDataObjectSource01::create();
        $this->assertArrayHasKey('Proof', $test1->getSchema()->databaseFields(MyTestDataObjectSource01::class));

        Config::nest();
        Config::modify()->update(MyTestDataObjectSource01::class, 'verifiable_fields', ['Proof']);

        // Ensure the "Proof" field-value will not become hashed
        $this->assertCount(2, MyTestDataObjectSource01::create()->getSource());

        Config::unnest();
    }

    // Exercises getSource()
    public function testGetSource()
    {
        // Check that HTML markup is removed
        $test2 = $this->objFromFixture('MyTestDataObjectSource02', 'markup-in-fields');
        $test2->setField('TEST1', '<p class="foo">Am I within markup?</p>');
        $test2->config()->update('verifiable_fields', ['TEST1']);
        $test2->write();
        $test2->publishSingle();
        // Assert that markup is present in the field itself
        $this->assertEquals('<p class="foo">Am I within markup?</p>', $test2->getField('TEST1'));
        // Asser that markup is NOT present in the source bound for hashing
        $this->assertEquals('Am I within markup?', $test2->getSource()[0]);
    }

    // Exercises verifiableFields()
    // Assert result is always an array
    public function testVerifiableFields()
    {
        // NULL
        $test1 = $this->objFromFixture('MyTestDataObjectSource02', 'null-fields');
        $this->assertInternalType('array', $test1->verifiableFields());
        $this->assertEquals([], $test1->verifiableFields());

        // Empty string
        $test2 = $this->objFromFixture('MyTestDataObjectSource02', 'no-fields');
        $this->assertInternalType('array', $test2->verifiableFields());
        $this->assertEquals([], $test2->verifiableFields());

        // One field
        $test3 = $this->objFromFixture('MyTestDataObjectSource02', 'a-field');
        $this->assertInternalType('array', $test3->verifiableFields());
        $this->assertEquals(['TEST1'], $test3->verifiableFields());

        // Some fields
        $test4 = $this->objFromFixture('MyTestDataObjectSource02', 'some-fields');
        $this->assertInternalType('array', $test4->verifiableFields());
        $this->assertEquals(['TEST1','TEST2'], $test4->verifiableFields());
    }

}

namespace PhpTek\Verifiable\Test;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;
use PhpTek\Verifiable\Extension\VerifiableExtension;

/**
 * Stub class without a userland-declared `verify()` method
 */
class MyTestDataObjectNoVerify extends DataObject implements TestOnly
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

/**
 * Stub class with a userland-declared `verify()` method
 */
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
use SilverStripe\ORM\DataObject;
use PhpTek\Verifiable\Extension\VerifiableExtension;

class MyTestDataObjectSource01 extends DataObject
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

namespace PhpTek\Verifiable\Test;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use PhpTek\Verifiable\Extension\VerifiableExtension;
use SilverStripe\Versioned\Versioned;

class MyTestDataObjectSource02 extends DataObject
{
    use Injectable;

    private static $db = [
        'TEST1' => 'Varchar',
    ];
    private static $table_name = 'MyTestDataObjectSource02';
    private static $extensions = [
        VerifiableExtension::class,
        Versioned::class,
    ];
}

namespace PhpTek\Verifiable\Test;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use PhpTek\Verifiable\Extension\VerifiableExtension;
use SilverStripe\Versioned\Versioned;

class MyTestDataObjectSource03 extends DataObject
{
    use Injectable;

    private static $table_name = 'MyTestDataObjectSource03';
    private static $db = [
        'TEST1' => 'Varchar',
    ];
    private static $extensions = [
        VerifiableExtension::class,
        Versioned::class,
    ];
}
