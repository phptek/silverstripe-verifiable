<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Verify;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\ClassInfo;
use PhpTek\Verifiable\Exception\VerifiableBackendException;
use PhpTek\Verifiable\Job\BackendVerificationJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use PhpTek\Verifiable\Backend\BackendProvider;
use SilverStripe\ORM\DataObject;

/**
 * Service class that works as an intermediary between any data model and the
 * currently selected Merkle Tree storage backend.
 *
 * @todo Handle rate-limiting by the Chainpoint network
 */
class VerifiableService
{
    use Injectable;
    use Configurable;

    /**
     * Represents a failed verification.
     *
     * @var string
     */
    const STATUS_FAILURE = 'FAIL';

    /**
     * Represents a passed verification.
     *
     * @var string
     */
    const STATUS_PASSED = 'PASS';

    /**
     * Represents a pending verification.
     *
     * @var string
     */
    const STATUS_PENDING = 'PENDING';

    /**
     * The hashing function to use.
     *
     * @var string
     * @see {@link $this->hash()}
     * @config
     */
    private static $hash_func = 'sha1';

    /**
     * @var BackendProvider
     */
    protected $backend;

    /**
     * @return void
     * @throws VerifiableBackendException
     */
    public function __construct()
    {
        $this->setBackend();
    }

    /**
     * Write a hash of data as per the "verifiable_fields" config static on each
     * {@link DataObject}.
     *
     * @param  array $data
     * @return mixed The result of this call to the backend.
     */
    public function write(array $data)
    {
        return $this->backend->writeHash([$this->hash($data)]);
    }

    /**
     * Fetch a chainpoint proof for the passed $hash.
     *
     * @param  string $hash
     * @return string The JSON-LD chainpoint proof.
     */
    public function read(string $hash) : string
    {
        return $this->backend->getProof($hash);
    }

    /**
     * Verify the given JSON-LD chainpoint proof against the backend.
     *
     * @param  string $proof A JSON-LD chainpoint proof.
     * @return bool
     */
    public function verify(string $proof) : bool
    {
        return $this->backend->verifyProof($proof);
    }

    /**
     * Gives us the current verification status of the given record. Takes into
     * account the state of the saved proof as well as by making a backend
     * verification call.
     *
     * @param  DataObject $record
     * @return string
     */
    public function getVerificationStatus(DataObject $record)
    {
        $proofData = $record->dbObject('Proof')->getStoreAsArray();
        $fieldData = $record->normaliseData();
        $proofIsComplete = $this->proofIsComplete($proofData);
        $proofIsPartial = $this->proofIsPartial($proofData);
        $proofIsVerified = $record->verify($fieldData, false) === true;

        switch (true) {
            case $proofIsComplete && $proofIsVerified:
                return self::STATUS_PASS;
            case $proofIsPartial:
                return self::STATUS_PENDING;
            case $proofIsComplete && !$proofIsVerified:
            default:
                return self::STATUS_FAILURE;
        }
    }

    /**
     * Does the passed data response represent a PARTIAL verification as far as
     * the local database is concerned?
     *
     * @param  array $data
     * @return bool
     */
    public function proofIsPartial(array $data) : bool
    {
        if (isset($data['anchors_complete']) && count($data['anchors_complete']) === 0) {
            return false;
        }

        return true;
    }

    /**
     * Does the passed data represent a FULL verification as far as the local database
     * is concerned?
     *
     * @param  array $data
     * @return bool
     */
    public function proofIsComplete(array $data) : bool
    {
        if (empty($data['anchors_complete']) || empty($data['anchors'])) {
            return false;
        }

        // "Full" means anchors to both Etheruem and Bitcoin blockchains
        return count($data['anchors']) === 3; // "cal" + "btc" + "eth"
    }

    /**
     * Set, instantiate and return a new Merkle Tree storage backend.
     *
     * @param  BackendProvider $provider Optional manually pased backend.
     * @return VerifiableService
     * @throws VerifiableBackendException
     */
    public function setBackend(BackendProvider $provider = null)
    {
        if ($provider) {
            $this->backend = $provider;

            return $this;
        }

        $namedBackend = $this->config()->get('backend');
        $backends = ClassInfo::implementorsOf(BackendProvider::class);

        foreach ($backends as $backend) {
            if (singleton($backend)->name() === $namedBackend) {
                $this->backend = Injector::inst()->create($backend);

                return $this;
            }
        }

        // Cannt continue without a legit backend
        throw new VerifiableBackendException('No backend found');
    }

    /**
     * @return BackendProvider
     */
    public function getBackend()
    {
        return $this->backend;
    }

    /**
     * Hashes the data passed into the $hash param.
     *
     * @param  array $data An array of data who's values should be hashed.
     * @return string      The resulting hashed data.
     * @todo               Take user input in the form of a digital signature
     */
    public function hash(array $data) : string
    {
        $func = $this->config()->get('hash_func');
        $text = json_encode($data); // Simply used to stringify arrays of arbitary depth

        return $func($text);
    }

    /**
     * Setup a {@link QueuedJob} to ping a backend and update the passed dataobject's
     * "Proof" field when a chainpoint proof has been generated.
     *
     * @param  DataObject $model The {@link DataObject} model subclass with a "Proof" field
     * @return void
     */
    public function queueVerification(DataObject $model)
    {
        $job = new BackendVerificationJob();
        $job->setObject($model);
        // Ping the backend 1 hour hence
        $time = date('Y-m-d H:i:s', time() + 3600);

        singleton(QueuedJobService::class)->queueJob($job, $time);
    }

}
