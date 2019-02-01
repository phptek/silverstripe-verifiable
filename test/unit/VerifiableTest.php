<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use PhpTek\Verifiable\Test\MyTestDataObject;
use PhpTek\Verifiable\Test\MyTestDataObjectSource01;
use PhpTek\Verifiable\Test\MyTestDataObjectVerify;
use PhpTek\Verifiable\Test\MyTestDataObjectNoVerify;
use PhpTek\Verifiable\Backend\Chainpoint\Service;
use PhpTek\Verifiable\Extension\VerifiableExtension;

/**
 * Suite: VerifiableAdminControllerTest
 *
 * Deals with mocking backend service-responses and testing key logic
 * found in {@link VerifiableExtension}.
 */
class VerifiableTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        MyTestDataObject::class,
        MyTestDataObjectSource01::class,
        MyTestDataObjectVerify::class,
    ];

    protected static $fixture_file = __DIR__ . '/../fixture/yml/VerifiableTest.yml';

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
        // Setup
        $serviceStub = $this->createMock(Service::class);
        $serviceStub
            ->method('setExtra')
            ->willReturn(null);
        $serviceStub
            ->method('call')
            ->willReturn(file_get_contents(__DIR__ . '/../fixture/json/response-verified-matching.json'));

        // Check that HTML markup is removed
        $record = $this->objFromFixture(MyTestDataObject::class, 'markup-in-fields');
        $record->getExtensionInstance(VerifiableExtension::class)->service = $serviceStub;
        $record->setField('Content', '<p class="foo">Am I within markup?</p>');
        $record->write();
        $record->publishSingle();
        // Assert that markup is present in the field itself
        $this->assertEquals('<p class="foo">Am I within markup?</p>', $record->getField('Content'));
        // Assert that markup is NOT present in the source bound for hashing
        $this->assertEquals('Am I within markup?', $record->getSource()[1]); // 0 => Title, 1 = >Content
    }

    // Exercises verifiableFields()
    // Assert result is always an array
    public function testVerifiableFields()
    {
        // NULL
        $test1 = $this->objFromFixture(MyTestDataObject::class, 'null-fields');
        $this->assertInternalType('array', $test1->verifiableFields());
        $this->assertEquals([], $test1->verifiableFields());

        // Empty string
        $test2 = $this->objFromFixture(MyTestDataObject::class, 'no-fields');
        $this->assertInternalType('array', $test2->verifiableFields());
        $this->assertEquals([], $test2->verifiableFields());

        // One field
        $test3 = $this->objFromFixture(MyTestDataObject::class, 'a-field');
        $this->assertInternalType('array', $test3->verifiableFields());
        $this->assertEquals(['TEST1'], $test3->verifiableFields());

        // Some fields
        $test4 = $this->objFromFixture(MyTestDataObject::class, 'some-fields');
        $this->assertInternalType('array', $test4->verifiableFields());
        $this->assertEquals(['TEST1','TEST2'], $test4->verifiableFields());
    }
}
