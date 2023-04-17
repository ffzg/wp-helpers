#!/bin/sh -xe

# work-around wp cli non-working errors because of plugins

echo "update wp_posts set comment_status='closed' where comment_status != 'closed'" | wp db query
