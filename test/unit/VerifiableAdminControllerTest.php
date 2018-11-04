<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use Dcentrica\Viz\ChainpointViz;
use PhpTek\Verifiable\Control\VerifiableAdminController;
use PhpTek\Verifiable\Backend\Chainpoint\Service;
use PhpTek\Verifiable\Extension\VerifiableExtension;
use PhpTek\Verifiable\Test\MyTestDataObject;

/**
 * Suite: VerifiableAdminControllerTest
 *
 * Deals with mocking backend service-responses and testing key logic
 * found in {@link VerifiableAdminController}.
 */
class VerifiableAdminControllerTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        MyTestDataObject::class,
    ];

    protected static $fixture_file = __DIR__ . '/../fixture/yml/VerifiableAdminControllerTest.yml';

    /**
     * Exercises the VerifiableAdminController::getStatus() method for failed statuses.
     */
    public function testGetStatusFailures()
    {
        $serviceStub = $this->createMock(Service::class);
        // Stub-in a canned verification response
        $serviceStub
            ->method('setExtra')
            ->willReturn(null);
        $serviceStub
            ->method('call')
            ->willReturn(file_get_contents(__DIR__ . '/../fixture/json/response-verified.json'));

        $vizServiceStub = $this->createMock(ChainpointViz::class);
        $vizServiceStub
            ->method('which')
            ->willReturn(true);

        $controller = VerifiableAdminController::create();
        $controller->service = $serviceStub;
        $controller->visualiser = $vizServiceStub;
        $data = [];

        // No proof
        $record = $this->objFromFixture(MyTestDataObject::class, 'has-null-proof');
        $this->assertEquals('Local Proof Not Found', $controller->getStatus($record, [], $data));

        // Empty proof
        $record = $this->objFromFixture(MyTestDataObject::class, 'has-no-proof');
        $this->assertEquals('Local Proof Not Found', $controller->getStatus($record, [], $data));

        // Initial proof
        $record = $this->objFromFixture(MyTestDataObject::class, 'has-initial-proof');
        $this->assertEquals('Initial', $controller->getStatus($record, [], $data));

        // Pending proof
        $record = $this->objFromFixture(MyTestDataObject::class, 'has-pending-proof');
        $this->assertEquals('Pending', $controller->getStatus($record, [], $data));

        // Broken full proof: (No hashidnode)
        $record = $this->objFromFixture(MyTestDataObject::class, 'has-full-with-broken-hashidnode-proof');
        $this->assertEquals('Local Components Invalid', $controller->getStatus($record, [], $data));

        // Broken full proof: (Hash mismatch - used completelty different stubbed repsonse for the fixture data)
        $record = $this->objFromFixture(MyTestDataObject::class, 'has-full-intact-proof');
        $this->assertEquals('Local Hash Invalid', $controller->getStatus($record, [], $data));
    }

    /**
     * Exercises the VerifiableAdminController::getStatus() method for success statuses.
     */
    public function testGetStatusSuccesses()
    {
        // Stub-in a suitable, matching and canned verification response used for
        // both $controller SUT and $record fixture
        $serviceStub = $this->createMock(Service::class);
        $serviceStub
            ->method('call')
            ->willReturn(file_get_contents(__DIR__ . '/../fixture/json/response-verified-matching.json'));
        // This smells: We're not actually exercising the check in getStatus() that re-hashes local data
        // and compares it against fixture data.
        // TODO: Find a way of properly spying on the real Service::hash() method and
        // do away with this canned response
        $serviceStub
            ->method('hash')
            ->willReturn('9344d44ee69cae8111dba30eb08b1dc82e6b83f4391c8667bd9237405dc84aac');
        $vizService = $this->createMock(ChainpointViz::class);
        $vizService
            ->method('which')
            ->willReturn(true);

        $controller = VerifiableAdminController::create();
        $controller->visualiser = $vizService;
        $controller->service = $serviceStub;

        // Intact proof
        $record = $this->objFromFixture(MyTestDataObject::class, 'has-full-intact-proof');
        $record->getExtensionInstance(VerifiableExtension::class)->service = $serviceStub;
        $this->assertEquals('Verified', $controller->getStatus($record, [], $data));
    }
}
