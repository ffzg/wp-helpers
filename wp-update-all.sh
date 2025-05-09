#!/bin/sh

dir=$1
if [ ! -e "$dir/wp-config.php" ] ; then
	echo "ERROR: no wordpress in '$dir', use $0 /path/to/wordpress"
	exit 1
fi

as_user=$( ls -ald $dir | awk '{ print $3 }' )

sudo -u $as_user wp core update --path=$dir
sudo -u $as_user wp core update-db --path=$dir
sudo -u $as_user wp plugin update --all --path=$dir
sudo -u $as_user wp theme update --all --path=$dir
