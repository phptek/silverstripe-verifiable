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
            'full' => ChainpointProof::create()->setValue(file_get_contents(realpath(__DIR__) . '/../fixture/full-proof-v3.json')),
            'part' => ChainpointProof::create()->setValue(file_get_contents(realpath(__DIR__) . '/../fixture/part-proof.json')),
            'verification' => ChainpointProof::create()->setValue(file_get_contents(realpath(__DIR__) . '/../fixture/verification-response.json')),
        ];
    }

    public function testGetSubmittedAt()
    {
        $this->assertEquals('2018-06-28T04:20:23Z', $this->proofs['full']->getSubmittedAt());
        $this->assertEquals('2018-06-30T02:07:56Z', $this->proofs['part']->getSubmittedAt());
        $this->assertEquals('2017-06-11T18:53:18Z', $this->proofs['verification']->getSubmittedAt());
    }

    public function testGetHash()
    {
        $this->assertEquals('1dd196890f5839c7d4e8800743901def9e1a669ab5d44d437685ad8c82d3cab8', $this->proofs['full']->getHash());
        $this->assertEquals('af0c70d03b6427d744e46b820a09db704709852bd4614735efc1394160158ba2', $this->proofs['part']->getHash());
        $this->assertEquals('112233ab12ab12ab12ab12ab12ab12ab12ab12ab12ab12ab11', $this->proofs['verification']->getHash());
    }

    // TODO Returns an array for full-proofs becuase there are x2 instances of the "hash_id_node" field
    public function testGetHashIdNode()
    {
        $this->assertCount(2, $this->proofs['full']->getHashIdNode());
        $this->assertEquals('93b97300-7a8a-11e8-a7a5-01a964383127', $this->proofs['full']->getHashIdNode()[0]);
        $this->assertEquals('93b97300-7a8a-11e8-a7a5-01a964383127', $this->proofs['full']->getHashIdNode()[1]);
        $this->assertCount(1, $this->proofs['part']->getHashIdNode());
        $this->assertEquals('67e70270-7c0a-11e8-8fef-0105a8ad42dd', $this->proofs['part']->getHashIdNode()[0]);
        $this->assertCount(1, $this->proofs['verification']->getHashIdNode());
        $this->assertEquals('3bce9920-4ed7-11e7-a7d0-3d6269e334e8', $this->proofs['verification']->getHashIdNode()[0]);
    }

    // TODO Returns an array for full-proofs becuase there are x2 instances of the "hash_id_node" field
    public function testGetStatus()
    {
        $this->assertEmpty($this->proofs['full']->getStatus());
        $this->assertEmpty($this->proofs['part']->getStatus());
        $this->assertEquals('verified', $this->proofs['verification']->getStatus());
    }

    public function testGetAnchors()
    {
        $this->assertCount(2, $this->proofs['full']->getAnchors());
        $this->assertCount(0, $this->proofs['part']->getAnchors());
        $this->assertCount(1, $this->proofs['verification']->getAnchors());
    }

    public function testIsFull()
    {
        $this->assertTrue($this->proofs['full']->isFull());
        $this->assertFalse($this->proofs['part']->isFull());
        $this->assertFalse($this->proofs['verification']->isFull());
    }

    public function testIsPending()
    {
        $this->assertFalse($this->proofs['full']->isPending());
        $this->assertFalse($this->proofs['part']->isPending());
        $this->assertFalse($this->proofs['verification']->isPending());
    }

}
