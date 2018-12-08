#!/bin/bash

function do_checksum ()
{
  tar -c \
        --sort=name \
        --owner=0 \
        --group=0 \
        --mode=755 \
        --numeric-owner \
        --mtime="2018-11-06T21:45:07,354730315+13:00" \
        --clamp-mtime \
        --exclude-vcs \
        --no-ignore-case \
        --exclude=CHECKSUM . | sha256sum | sed -e 's#[ -]##g'
}

if [ ! -z "${1}" ]; then
  do_checksum
fi
