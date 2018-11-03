<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use PhpTek\Verifiable\Backend\BackendServiceFactory;
use PhpTek\Verifiable\Exception\VerifiableBackendException;
use PhpTek\Verifiable\Backend\ServiceProvider;

/**
 * Suite: BackendServiceFactoryTest
 *
 * Deals with mocking testing key logic from the module's service/gateway factory
 * found in {@link BackendServiceFactory}.
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
