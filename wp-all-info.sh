#!/bin/sh

# sudo ln -s /home/dpavlin/wp-helpers/wp-all-info.sh /etc/cron.daily/wp-all-info

sudo find /srv/www/ -name wp-config.php | while read config ; do
	dir=$( dirname $config )
	name=$( basename $dir );
	user=$( ls -al $config | awk '{ print $3 }' )
	to=$( echo $dir | sed 's,/srv/www,/home/dpavlin/wp-info,' )
	cd $dir
	echo "# $dir"
	test -d $to || mkdir -v -p $to
	sudo -u $user wp --allow-root user list   | tee $to/user
	sudo -u $user wp --allow-root plugin list | tee $to/plugin
done

cd /home/dpavlin/wp-info

/home/dpavlin/wp-helpers/mysql-size.sh

git add *
git commit -m $( date +%Y-%m-%d ) -a
