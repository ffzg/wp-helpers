#!/bin/sh

echo "# $name" > /dev/stderr


sudo find /srv/www/ -name wp-config.php | while read config ; do
	dir=$( dirname $config )
	name=$( basename $dir );
	user=$( ls -al $config | awk '{ print $3 }' )
	to=$( echo $dir | sed 's,/srv/www,/home/dpavlin/wp-info,' )
	cd $dir
	echo "# $dir"
	if sudo -u $user wp user list | grep wordpress ; then
		echo "XXX $name"
		test -d $to || mkdir -v -p $to
		sudo -u $user wp user list   | tee $to/user
		sudo -u $user wp plugin list | tee $to/plugin
	fi
done
