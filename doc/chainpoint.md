chainpoint:

See: https://github.com/chainpoint/chainpoint-node/wiki/Chainpoint-Node-API:-How-to-Create-a-Chainpoint-Proof

write()

1). Request https://a.chainpoint.org/nodes/random and select 3 nodes at random at write-time to send data to
2). Create a sha256 Hash of the data
3). Submit hash to the 3 nodes selected in 1. /hashes (returns: {"hash_id_node": "foo"} the UID for the chainpoint proof
4). Retrieve a partial Chainpoint Proof

	Wait ~15 seconds for your hash to be anchored to the Chainpoint Calendar. Retrieve the proof by using the Node’s GET /proofs/:hash_id endpoint. The Node will return a partial Chainpoint proof that points to the Chainpoint Calendar blockchain.
	Step 4 - Retrieve a full Chainpoint Proof

	It can take up to two hours before a Chainpoint Node is ready to return a full Chainpoint proof. Chainpoint publishes a Bitcoin transaction and waits for six confirmations to ensure its permanence on the Bitcoin blockchain. After two hours, retrieve the proof using the Node’s GET /proofs/:hash_id endpoint. The node will return an updated version of your proof that is anchored to the Chainpoint Calendar and the Bitcoin blockchain.
	
5). Verify a Chainpoint Proof

	You can verify a full or partial Chainpoint proof by submitting the proof to a Node’s POST /verify endpoint. The API response will specify if the proof is valid or not.

