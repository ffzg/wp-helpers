#!/bin/sh -e


:> /dev/shm/wp-plugin-list

./wp-dirs.sh | while read dir ; do
	cd $dir
	wp plugin list --allow-root | sed "s,^,$dir ," | tee -a /dev/shm/wp-plugin-list
done
