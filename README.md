# check-wp_options.sh

generic script to check wp-options on all sites

ln -sf check-wp_options.sh check-default_comment_status.sh
./check-default_comment_status.sh

# disable-comments.sh

close comments on posts and default_comment_status

Example to disable comments on all sites:

./check-default_comment_status.sh | grep ' open$' | awk '{ print $1 }' | \
xargs -i sh -cxe 'cd {} ; /home/dpavlin/wp-helpers/disable-comments.sh'

## comments_for_old_posts

This option is useful to disable comments on media ports, since there is
no easy vay to disable them in any other way

./wp-all-close_comments_for_old_posts.sh

# check-users_can_register.sh

check if new users can register, disable with

wp option set users_can_register 0

# activate plugins

grep fail2ban /dev/shm/wp-plugin-list | grep inactive | awk '{ print "cd "$1" && wp --allow-root plugin activate "$2 }' | sh -x


# find sites which are missing fail2ban plugin and install it

grep fail2ban /dev/shm/wp-plugin-list | awk '{ print $1 }' > /tmp/wp.fail2ban
grep -v -f /tmp/wp.fail2ban /dev/shm/wp-dirs > /tmp/wp.fail2ban.missing
cat /tmp/wp.fail2ban.missing | awk '{ print "cd "$1" ; wp --allow-root plugin install wp-fail2ban --activate" }' | sh -x



