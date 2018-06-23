<?php

/**
 * @author  Russell MIchell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Backend;

use PhpTek\Verifiable\Backend\BackendProvider;
use PhpTek\Verifiable\Verifiable;
use PhpTek\Verifiable\Exception\VerifiableValidationException;
use GuzzleHttp\Client;
use Guzzle\Http\Exception\RequestException;
use Guzzle\Http\Message\Request;
use PhpTek\Verifiable\Exception\VerifiableBackendException;
use SilverStripe\Core\Config\Configurable;

/**
 * Trillian relies on something called a "Personality" to supply it with the exact
 * type and format of data, that the overall application is expecting it to store.
 * As such Trillian itself will perform no data validation or normalisation, favouring
 * instead to farm out this responsibility to personalities.
 */
class Trillian implements BackendProvider
{
    use Configurable;

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
        // TODO
        $response = $this->client('/auth', 'GET', [
            'auth' => [
                $this->config()->get('connection', 'username'),
                $this->config()->get('connection', 'password'),
                'digest'
            ]
        ]);

        return $response->getStatusCode() === 200;
    }

    /**
     *
     * {@inheritdoc}
     */
    public function read(string $hash) : array
    {
        if (!$this->connect()) {
            return [];
        }
    }

    /**
     *
     * {@inheritdoc}
     */
    public function write(string $hash) : string
    {
        if (!$this->connect()) {
            return [];
        }
    }

    /**
     * Return a client to use for all RPC traffic to this backend.
     *
     * @param  string             $url
     * @param  string             $verb
     * @param  array              $payload
     * @return GuzzleHTTPResponse
     * @throws VerifiableBackendException
     */
    private function client(string $url, string $verb, array $payload = [])
    {
        $verb = strtoupper($verb);
        // See Client()->setSslVerification() if required
        $client = new Client([
            'base_uri' => $this->config()->get('trillian', 'params')['base_uri'],
            'timeout'  => $this->config()->get('trillian', 'params')['timeout'],
        ]);
        $request = new Request($verb, $url, $payload);

        try {
            $client->send($request);

            if (!preg_match("#^2#", $code = $request->getStatusCode())) {
                throw new VerifiableBackendException(sprintf('Request gave HTTP status: %d', $code));
            }
        } catch (RequestException $e) {
            throw new VerifiableBackendException($e->getMessage());
        }
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
