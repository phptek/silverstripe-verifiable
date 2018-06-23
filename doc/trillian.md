## Development Install

Instructions for a local, development installation of Trillian in Map mode.

To install Trillian you can try the Docker Compose method found in the project's repo's "examples" dir, but I found these a little opaque to debug. I eventually found a buried Makefile ([reproduced here](../examples/trillian/Makefile) in another project and installed it manually from the commands there.

Note: Instructions for Trillian `master:b736197c9b9`

 1. Install Go. You'll need v1.9+ (I'm using v1.10.1 on Ubuntu 16.04)
 2. If you haven't got it, install `mysql` or `mariadb` which Trillian uses as its data store
 3. Create a mysql `test` account on a `test` database: `mysql -u<user> -p<pass> -ve "USE mysql ; CREATE SCHEMA test" \
  && mysql -u<user> -p<pass> -ve "USE mysql ; GRANT ALL ON test.* TO 'test'@'localhost' IDENTIFIED BY 'zaphod'" \
  && mysql -ve "USE mysql ; FLUSH PRIVILEGES"`
 4. Ensure your `GOPATH` and `PATH` are set correctly. Do NOT set `GOROOT` if `go env` says you already have it. Rookie mistake which cost me a few hours of head scratching
 5. Install Trillian: `go get github.com/google/trillian/...`
 6. Build the log, map server and signer: `cd $GOPATH/src/github.com/google/trillian && \
 	go build ./server/trillian_log_server && \
 	go build ./server/trillian_log_signer && \
 	go build ./server/trillian_map_server`
 7. Start the logserver: `./trillian_log_server --logtostderr ...`
 8. Start Trillian: `./trillian_log_signer --logtostderr --force_master --http_endpoint=localhost:8092 --batch_size=1000 --sequencer_guard_window=0 --sequencer_interval=200ms`
 
 Note: You could also see what milage you get out of `scripts/resetdb.sh` too.

## Web services

TBC
