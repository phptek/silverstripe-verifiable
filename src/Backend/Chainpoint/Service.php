<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Backend\Chainpoint;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Config\Configurable;
use PhpTek\Verifiable\Backend\GatewayProvider;
use PhpTek\Verifiable\Backend\ServiceProvider;
use PhpTek\Verifiable\Backend\Chainpoint\Gateway;

/**
 * Service class that works as an intermediary between any data model and the
 * currently selected Merkle Tree backend gateway.
 *
 * @todo There should be only one service. The gateway is the part that changes
 *       between backend implementations.
 */
class Service implements ServiceProvider
{
    use Injectable;
    use Configurable;

    /**
     * @var GatewayProvider
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
        $this->setGateway();
    }

    /**
     * @return string
     */
    public function name() : string
    {
        return $this->getGateway()->name();
    }

    /**
     * Wrapper around all gateway methods.
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

        $this->gateway->setDiscoveredNodes($this->getExtra());

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
        return $this->gateway->hashes([$this->hash($data)]);
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
            return $this->gateway->proofs(array_unique($uuid));
        }

        return $this->gateway->proofs($uuid);
    }

    /**
     * Verify the given JSON-LD chainpoint proof against the backend.
     *
     * @param  string $proof A JSON-LD chainpoint proof.
     * @return mixed
     */
    protected function verify(string $proof)
    {
        return $this->gateway->verify($proof);
    }

    /**
     * Set some arbitrary data onto the service. Used as a way of acting as
     * an intermediary or broker between DataObjects and the backend.
     *
     * @param  array $extra
     * @return VerifiableService
     */
    public function setExtra(array $extra = [])
    {
        if (!$extra) {
            $this->gateway->setDiscoveredNodes();
            $this->extra = $this->gateway->getDiscoveredNodes();
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
     * @param  GatewayProvider   $provider Optional manually passed backend.
     * @return VerifiableService
     */
    public function setGateway(GatewayProvider $provider = null)
    {
        if ($provider) {
            $this->gateway = $provider;

            return $this;
        }

        $this->gateway = Injector::inst()->create(Gateway::class);
    }

    /**
     * @return GatewayProvider
     */
    public function getGateway()
    {
        return $this->gateway;
    }

    /**
     * Hashes the data passed as the $data param.
     *
     * @param  array  $data An array of data who's values should be hashed.
     * @return string       The resulting hashed data.
     */
    public function hash(array $data) : string
    {
        $func = $this->gateway->hashFunc();
        $text = serialize($data);

        return hash($func, $text, false);
    }

}
