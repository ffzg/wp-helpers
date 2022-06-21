#!/bin/sh

ls -d /srv/www/* | while read dir ; do
	cd $dir
	users_can_register=$( wp db query 'select option_value from wp_options where option_name="users_can_register"' | grep -v option_value )
	echo "$dir $users_can_register"
done
