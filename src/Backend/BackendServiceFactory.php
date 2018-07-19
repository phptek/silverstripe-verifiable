<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Backend;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use PhpTek\Verifiable\Exception\VerifiableBackendException;

/**
 * Constructs and returns the appropriate backend service implementation,
 * according to userland config.
 */
class BackendServiceFactory
{
    use Configurable;

    /**
     * @return ServiceProvider
     * @throws VerifiableBackendException
     */
    public function create()
    {
        $backend = strtolower($this->config()->get('backend'));
        $gtwClass = sprintf('PhpTek\Verifiable\Backend\%s\Gateway', ucfirst($backend));
        $srvClass = sprintf('PhpTek\Verifiable\Backend\%s\Service', ucfirst($backend));

        if (!class_exists($gtwClass) || !class_exists($srvClass)) {
            throw new VerifiableBackendException(sprintf('No backend named %s was found', $srvClass));
        }

        return Injector::inst()->create($srvClass);
    }

}
