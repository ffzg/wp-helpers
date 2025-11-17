#!/bin/sh

dir=$1
if [ ! -e "$dir/wp-config.php" ] ; then
	echo "ERROR: no wordpress in '$dir', use $0 /path/to/wordpress"
	exit 1
fi

as_user=$( ls -ald $dir | awk '{ print $3 }' )

sudo -u $as_user wp --require=/home/dpavlin/wp-helpers/wp-cli-audit/disable-user.php disable-user $*
