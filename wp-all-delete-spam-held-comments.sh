#!/bin/sh

echo "# $name" > /dev/stderr

find /srv/www/ -name wp-config.php | while read config ; do
	dir=$( dirname $config )
	cd $dir
	# --force to skip trash
	wp comment delete $(wp comment list --status=spam --format=ids) --force
	wp comment delete $(wp comment list --status=hold --format=ids) --force
done
