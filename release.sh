#!/bin/bash
#
# Verifiable package release manager

tag=$1

if [ -z "${tag}" ]; then
  echo -e "No tag given!"
  exit 1
fi

function verify_release ()
{
  if [ ! -f ./VERSION ] || [ ! -f ./CHECKSUM ]; then
    echo -e "BAD RELEASE!"
    exit 1
  fi

  echo -e "...OK"
}

function do_checksum ()
{
  tar -c \
        --sort=name \
        --owner=0 \
        --group=0 \
        --mode=755 \
        --numeric-owner \
        --mtime=2018-01-01 00:00Z \
        --clamp-mtime \
        --exclude-vcs \
        --no-ignore-case \
        --exclude=CHECKSUM | sha256sum | sed -e 's#[\s\-]##g' > CHECKSUM
}

function do_release ()
{
  # Write version
  echo -e "* Writing version..."
  echo $tag > VERSION

  # Write checksum
  echo -e "* Writing checksum..."
  do_checksum

  # Verify release
  echo -e "* Verifiying release"
  verify_release

  # Git commit
  echo -e "* Create release..."
  git add . && git commit -an -m "Release $tag"

  echo -e "* Done!"
}

do_release
