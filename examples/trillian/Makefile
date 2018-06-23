#If Docker images don't work. Go from source
#
# Makefile
#
# Instructions ripped from: https://github.com/google/trillian-examples/tree/master/etherslurp
# 
# In the first terminal:
# make trillian
# In the second terminal:
# make tlserver
# In another terminal:
# make tlsigner
# Now create a Trillian log or map
# make createlog | make createmap
# UI (locahost:9001)
# make ui

T=$(GOPATH)/src/github.com/google/trillian
E=$(GOPATH)/src/github.com/google/trillian-examples/etherslurp
G=$(GOPATH)/src/github.com/ethereum/go-ethereum/build/bin/geth

trillian::
	go get -u -d -t github.com/google/trillian/...
	cd $T && \
	go build ./server/trillian_log_server && \
	go build ./server/trillian_log_signer && \
	go build ./server/trillian_map_server

tlserver::
	cd $T && ./trillian_log_server --logtostderr ...

tlsigner::
	cd $T && ./trillian_log_signer --logtostderr --force_master --http_endpoint=localhost:8092 --batch_size=1000 --sequencer_guard_window=0 --sequencer_interval=200ms
