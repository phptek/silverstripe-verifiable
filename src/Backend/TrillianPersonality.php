<?php

/**
 * @author  Russell MIchell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Backend;

use PhpTek\Verifiable\Backend\BackendProvider;
use PhpTek\Verifiable\Verifiable;
use PhpTek\Verifiable\Exception\VerifiableValidationException;

/**
 * Trillian relies on something called a "Personality" to supply it with the exact
 * type and format of data, that the overall application is expecting it to store.
 * As such Trillian itself will perform no data validation or normalisation, favouring
 * instead to farm out this responsibility to personalities.
 */
class TrillianPersonality implements BackendProvider
{
    /**
     *
     * {@inheritdoc}
     */
    public function name() : string
    {
        return 'trillian';
    }

    /**
     *
     * {@inheritdoc}
     */
    public function connect() : bool
    {
    }

    /**
     *
     * {@inheritdoc}
     */
    public function read(string $hash) : array
    {
    }

    /**
     *
     * {@inheritdoc}
     */
    public function write(string $hash) : string
    {
    }

    /**
     * @param  string $data                  The data to be verified
     * @throws VerifiableValidationException In the event invalid data is detected
     *                                       Sure-fire way to prevent a malformed
     *                                       write to the backend.
     * @return void
     * @todo   Implement a dedicated hash-specific handler
     */
    public function validate(string $data)
    {
        $func = Verifiable::config()->get('hash_func');

        if ($func == 'sha1') {
            if (strlen($data) !== 40) {
                throw new VerifiableValidationException(sprintf('Invalid %s hash: Length', $func));
            }
        }
    }

}
