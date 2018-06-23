<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\ClassInfo;
use PhpTek\Verifiable\Exception\VerifiableBackendException;
use PhpTek\Verifiable\Exception\VerifiableServiceException;
use PhpTek\Verifiable\Job\BackendVerificationJob;

if (!class_exists(Symbiote\QueuedJobs\Services\QueuedJobService)) {
    throw new VerifiableServiceException('QueuedJobs module is not installed.');
}

/**
 * Service class that works as an intermediary between any data model and the
 * currently selected Merkle Tree storage backend.
 */
class VerifiableService
{
    use Injectable;
    use Configurable;

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
     * Write a hash of data as per the "verifiable_fields" confifg static on each
     * {@link DataObject}.
     *
     * @param  array  $data
     * @return boolean True if the write went through OK. False otherwise.
     */
    public function write(array $data) : boolean
    {
        return $this->backend->writeHash($this->hash($data));
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
        $backends = ClassInfo::implementorsOf('BackendProvider');

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
        $text = implode('', $data);

        return $func($text);
    }

    /**
     * Setup a {@link QueuedJob} to ping a backend and update the passed dataobject's
     * "Proof" field when a chainpoint proof has been generated.
     *
     * @param  DataObject $model The {@link DataObject} model subclass with a "Proof" field
     * @return void
     */
    public function queuePing(DataObject $model)
    {
        $job = BackendVerificationJob::create()->setObject($model);

        singleton(QueuedJobService::class)->queue($job);
    }

}
