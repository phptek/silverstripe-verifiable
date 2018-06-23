<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Backend;

use PhpTek\Verifiable\Backend\BackendProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use SilverStripe\Core\Config\Configurable;
use PhpTek\Verifiable\Exception\VerifiableBackendException;
use PhpTek\Verifiable\Exception\VerifiableValidationException;
use SilverStripe\Core\Injector\Injector;

/**
 * Calls the endpoints of the Tierion network's ChainPoint service.
 *
 * @see https://app.swaggerhub.com/apis/chainpoint/node/1.0.0
 * @see https://chainpoint.org
 */
class Chainpoint implements BackendProvider
{
    use Configurable;

    /**
     * @return string
     */
    public function name() : string
    {
        return 'chainpoint';
    }

    /**
     * @return string
     */
    public function hashFunc() : string
    {
        return 'sha256';
    }

    /**
     * Send a single hash_id_node to retrieve a proof in binary format from the
     * Tierion network.
     *
     * GETs to the: "/proofs" REST API endpoint.
     *
     * @param  string $hashIdNode
     * @return string (From GuzzleHttp\Stream::getContents()
     * @todo Rename to proofs() as per the "gateway" we're calling
     * @todo modify to accept an array of hashes
     */
    public function getProof(string $hashIdNode) : string
    {
        $response = $this->client("/proofs/$hashIdNode", 'GET');

        return $response->getBody()->getContents() ?? '[]';
    }

    /**
     * Send an array of hashes for anchoring.
     *
     * POSTs to the: "/hashes" REST API endpoint.
     *
     * @param  array $hashes
     * @return string (From GuzzleHttp\Stream::getContents()
     * @todo Rename to hashes() as per the "gateway" we're calling
     */
    public function writeHash(array $hashes) : string
    {
        $response = $this->client('/hashes', 'POST', ['hashes' => $hashes]);

        return $response->getBody()->getContents() ?? '[]';
    }

    /**
     * Submit a chainpoint proof to the backend for verification.
     *
     * @param  string $proof A partial or full JSON string, originally received from,
     *                       or generated on behalf of, a backend.
     * @return bool
     * @todo See the returned proof's "uris" key, to be able to call a specific URI for proof verification.
     */
    public function verifyProof(string $proof) : bool
    {
        // Consult blockchains directly, if so configured and suitable
        // blockchain full-nodes are available to our RPC connections
        if ((bool) $this->config()->get('direct_verification')) {
            return $this->backend->verifyDirect($proof);
        }

        $response = $this->client('/verify', 'POST', ['proofs' => [$proof]]);
        $contents = $response->getBody()->getContents() ?? '[]';

        if ($contents === '[]') {
            return false;
        }

        return json_decode($contents, true)['status'] === 'verified';
    }

    /**
     * For each of this backend's supported blockchain networks, skips any intermediate
     * verification steps through the Tieron network, preferring instead to calculate
     * proofs ourselves in consultation directly with the relevant networks.
     *
     * @param  string $proof    The stored JSON-LD chainpoint proof
     * @param  array  $networks An array of available blockchains to consult
     * @return bool             Returns true if each blockchain found in $network
     *                          can verify our proof.
     * @todo   Implement via dedicated classes for each configured blockchain network.
     * @see    https://runkit.com/tierion/verify-a-chainpoint-proof-directly-using-bitcoin
     */
    protected function verifyProofDirect(string $proof, array $networks = [])
    {
        $result = [];

        foreach ($this->config()->get('blockchain_config') as $config) {
            if (in_array($config['name'], $networks)) {
                $implementation = ucfirst(strtolower($config['name']));
                $node = Injector::inst()->createWithArgs($implementation, [$config]);

                $result[strtolower($config['name'])] = $node->verifyProof($proof);
            }
        }

        return !in_array(false, $result);
    }

    /**
     * Return a client to use for all RPC traffic to this backend.
     *
     * @param  string   $url
     * @param  string   $verb
     * @param  array    $payload
     * @param  bool     $simple  Pass "base_uri" to {@link Client}.
     * @return Response Guzzle Response object
     * @throws VerifiableBackendException
     * @todo Client()->setSslVerification() if required
     * @todo Use promises to send concurrent requests: 1). Find a node 2). Pass node URL to second request
     * @todo Use 'body' in POST requests?
     * @todo Port to dedicated ChainpointClient class
     */
    protected function client(string $url, string $verb, array $payload = [], bool $simple = true)
    {
        $verb = strtoupper($verb);
        $method = strtolower($verb);
        $config = $this->config()->get('client_config');
        $client = new Client([
            'base_uri' => $simple ? $this->discoverNode() : '',
            'verify' => true,
            'timeout'  => $config['timeout'],
            'connect_timeout'  => $config['connect_timeout'],
            'allow_redirects' => false,
            'user-agent' => \GuzzleHttp\default_user_agent(),
        ]);

        try {
            // json_encodes POSTed data and sends correct Content-Type header
            if ($payload && $verb === 'POST') {
                $payload['json'] = $payload;
            }

            return $client->$method($url, $payload);
        } catch (RequestException $e) {
            throw new VerifiableValidationException($e->getMessage());
        }
    }

    /**
     * The Tierion network comprises many nodes, some of which may or may not be
     * online. Pings a randomly selected resource URL, who's response should contain
     * IPs of each advertised and audited node, then calls each one until one responds
     * with an HTTP 200 and returns it.
     *
     * @return string
     * @throws VerifiableBackendException
     * @todo Set the URL as a class-property and re-use that, rather than re-calling discoverNode()
     * @todo Make this method re-entrant and try a different URL
     */
    protected function discoverNode()
    {
        $chainpointUrls = $this->config()->get('chainpoint_urls');
        $url = $chainpointUrls[rand(0,2)];
        $response = $this->client($url, 'GET', [], false);

        if ($response->getStatusCode() !== 200) {
            throw new VerifiableBackendException('Bad response from node source URL');
        }

        foreach (json_decode($response->getBody(), true) as $candidate) {
            $response = $this->client($candidate['public_uri'], 'GET', [], false);

            // If for some reason we don't get a response: Do re-entrant method
            if ($response->getStatusCode() === 200) {
                return $candidate['public_uri'];
            }
        }
    }

}
