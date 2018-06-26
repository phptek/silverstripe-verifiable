<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Test;

use SilverStripe\Dev\SapphireTest;
use PhpTek\Verifiable\ORM\FieldType\ChainpointProof;

/**
 *
 */
class ChainpointProofTest extends SapphireTest
{

    public function testGetHashIdNode()
    {
        // Valid JSON proof
        $proofValid = file_get_contents(realpath(__DIR__) . '/../fixture/proof-valid.json');
        $proofField = ChainpointProof::create()->setValue($proofValid);

        $this->assertEquals('bd469a90-7922-11e8-91f0-01201f800553', $proofField->getHashIdNode());

        // Invalid: Missing hash_id_node
        $proofInValid = file_get_contents(realpath(__DIR__) . '/../fixture/proof-invalid-no-hashid.json');
        $proofField = ChainpointProof::create()->setValue($proofInValid);

        $this->assertEquals('', $proofField->getHashIdNode());
    }

    public function testGetHash()
    {
        // Valid JSON proof
        $proofValid = file_get_contents(realpath(__DIR__) . '/../fixture/proof-valid.json');
        $proofField = ChainpointProof::create()->setValue($proofValid);

        $this->assertEquals('611d8759d9de000cd7fd4abef8d95ee9f2571bc8b953f8efbba21b110e1bbf0e', $proofField->getHash());

        // Invalid: Missing hash
        $proofInValid = file_get_contents(realpath(__DIR__) . '/../fixture/proof-invalid-no-hash.json');
        $proofField = ChainpointProof::create()->setValue($proofInValid);

        $this->assertEquals('', $proofField->getHash());
    }

    public function testMatch()
    {
        // Valid JSON proof
        $proofValid = file_get_contents(realpath(__DIR__) . '/../fixture/proof-valid.json');
        $proofField = ChainpointProof::create()->setValue($proofValid);

        $this->assertTrue($proofField->match('611d8759d9de000cd7fd4abef8d95ee9f2571bc8b953f8efbba21b110e1bbf0e'));

        // Invalid: Mismatched hash passed
        $proofValid = file_get_contents(realpath(__DIR__) . '/../fixture/proof-valid.json');
        $proofField = ChainpointProof::create()->setValue($proofValid);

        $this->assertFalse($proofField->match('61d8759d9de000cd7fd4abef8d95ee9f2571bc8b953f8efbba21b110e1bbf0e'));
    }

}
