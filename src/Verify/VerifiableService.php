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
use PhpTek\Verifiable\Backend\BackendProvider;

/**
 * Service class that works as an intermediary between any data model and the
 * currently selected Merkle Tree storage backend.
 *
 * @todo Handle rate-limiting by the Chainpoint network and by repeated access to this controller
 * @todo Write a __call() method that sets nodes to the backend once, rather than in each backend method call
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
     *
     * @var array
     */
    protected $extra = [];

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
        // TODO: Is there a better way of doing this?
        $this->backend->setDiscoveredNodes($this->getExtra());

        return $this->backend->writeHash([$this->hash($data)]);
    }

    /**
     * Fetch a chainpoint proof for the passed $uuid.
     *
     * @param  string $uuid
     * @return string The JSON-LD chainpoint proof.
     */
    public function read(string $uuid) : string
    {
        // TODO: Is there a better way of doing this?
        $this->backend->setDiscoveredNodes($this->getExtra());

        return $this->backend->getProof($uuid);
    }

    /**
     * Verify the given JSON-LD chainpoint proof against the backend.
     *
     * @param  string $proof A JSON-LD chainpoint proof.
     * @return mixed
     */
    public function verify(string $proof)
    {
        // TODO: Is there a better way of doing this?
        $this->backend->setDiscoveredNodes($this->getExtra());

        return $this->backend->verifyProof($proof);
    }

    /**
     * @param  array $extra
     * @return VerifiableService
     */
    public function setExtra(array $extra = [])
    {
        if (!$extra) {
            $this->backend->setDiscoveredNodes();
            $this->extra = $this->backend->getDiscoveredNodes();
        } else {
            $this->extra = $extra;
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getExtra()
    {
        return $this->extra;
    }

    /**
     * Set, configure and return a new Merkle Tree storage backend.
     *
     * @param  BackendProvider   $provider Optional manually passed backend.
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

        // Cannot continue without a legit backend
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
     * @param  array  $data An array of data who's values should be hashed.
     * @return string       The resulting hashed data.
     */
    public function hash(array $data) : string
    {
        $func = $this->backend->hashFunc();
        $text = json_encode($data); // json_encode() to stringify arrays of arbitary depth

        return hash($func, $text);
    }

}
