<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Verify;

use SilverStripe\Core\DataExtension;

/**
 * Attach to any {@link DataObject} subclass, including {@link SiteTree} subclasses,
 * and all applicable database writes will be passed through here.
 */
class VerifiableExtension extends DataExtension
{
    /**
     * @var array
     * @config
     */
    private static $verifiable_fields = [];

    /**
     * @var  Verifiable
     * @todo Should prob be nailed-in, in config as a dependency.
     */
    protected $verifiable;

    /**
     * @return void
     */
    public function __construct()
    {
        $this->verifiable = Verifiable::create()->setModel($this->getOwner());
    }

    /**
     * After each write, hash the desired fields and submit to the current backend.
     *
     * @return void
     */
    public function onAfterWrite()
    {
        $this->verifiable->write();
    }

    /**
     * Return the data in the backend referenced by $hash. If no data
     * is found, returns an empty array.
     *
     * @param  string $hash
     * @return mixed boolean | array
     */
    public function read(string $hash) : array
    {
        return $this->verifiable->read($hash);
    }

}

