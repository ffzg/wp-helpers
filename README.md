# check-wp_options.sh

generic script to check wp-options on all sites

ln -sf check-wp_options.sh check-default_comment_status.sh
./check-default_comment_status.sh

# activate plugins

grep fail2ban /dev/shm/wp-plugin-list | grep inactive | awk '{ print "cd "$1" && wp --allow-root plugin activate "$2 }' | sh -x


# find sites which are missing fail2ban plugin and install it

grep fail2ban /dev/shm/wp-plugin-list | awk '{ print $1 }' > /tmp/wp.fail2ban
grep -v -f /tmp/wp.fail2ban /dev/shm/wp-dirs > /tmp/wp.fail2ban.missing
cat /tmp/wp.fail2ban.missing | awk '{ print "cd "$1" ; wp --allow-root plugin install wp-fail2ban --activate" }' | sh -x



