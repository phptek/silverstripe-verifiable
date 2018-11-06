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
  if [ ! -f ./CHECKSUM ]; then
    echo -e "BAD RELEASE!"
    exit 1
  fi

  echo -e "...OK"
}

function do_release ()
{
  # Write checksum
  echo -e "* Writing checksum..."
  source ./tools/checksum.sh true > CHECKSUM

  # Verify release
  echo -e "* Verifiying release"
  verify_release

  # Git commit
  echo -e "* Create release..."
  git add . && git commit -an -m "Release: $tag"

  echo -e "* Done!"
}

do_release
