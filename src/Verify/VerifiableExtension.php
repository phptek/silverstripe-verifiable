<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Verify;

use SilverStripe\ORM\DataExtension;
use PhpTek\Verifiable\ORM\Fieldtype\ChainpointProof;

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
 * @todo Hard-code "Created" and "LastEdited" fields into "verifiable_fields"
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
     * After each write data from our verifiable_fields is compiled into a string
     * and submitted as a hash to the current backend.
     *
     * Once written, we poll the backend to receive the full chainpoint proof
     * which we'll need for subsequent verification checks, also made against the
     * same backend.
     *
     * If only the "Proof" field has been written-to, or no-data is found in the
     * verifiable_fields, this should not constitute a write that we need to do
     * anything with, and it's therefore skipped.
     *
     * @return void
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        // Skip queueing-up another verification process if only the "Proof" field
        // is modified
        $verifiableFields = $this->getOwner()->config()->get('verifiable_fields');
        $skipWriteCount = 0;

        foreach ($verifiableFields as $field) {
            if (!$this->getOwner()->getField($field)) {
                $skipWriteCount++;
            }
        }

        $verifiable = $this->normaliseData();
        $doWrite = count($verifiable) && (count($verifiableFields) !== $skipWriteCount);

        if ($doWrite && $proofData = $this->verifiableService->write($verifiable)) {
            // Save initial response
            $this->writeProof($proofData);
            // Fire off a job to periodically check if verification is complete
            // TODO: Use AsyncPHP and use a callback OR check if API has a callback endpoint it can call on our end (Tierion does)
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
     * Gateway method into the whole package's functionality.
     *
     * Passed an array of fields and their values, this method will hash them
     * and check that a chainpoint proof exists in the local database. If unsuccessful
     * we return false. Otherwise, we continue and consult the backend for the
     * same proof.
     *
     * If a matching proof is found both locally and in the backed, then the supplied
     * data is said to be verified. Note: See the $strict param to skip the
     * local proof check.
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
        if (!$proof->exists() || !$proof->match($hash)) {
            return false;
        }

        // TODO Verify data in "Proof" field

        // 2). Send the local proof to the backend for verification
        if (!$this->verificationService->verify($proof)) {
            return false;
        }

        // 3). Verification complete
        return true;
    }

    /**
     * Writes string data that is assumed to be JSON (as returned from a web-service
     * for example) and saved to this decorated object's "Proof" field.
     *
     * @param  string $proof
     * @return void
     */
    public function writeProof(string $proof)
    {
        $this->getOwner()->setField('Proof', $proof)->write();
    }

}
