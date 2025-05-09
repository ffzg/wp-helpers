#!/bin/sh

dir=$1
if [ ! -e "$dir/wp-config.php" ] ; then
	echo "ERROR: no wordpress in '$dir', use $0 /path/to/wordpress"
	exit 1
fi

wp core check-update --path=$dir
wp plugin list --update-available --path=$dir
wp theme list --update-available --path=$dir
