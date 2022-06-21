#!/bin/sh -xe

wp post list --post-status=publish --post_type=post --comment_status=open --format=ids \
	| xargs -d ' ' -I % wp post update % --comment_status=closed

wp option update default_comment_status closed
