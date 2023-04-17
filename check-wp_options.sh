#!/bin/sh

name=$( basename $0 | sed -e 's/^check-//' -e 's/\.sh$//' )
if [ ! -z "$1" ] ; then
	name=$1
fi

echo "# $name" > /dev/stderr

find /srv/www/ -name wp-config.php | while read config ; do
	dir=$( dirname $config )
	cd $dir
	echo "$dir " $( wp option get $name )
done
