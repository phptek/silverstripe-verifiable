<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Test;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use PhpTek\Verifiable\Backend\BackendServiceFactory;
use PhpTek\Verifiable\Exception\VerifiableBackendException;
use PhpTek\Verifiable\Backend\ServiceProvider;

/**
 * Simple tests for our public factory API.
 */
class BackendServiceFactoryTest extends SapphireTest
{
    public function testCreateWithNonExistentClass()
    {
        $this->setExpectedException(VerifiableBackendException::class);

        Config::nest();
        // Non-existent class should throw an exception
        Config::modify()->update(BackendServiceFactory::class, 'backend', 'nothere');

        $factory = new BackendServiceFactory();
        $factory->create('nothere', []);

        Config::unnest();
    }

    public function testCreateWithBadClassCase()
    {
        Config::nest();
        // Bad clase case should be OK
        Config::modify()->update(BackendServiceFactory::class, 'backend', 'CHAINPOINT');

        $factory = new BackendServiceFactory();

        $this->assertInstanceOf(ServiceProvider::class, $factory->create('CHAINPOINT', []));

        Config::unnest();
    }

}
