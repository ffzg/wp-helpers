#!/bin/sh

echo "# $name" > /dev/stderr

find /srv/www/ -name wp-config.php | while read config ; do
	dir=$( dirname $config )
	user=$( ls -al $config | awk '{ print $3 }' )
	cd $dir
	echo "# $dir"
	if sudo -u $user wp user list | grep wordpress ; then
		echo "XXX remove"
		sudo -u $user wp user remove-role wordpress administrator
		sudo -u $user wp user update wordpress --user_pass=$( pwgen 12 1 )
	fi
done
