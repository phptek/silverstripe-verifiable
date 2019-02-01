<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

use SilverStripe\Dev\SapphireTest;
use PhpTek\Verifiable\ORM\FieldType\ChainpointProof;

/**
 * Simple tests of the key methods found in our JSONText subclass `ChainpointProof`.
 * @todo Add a test for isVerified()
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
            'good' => [
                'full' => ChainpointProof::create()->setValue(
                    file_get_contents(realpath(__DIR__) . '/../fixture/json/response-full.json')
                ),
                'init' => ChainpointProof::create()->setValue(
                    file_get_contents(realpath(__DIR__) . '/../fixture/json/response-initial.json')
                ),
                'pend' => ChainpointProof::create()->setValue(
                    file_get_contents(realpath(__DIR__) . '/../fixture/json/response-pending.json')
                ),
                'veri' => ChainpointProof::create()->setValue(
                    file_get_contents(realpath(__DIR__) . '/../fixture/json/response-verified.json')
                ),
                'v3' => ChainpointProof::create()->setValue(
                    file_get_contents(realpath(__DIR__) . '/../fixture/json/chainpoint-proof.json')
                ),
            ],
            'bad' => [
                'full-nohashidnode' => ChainpointProof::create()->setValue(
                    file_get_contents(realpath(__DIR__) . '/../fixture/json/response-full-nohashidnode.json')
                ),
            ]
        ];
    }

    public function testGetSubmittedAt()
    {
        $this->assertEquals('', $this->proofs['good']['full']->getSubmittedAt());
        $this->assertEquals('2018-06-30T02:07:56Z', $this->proofs['good']['init']->getSubmittedAt());
        $this->assertEquals('', $this->proofs['good']['pend']->getSubmittedAt());
        $this->assertEquals('2018-07-15T09:14:47Z', $this->proofs['good']['veri']->getSubmittedAt());
    }

    public function testGetHash()
    {
        $this->assertEquals('', $this->proofs['good']['full']->getHash());
        $this->assertEquals(
            'af0c70d03b6427d744e46b820a09db704709852bd4614735efc1394160158ba2',
            $this->proofs['good']['init']->getHash()
        );
        $this->assertEquals('', $this->proofs['good']['pend']->getHash());
        $this->assertEquals(
            'e305bc4fbc91da2b6e140da27cfdf6b880fcd5fa71e84eeb96c16ae4b7110cd1',
            $this->proofs['good']['veri']->getHash()
        );
    }

    public function testGetHashIdNodeGood()
    {
        $this->assertCount(1, $this->proofs['good']['full']->getHashIdNode());
        $this->assertEquals('853b40a0-880f-11e8-9148-010f8d091124', $this->proofs['good']['full']->getHashIdNode()[0]);
        $this->assertCount(1, $this->proofs['good']['pend']->getHashIdNode());
        $this->assertEquals('853b40a0-880f-11e8-9148-010f8d091124', $this->proofs['good']['pend']->getHashIdNode()[0]);
        $this->assertCount(1, $this->proofs['good']['init']->getHashIdNode());
        $this->assertEquals('67e70270-7c0a-11e8-8fef-0105a8ad42dd', $this->proofs['good']['init']->getHashIdNode()[0]);
        $this->assertCount(1, $this->proofs['good']['veri']->getHashIdNode());
        $this->assertEquals('853b40a0-880f-11e8-9148-010f8d091124', $this->proofs['good']['veri']->getHashIdNode()[0]);
    }

    public function testGetHashIdNodeBad()
    {
        $this->assertCount(0, $this->proofs['bad']['full-nohashidnode']->getHashIdNode());
    }

    public function testGetStatus()
    {
        $this->assertEmpty($this->proofs['good']['full']->getStatus());
        $this->assertEmpty($this->proofs['good']['init']->getStatus());
        $this->assertEmpty($this->proofs['good']['pend']->getStatus());
        $this->assertEquals('verified', $this->proofs['good']['veri']->getStatus());
    }

    public function testGetProof()
    {
        $this->assertContains('eJyVVkFvpEcRhX/Aj+C4XldXd1d3+7', $this->proofs['good']['full']->getProof());
        $this->assertEquals('', $this->proofs['good']['init']->getProof());
        $this->assertContains(
            'eJyNU82OHDUQ5hHyEBwzO1W2y3b1aSVegVMuo7JdZlpaZkb',
            $this->proofs['good']['pend']->getProof()
        );
        $this->assertEquals('', $this->proofs['good']['veri']->getProof());
    }

    public function testGetProofJson()
    {
        $this->assertNotNull(json_decode($this->proofs['good']['full']->getProofJson()));
        $this->assertEquals('', $this->proofs['good']['init']->getProofJson());
        $this->assertNotNull(json_decode($this->proofs['good']['pend']->getProofJson()));
        $this->assertEquals('', $this->proofs['good']['veri']->getProofJson());
    }

    public function testGetAnchors()
    {
        $this->assertCount(0, $this->proofs['good']['full']->getAnchors());
        $this->assertCount(0, $this->proofs['good']['init']->getAnchors());
        $this->assertCount(0, $this->proofs['good']['pend']->getAnchors());
        $this->assertCount(2, $this->proofs['good']['veri']->getAnchors());
        $this->assertCount(1, $this->proofs['good']['v3']->getAnchors('cal'));
        $this->assertCount(1, $this->proofs['good']['v3']->getAnchors('btc'));
        $this->assertCount(1, $this->proofs['good']['v3']->getAnchors('foo')); // default!!
    }

    public function testGetAnchorsComplete()
    {
        $this->assertCount(2, $this->proofs['good']['full']->getAnchorsComplete());
        $this->assertCount(0, $this->proofs['good']['init']->getAnchorsComplete());
        $this->assertCount(1, $this->proofs['good']['pend']->getAnchorsComplete());
        $this->assertCount(0, $this->proofs['good']['veri']->getAnchorsComplete());
    }

    public function testIsFull()
    {
        $this->assertTrue($this->proofs['good']['full']->isFull());
        $this->assertFalse($this->proofs['good']['init']->isFull());
        $this->assertFalse($this->proofs['good']['pend']->isFull());
        $this->assertFalse($this->proofs['good']['veri']->isFull());
    }

    public function testIsPending()
    {
        $this->assertFalse($this->proofs['good']['full']->isPending());
        $this->assertFalse($this->proofs['good']['init']->isPending());
        $this->assertTrue($this->proofs['good']['pend']->isPending());
        $this->assertFalse($this->proofs['good']['veri']->isPending());
    }
}
