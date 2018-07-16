<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Test;

use SilverStripe\Dev\SapphireTest;
use PhpTek\Verifiable\ORM\FieldType\ChainpointProof;

/**
 * There are three "types" of data model available out of the Tierion network.
 * Rightly or wrongly, at the moment we're modeling backend-specific stuff in the
 * "ChainpointProof" field, when in fact it should only be used t model full-proofs
 * and nothing else.
 */
class ChainpointProofTest extends SapphireTest
{
    /**
     * @var array
     */
    protected $proofs = [];

    public function setUp()
    {
        parent::setUp();

        $this->proofs = [
            'full' => ChainpointProof::create()->setValue(file_get_contents(realpath(__DIR__) . '/../fixture/response-full.json')),
            'init' => ChainpointProof::create()->setValue(file_get_contents(realpath(__DIR__) . '/../fixture/response-initial.json')),
            'pend' => ChainpointProof::create()->setValue(file_get_contents(realpath(__DIR__) . '/../fixture/response-pending.json')),
            'veri' => ChainpointProof::create()->setValue(file_get_contents(realpath(__DIR__) . '/../fixture/response-verified.json')),
        ];
    }

    public function testGetSubmittedAt()
    {
        $this->assertEquals('', $this->proofs['full']->getSubmittedAt());
        $this->assertEquals('2018-06-30T02:07:56Z', $this->proofs['init']->getSubmittedAt());
        $this->assertEquals('', $this->proofs['pend']->getSubmittedAt());
        $this->assertEquals('2018-07-15T09:14:47Z', $this->proofs['veri']->getSubmittedAt());
    }

    public function testGetHash()
    {
        $this->assertEquals('', $this->proofs['full']->getHash());
        $this->assertEquals('af0c70d03b6427d744e46b820a09db704709852bd4614735efc1394160158ba2', $this->proofs['init']->getHash());
        $this->assertEquals('', $this->proofs['pend']->getHash());
        $this->assertEquals('e305bc4fbc91da2b6e140da27cfdf6b880fcd5fa71e84eeb96c16ae4b7110cd1', $this->proofs['veri']->getHash());
    }

    public function testGetHashIdNode()
    {
        $this->assertCount(1, $this->proofs['full']->getHashIdNode());
        $this->assertEquals('853b40a0-880f-11e8-9148-010f8d091124', $this->proofs['full']->getHashIdNode()[0]);
        $this->assertCount(1, $this->proofs['pend']->getHashIdNode());
        $this->assertEquals('853b40a0-880f-11e8-9148-010f8d091124', $this->proofs['pend']->getHashIdNode()[0]);
        $this->assertCount(1, $this->proofs['init']->getHashIdNode());
        $this->assertEquals('67e70270-7c0a-11e8-8fef-0105a8ad42dd', $this->proofs['init']->getHashIdNode()[0]);
        $this->assertCount(1, $this->proofs['veri']->getHashIdNode());
        $this->assertEquals('853b40a0-880f-11e8-9148-010f8d091124', $this->proofs['veri']->getHashIdNode()[0]);
    }

    public function testGetStatus()
    {
        $this->assertEmpty($this->proofs['full']->getStatus());
        $this->assertEmpty($this->proofs['init']->getStatus());
        $this->assertEmpty($this->proofs['pend']->getStatus());
        $this->assertEquals('verified', $this->proofs['veri']->getStatus());
    }

    public function testGetProof()
    {
        $this->assertContains('eJyVVkFvpEcRhX/Aj+C4XldXd1d3+7', $this->proofs['full']->getProof());
        $this->assertEquals('', $this->proofs['init']->getProof());
        $this->assertContains('eJyNU82OHDUQ5hHyEBwzO1W2y3b1aSVegVMuo7JdZlpaZkb', $this->proofs['pend']->getProof());
        $this->assertEquals('', $this->proofs['veri']->getProof());
    }

    public function testGetAnchors()
    {
        $this->assertCount(0, $this->proofs['full']->getAnchors());
        $this->assertCount(0, $this->proofs['init']->getAnchors());
        $this->assertCount(0, $this->proofs['pend']->getAnchors());
        $this->assertCount(2, $this->proofs['veri']->getAnchors());
    }

    public function testGetAnchorsComplete()
    {
        $this->assertCount(2, $this->proofs['full']->getAnchorsComplete());
        $this->assertCount(0, $this->proofs['init']->getAnchorsComplete());
        $this->assertCount(1, $this->proofs['pend']->getAnchorsComplete());
        $this->assertCount(0, $this->proofs['veri']->getAnchorsComplete());
    }

    public function testIsFull()
    {
        $this->assertTrue($this->proofs['full']->isFull());
        $this->assertFalse($this->proofs['init']->isFull());
        $this->assertFalse($this->proofs['pend']->isFull());
        $this->assertFalse($this->proofs['veri']->isFull());
    }

    public function testIsPending()
    {
        $this->assertFalse($this->proofs['full']->isPending());
        $this->assertFalse($this->proofs['init']->isPending());
        $this->assertTrue($this->proofs['pend']->isPending());
        $this->assertFalse($this->proofs['veri']->isPending());
    }

}
