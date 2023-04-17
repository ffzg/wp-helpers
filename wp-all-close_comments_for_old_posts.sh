#!/bin/sh

echo "# $name" > /dev/stderr

find /srv/www/ -name wp-config.php | while read config ; do
	dir=$( dirname $config )
	cd $dir
	wp option update close_comments_for_old_posts 1
done
