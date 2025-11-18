#!/bin/sh -e
# This generates a 32-character random password and sets it, without you ever seeing it.
wp user update $1 --user_pass=$(openssl rand -base64 32)
wp user session list $1
