#!/bin/sh -e

ls -d /srv/www/*/wp-content | sed 's/wp-content//' | tee /dev/shm/wp-dirs
