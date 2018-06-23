<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\ClassInfo;
use PhpTek\Verifiable\Exception\VerifiableBackendException;
use SilverStripe\ORM\DataObjectSchema;

/**
 * Does all the connect/read/write heavy-lifting.
 */
class Verifiable
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
     * @var DataObjectProvider
     */
    protected $model;

    /**
     * @return void
     * @throws VerifiableBackendException
     */
    public function __construct()
    {
        if (!$this->backend = $this->backend()) {
            throw new VerifiableBackendException('Backend not found or not specified.');
        }
    }

    /**
     * Write a hash of data as per the "verifiable_fields" confif static on each
     * {@link DataObject}.
     *
     * @return boolean True if the write went through OK. False otherwise.
     */
    public function write() : boolean
    {
        $fields = $this->model->config()->get('verifiable_fields');
    	$hash = $this->hash($fields);

        return $this->backend->write($hash);
    }

    /**
     * Get and instantiate a new backend
     *
     * @return mixed null | BackendProvider
     */
    public function backend()
    {
        $namedBackend = $this->config()->get('backend');
        $backends = ClassInfo::implementorsOf('BackendProvider');

        foreach ($backends as $backend) {
            if (singleton($backend)->name() == $namedBackend) {
                return Injector::inst()->create($backend);
            }
        }

        return null;
    }

    /**
     * Hashes the data found in all the fields of the current Data Model.
     *
     * @param  array $fields The fields on the current {@link DataObject} subclass
     *                       who's values should be hashed.
     * @return string
     * @todo   Take use input in the form of a digital signature
     */
    public function hash(array $fields) : string
    {
        $text = '';
        $class = get_class($this->model);
        $func = $this->config()->get('hash_func');
        $specs = array_keys(
            $this->model->getSchema()->fielSpecs($class, DataObjectSchema::UNINHERITED)
        );

        foreach ($specs as $name) {
            if (isset($fields[$name])) {
                $text .= $this->model->getField($name);
            }
        }

        return $func($test);
    }

    /**
     * @param  DataObject $model
     * @return Verifiable
     */
    public function setModel(DataObject $model) : Verifiable
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Return an array of the fetched hash, a timestamp and everything else the current backend
     * gives us.
     *
     * @return array
     */
     public function read(string $hash) : array
     {
     }

}
