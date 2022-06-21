#!/bin/sh

name=$( basename $0 | sed -e 's/^check-//' -e 's/\.sh$//' )

echo "# $name" > /dev/stderr

ls -d /srv/www/* | while read dir ; do
	cd $dir
	if [ -e wp-config.php ] ; then
		value=$( wp db query "select option_value from wp_options where option_name='$name'" | grep -v option_value )
		echo "$dir $value"
	else
		echo "$dir MISSING wp-config.php" > /dev/stderr
	fi
done
