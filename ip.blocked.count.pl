#!/usr/bin/perl
use warnings;
use strict;

@ARGV = ( '/tmp/ip.blocked' ) unless @ARGV;

my $count;

while(<>) {
	chomp;
	if ( s/^::ffff:(\S+)/$1/ ) {
		my @v = split(/\s+/, $_);
		$count->{$1} += $v[-3];
	}
}

foreach my $ip ( sort { $count->{$b} <=> $count->{$a} } keys %$count ) {
	print "$ip $count->{$ip}\n";
}
