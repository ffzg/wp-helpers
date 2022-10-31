#!/bin/sh -xe

test ! -z "$1" && cd $1 && echo "# $1"

where=" where unixday = FLOOR(UNIX_TIMESTAMP() / 86400)" # today
#where=" where unixday > FLOOR(UNIX_TIMESTAMP() / 86400) - 7" # week

wp db query "select inet6_ntoa(ip),countryCode,blockCount,unixday,blockType from wp_wfBlockedIPLog $where" ||
wp db query "select inet6_ntoa(ip),countryCode,blockCount,unixday,blockType from wp_wfblockediplog $where"
