<?php
/**
 * WP-CLI script to audit user roles and metadata across a multisite network.
 *
 * This script iterates through all sites, lists users with roles on each site,
 * and includes site metadata and user's last login time.
 *
 * Run with: wp eval-file user-audit.php
 */

// Get all sites in the network. The get_sites() function is the modern way to do this.
$sites = get_sites();

// This array will hold all the data we collect before printing it.
$report_data = [];

// Define the headers for our final output table.
$fields = [
    'site_id',
    'site_url',
    'site_last_updated',
    'user_login',
    'user_roles',
    'user_last_login'
];

WP_CLI::log( 'Starting user audit across ' . count( $sites ) . ' sites...' );

// Loop through each site object.
foreach ( $sites as $site ) {

    // IMPORTANT: Switch the context to the current site in the loop.
    // All WordPress functions will now operate on this site.
    switch_to_blog( $site->blog_id );

    // Get all users who have an explicit role on this specific blog.
    $users_on_this_site = get_users( [ 'blog_id' => $site->blog_id ] );

    foreach ( $users_on_this_site as $user ) {
        // A user might be in the network but have no role on this specific site.
        // We only want to list users who have assigned roles here.
        if ( empty( $user->roles ) ) {
            continue;
        }

        // Get the last login timestamp stored by Wordfence.
        // The 'true' argument ensures a single value is returned.
        $last_login_timestamp = get_user_meta( $user->ID, 'wfls-last-login', true );

        // Format the timestamp into a human-readable date, or show 'N/A' if it's not set.
        $last_login_date = $last_login_timestamp ? date( 'Y-m-d H:i:s', (int) $last_login_timestamp ) : 'N/A';
        
        // Add the collected data as a new row in our report array.
        $report_data[] = [
            'site_id'           => $site->blog_id,
            'site_url'          => $site->domain . $site->path,
            'site_last_updated' => $site->last_updated,
            'user_login'        => $user->user_login,
            'user_roles'        => implode( ', ', $user->roles ), // Convert roles array to a string
            'user_last_login'   => $last_login_date,
        ];
    }

    // IMPORTANT: Restore the context back to the original site.
    restore_current_blog();
}

// Check if we have super admins to list separately as they have access to all sites.
$super_admins = get_super_admins();
if ( ! empty( $super_admins ) ) {
    WP_CLI::success( 'Found ' . count( $report_data ) . ' user-site role assignments.' );
    WP_CLI::line( "\n" . WP_CLI::colorize( '%YNote:%n Super Admins have administrator rights on ALL sites.' ) );
    WP_CLI::line( 'Super Admins: ' . implode( ', ', $super_admins ) . "\n" );
}

// Use the powerful WP-CLI formatter to display the collected data in a clean table.
WP_CLI\Utils\format_items(
    'table',
    $report_data,
    $fields
);
