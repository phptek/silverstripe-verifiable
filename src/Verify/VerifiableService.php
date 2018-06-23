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
 * @todo Handle rate-limiting by the Chainpoint network and by repeated access to this controller
 * @see https://github.com/chainpoint/chainpoint-node/wiki/Chainpoint-Node-API:-How-to-Create-a-Chainpoint-Proof
 */
class VerifiableService
{
    use Injectable;
    use Configurable;

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
     * Fetch a chainpoint proof for the passed $hashIdNode.
     *
     * @param  string $hashIdNode
     * @return string The JSON-LD chainpoint proof.
     */
    public function read(string $hashIdNode) : string
    {
        return $this->backend->getProof($hashIdNode);
    }

    /**
     * Verify the given JSON-LD chainpoint proof against the backend.
     *
     * @param  string $proof A JSON-LD chainpoint proof.
     * @return mixed
     */
    public function verify(string $proof)
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
        $func = $this->backend->hashFunc();
        $text = json_encode($data); // Simply used to stringify arrays of arbitary depth

        return hash($func, $text);
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
