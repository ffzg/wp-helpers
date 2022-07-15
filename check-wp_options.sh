#!/bin/sh

name=$( basename $0 | sed -e 's/^check-//' -e 's/\.sh$//' )
if [ ! -z "$1" ] ; then
	name=$1
fi

echo "# $name" > /dev/stderr

find /srv/www/ -name wp-config.php | while read config ; do
	dir=$( dirname $config )
	cd $dir
	value=$( wp db query "select option_value from wp_options where option_name='$name'" | grep -v option_value )
	echo "$dir $value"
done
