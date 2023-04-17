#!/bin/sh -xe

# work-around wp cli non-working errors because of plugins

echo "update wp_posts set comment_status='closed' where comment_status != 'closed'" | wp db query

echo "update wp_options set option_value = 'closed' where option_name = 'default_comment_status' and option_value != 'closed'" | wp db query

# this will also disable comments on media which are not disabled with above options
echo "update wp_options set option_value = 1 where option_name = 'close_comments_for_old_posts' and option_value != 1" | wp db query


