#!/bin/sh

echo "# $name" > /dev/stderr

login=wordpress
login=backup

find /srv/www/ -name wp-config.php | while read config ; do
	dir=$( dirname $config )
	user=$( ls -al $config | awk '{ print $3 }' )
	cd $dir
	echo "# $dir"
	if sudo -u $user wp --allow-root user list | grep $login ; then
		echo "XXX remove"
		sudo -u $user wp --allow-root user remove-role $login administrator
		sudo -u $user wp --allow-root user update $login --user_pass=$( pwgen 12 1 )
	fi
done
