#!/bin/sh -xe

sudo du -kcs /var/lib/mysql/* | awk '{ print $2 " " $1 }' | sort | tee /tmp/1

ls -d /srv/www/* | xargs -i sh -cx 'cd {} ; wp db optimize ; cd -'

sudo du -kcs /var/lib/mysql/* | awk '{ print $2 " " $1 }' | sort | tee /tmp/2

join /tmp/1 /tmp/2 | column -t | tee -a $( date +%Y-%m-%d-wp-db-optimize.txt )
