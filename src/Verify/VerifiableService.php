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
     */
    public function __construct()
    {
        $this->setBackend();
    }

    /**
     * Wrapper around all backend methods.
     *
     * @param  string $method The name of the method to call
     * @param  mixed  $arg    The argument to pass to $method
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function call($method, $arg)
    {
        if (!method_exists($this, $method)) {
            throw new \InvalidArgumentException("$method doesn't exist.");
        }

        $this->backend->setDiscoveredNodes($this->getExtra());

        return $this->$method($arg);
    }

    /**
     * Write a hash of data as per the "verifiable_fields" config static on each
     * {@link DataObject}.
     *
     * @param  array $data
     * @return mixed The result of this call to the backend.
     */
    protected function write(array $data)
    {
        return $this->backend->writeHash([$this->hash($data)]);
    }

    /**
     * Fetch a chainpoint proof for the passed $uuid.
     *
     * @param  mixed string | array $uuid
     * @return string The JSON-LD chainpoint proof.
     */
    protected function read($uuid) : string
    {
        if (is_array($uuid)) {
            return $this->backend->getProofs(array_unique($uuid));
        }

        return $this->backend->getProof($uuid);
    }

    /**
     * Verify the given JSON-LD chainpoint proof against the backend.
     *
     * @param  string $proof A JSON-LD chainpoint proof.
     * @return mixed
     */
    protected function verify(string $proof)
    {
        return $this->backend->verifyProof($proof);
    }

    /**
     * Set some arbitrary data onto the service. Used as a way of acting as
     * an intermediary or broker between DataOBjects and the backend.
     *
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
     * Return arbitrary data set to this service.
     *
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
        $text = json_encode($data, true);

        return hash($func, $text);
    }

}
