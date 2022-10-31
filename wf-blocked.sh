#!/bin/sh -xe

test ! -z "$1" && cd $1 && echo "# $1" || exit 1

wp db query 'select inet6_ntoa(ip),hex(ip),countryCode,blockCount,unixday,blockType from wp_wfBlockedIPLog'
