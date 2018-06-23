<?php

/**
 * @author  Russell MIchell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Backend;

use PhpTek\Verifiable\Backend\BackendProvider;
use GuzzleHttp\Client;
use Guzzle\Http\Exception\RequestException;
use Guzzle\Http\Message\Request;
use SilverStripe\Core\Config\Configurable;
use PhpTek\Verifiable\Exception\VerifiableBackendException;

/**
 * See: https://chainpoint.org
 */
class Chainpoint implements BackendProvider
{
    use Configurable;

    /**
     *
     * {@inheritdoc}
     */
    public function name() : string
    {
        return 'chainpoint';
    }

    /**
     *
     * @throws VerifiableBackendException
     */
    public function getProof(string $hash) : array
    {
        $response = $this->client('/proofs', 'GET', $hash);

        if ($response->getStatusCode() !== 200) {
            throw new VerifiableBackendException('Unable to fetch proof from backend.');
        }

        return $response->getBody();
    }

    /**
     *
     * {@inheritdoc}
     */
    public function writeHash(string $hash) : string
    {
        $response = $this->client('/hashes', 'POST', ['hashes' => $hash]);

        return $response->getBody();
    }

    /**
     * Submit a chainpoint proof to the backend for verification.
     *
     * @param  string $proof A valid JSON-LD Chainpoint Proof.
     * @return bool
     */
    public function verifyProof(string $proof) : bool
    {
        $response = $this->client('/verify', 'POST', json_encode(['proofs' => [$hash]]));

        return $response->getStatusCode() === 200 &&
                json_decode($response->getBody(), true)['status'] === 'verified';
    }

    /**
     * Return a client to use for all RPC traffic to this backend.
     *
     * @param  string                $url
     * @param  string                $verb
     * @param  array                 $payload
     * @return mixed null | Response Guzzle Response object
     * @todo Client()->setSslVerification() if required
     */
    protected function client(string $url, string $verb, array $payload = [])
    {
        $verb = strtoupper($verb);
        $client = new Client([
            'base_uri' => $this->fetchNodeUrl(),
            'timeout'  => $this->config()->get('chainpoint', 'params')['timeout'],
        ]);
        $request = new Request($verb, $url, $payload);

        try {
            return $client->send($request);
        } catch (RequestException $e) {
            return null;
        }
    }

    /**
     * The Tieron network comprises many nodes, some of which may or may not be
     * online. Pings a randomly selected resource URL, containing IPs of each
     * advertised node and calls each until one returns an HTTP 200.
     *
     * @return string
     */
    protected function fetchNodeUrl()
    {
        $chainpointUrls = [
            'https://a.chainpoint.org/nodes/random',
            'https://b.chainpoint.org/nodes/random',
            'https://c.chainpoint.org/nodes/random',
        ];

        $url = $chainpointUrls[rand(count($randUrls))];
        $response = $this->client($url, 'GET');

        foreach (json_decode($response->getBody(), true) as $candidate) {
            $response = $this->client($candidate['public_uri']);

            if ($response->getStatusCode() === 200) {
                return $candidate['public_uri'];
            }
        }
    }

}
