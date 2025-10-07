#!/bin/bash

if [ -z "$1" ]; then
    echo "Usage: $0 <plugin_directory>"
    exit 1
fi

PLUGIN_DIR=$1

echo "Searching for menu creation functions in $PLUGIN_DIR..."
echo "--------------------------------------------------"

grep -rnH -E 'add_menu_page|add_submenu_page|add_options_page' $PLUGIN_DIR

echo "--------------------------------------------------"
echo "To find wrapper functions, manually inspect the output above."
echo "Look for the menu slugs (usually the 4th argument in the function call)."
echo "Then, search for the slug in the plugin directory to see how it's used."
echo "For example, if you find a slug 'my_plugin_settings', run:"
echo "grep -r 'my_plugin_settings' $PLUGIN_DIR"