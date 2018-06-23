<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Verify;

use SilverStripe\ORM\DataExtension;
use PhpTek\Verifiable\Verify\ChainpointProof;

/**
 * By attaching this extension to any {@link DataObject} subclass and declaring a
 * $verifiable_fields array in YML config, all subsequent database writes will
 * be passed through here via {@link $this->onAfterWrite()};
 *
 * This {@link DataExtension} also provides a single field to which all verified
 * and verifiable chainpoint proofs are stored in a queryable JSON-aware field.
 *
 * @todo Store the hash function used and if subsequent verifications
 *       fail because differing hash functions are used, throw an exception.
 */
class VerifiableExtension extends DataExtension
{
    /**
     * Declares a JSON-aware {@link DBField} where all chainpoint proofs are stored.
     *
     * @var array
     * @config
     */
    private static $db = [
        'Proof' => ChainpointProof::class,
    ];

    /**
     * Field values will be hashed and committed to the current backend.
     *
     * @var array
     * @config
     */
    private static $verifiable_fields = [];

    /**
     * After each write, the desired data is compiled into a string
     * and submitted as a hash to the current backend.
     *
     * Once written, we poll the backend to receive the chainpoint proof
     * which we'll need for subsequent verification checks made against the
     * backend.
     *
     * If only the "Proof" field has been written-to, this should not constitute
     * a write that we need to do anything with, and is therefore ignored.
     *
     * @return mixed null | void
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        // Skip queueing-up a verification process if this is a "create" write
        if (!$this->getOwner()->exists()) {
            return;
        }

        // Skip queueing-up another verification process if only "Proof" is modified
        if ($this->getOwner()->isChanged('Proof')) {
            return;
        }

        $verifiable = $this->normaliseData();

        if (count($verifiable)) {
            $this->verifiableService->write($verifiable);
            $this->verifiableService->queueVerification($this->getOwner());
        }
    }

    /**
     * Normalise this model's data so it's suited to being hashed.
     *
     * @return array
     */
    public function normaliseData() : array
    {
        $fields = $this->getOwner()->config()->get('verifiable_fields');
        $verifiable = [];

        foreach ($fields as $field) {
            $verifiable[] = (string) $this->getOwner()->getField($field);
        }

        return $verifiable;
    }

    /**
     * Central to the whole package, this method is passed an array of fields
     * and their values, hashes them and will check that a chainpoint proof
     * exists in the local database. If unsuccessful, we return false.
     * Otherwise, we continue and consult the backend for the same proof. If one
     * is found both locally and in the backed, then the supplied data is said to
     * be verified. Note: See the $strict param to skip the local proof check.
     *
     * @param  array  $data   An array of data to verify against the current backend.
     * @param  bool   $strict True by default; That-is both the local database and
     *                        the backend are consulted for a valid proof. If set
     *                        to false, we bypass the local check and just consult
     *                        the backend directly.
     * @return bool           True if the backend verifies the proof, false otherwise.
     */
    public function verify(array $data, bool $strict = true) : bool
    {
        $hash = $this->verifiableService->hash($data);
        $proof = $this->getOwner()->dbObject('Proof');

        // 1). Get the locally stored chainpoint proof
        if (!$proof->match($hash)) {
            return false;
        }

        // 2). Send the local proof to the backend for verification
        if (!$this->verificationService->verify($proof)) {
            return false;
        }

        // 3). Verification complete
        return true;
    }

}
