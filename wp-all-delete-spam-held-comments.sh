#!/bin/sh

echo "# $name" > /dev/stderr

find /srv/www/ -name wp-config.php | while read config ; do
	dir=$( dirname $config )
	cd $dir
	echo "# $dir"
	# --force to skip trash
	spam_ids=$(wp comment list --status=spam --format=ids)
	trash_ids=$(wp comment list --status=hold --format=ids)
	if [ ! -z "$spam_ids" -o ! -z "$trash_ids" ] ; then
		echo "IDS to remove: $spam_ids $trash_ids"
		wp comment delete $spam_ids $trash_ids
		wp db optimize
	fi
done
