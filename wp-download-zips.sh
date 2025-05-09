#!/bin/bash -e

# Define download directories
download_dir="/tmp/wp.download/"
wp_dir="/tmp/wp/"

# remove old directory
test -d "$wp_dir" && rm -Rf "$wp_dir"

# Create directories if they don't exist
mkdir -p "$download_dir" "$wp_dir"

# Check if WP-CLI is available
if ! command -v wp &> /dev/null; then
    echo "WP-CLI is not installed. Please install it first."
    exit 1
fi

# Verify we're in a WordPress directory
if ! wp core is-installed &> /dev/null; then
    echo "This doesn't appear to be a WordPress installation directory. Exiting."
    exit 1
fi

# Download WordPress core
wp_core_version=$(wp core version)
wp_core_zip="wordpress-${wp_core_version}.zip"
if [ ! -e "$download_dir/$wp_core_zip" ] ; then
	core_url="wordpress.org/wordpress-${wp_core_version}.zip"
	echo "Downloading WordPress Core ${wp_core_version}"
	wget -q -m "https://$core_url" -P "$download_dir" && echo "Core downloaded successfully" || echo "Failed to download core"
else
	echo "Existing $wp_core_zip"
fi
unzip -o -q "$download_dir/$core_url" -d $wp_dir/

# Download plugins
echo "Processing plugins..."
wp plugin list --fields=name,version --format=csv | tail -n +2 | while IFS=, read -r slug version; do
    plugin_zip="${slug}.${version}.zip"
    plugin_url="downloads.wordpress.org/plugin/${slug}.${version}.zip"
    plugin_url_no_ver="downloads.wordpress.org/plugin/${slug}.zip"
    echo "Downloding plugin ${slug} ${version}"

    if ! wget -q -m "https://$plugin_url" -P "$download_dir" ; then 
	if ! wget -q -m "https://$plugin_url_no_ver" -P "$download_dir" ; then
		local_zip=/home/dpavlin/${slug}.${version}.zip
		if [ -e $local_zip ] ; then
			unzip -o -q $local_zip    -d "$wp_dir/wordpress/wp-content/plugins/"
		else
			echo "ERROR: ${slug}.${version}.zip MISSING"
		fi
        else
		unzip -o -q $download_dir/$plugin_url_no_ver -d "$wp_dir/wordpress/wp-content/plugins/"
	fi
    else
  	unzip -o -q $download_dir/$plugin_url -d "$wp_dir/wordpress/wp-content/plugins/"
    fi
done

# Download themes
echo "Processing themes..."
wp theme list --fields=name,version --format=csv | tail -n +2 | while IFS=, read -r slug version; do
    theme_url="downloads.wordpress.org/theme/${slug}.${version}.zip"
    echo "Downloding theme ${slug} ${version}"
    
    if ! wget -q -m "$theme_url" -P "$download_dir" ; then
	echo "ERROR: $theme_url MISSING"
    else
    	unzip -o -q $download_dir/$theme_url -d "$wp_dir/wordpress/wp-content/themes/"
    fi
done

echo "Download process completed!"
echo "Download dir: $download_dir"
echo "Wordpress dir: $wp_dir/wordpress/"
echo "Compare installation with: diff -urw $wp_dir/wordpress $(pwd)"
