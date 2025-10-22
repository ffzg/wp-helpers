diff -u <(wp plugin list --field=name | sort) <( (cd wp-content/plugins && ls -d */ | cut -f1 -d'/') | sort)

