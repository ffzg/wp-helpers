<?php
/**
 * WP-CLI script to audit user roles and metadata across a multisite network or single site.
 *
 * This script iterates through all sites, lists users with roles on each site,
 * and includes site metadata and user's last login time.
 * It also identifies users who are not assigned to any site.
 *
 * Run with: wp eval-file user-audit.php
 */

$is_multisite = is_multisite();

// Get all sites.
if ( $is_multisite ) {
    $sites = get_sites();
} else {
    $sites = [
        (object) [
            'blog_id'      => get_current_blog_id(),
            'domain'       => parse_url( home_url(), PHP_URL_HOST ),
            'path'         => parse_url( home_url(), PHP_URL_PATH ) ?: '/',
            'last_updated' => get_blog_details( get_current_blog_id() )->last_updated ?? 'N/A',
        ]
    ];
}

$report_data = [];
$users_found = []; // Track user IDs that have at least one site assignment

// Get all users in the system to find orphans later.
$all_users = get_users( $is_multisite ? [ 'blog_id' => 0 ] : [] );
$super_admins = $is_multisite ? get_super_admins() : [];

$fields = [
    'site_id',
    'site_url',
    'site_last_updated',
    'user_login',
    'user_email',
    'user_registered',
    'user_roles',
    'super_admin',
    'user_last_login'
];

WP_CLI::log( 'Starting user audit across ' . count( $sites ) . ' sites...' );

foreach ( $sites as $site ) {
    if ( $is_multisite ) {
        // Attempt to switch to blog. Handle potential missing tables.
        switch_to_blog( $site->blog_id );
        
        // Check if switching actually worked (WP doesn't always throw error, but we can check if options table exists)
        global $wpdb;
        $table_name = $wpdb->prefix . 'options';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            WP_CLI::warning( "Tables for site {$site->blog_id} are missing. Skipping." );
            restore_current_blog();
            continue;
        }
    }

    $users_on_this_site = get_users( [ 'blog_id' => $site->blog_id ] );

    foreach ( $users_on_this_site as $user ) {
        if ( empty( $user->roles ) ) {
            continue;
        }

        $users_found[$user->ID] = true;
        
        // Get the last login timestamp stored by Wordfence.
        $last_login_timestamp = get_user_meta( $user->ID, 'wfls-last-login', true );
        $last_login_date = $last_login_timestamp ? date( 'Y-m-d H:i:s', (int) $last_login_timestamp ) : 'N/A';

        $report_data[] = [
            'site_id'           => $site->blog_id,
            'site_url'          => $site->domain . $site->path,
            'site_last_updated' => $site->last_updated ?? 'N/A',
            'user_login'        => $user->user_login,
            'user_email'        => $user->user_email,
            'user_registered'   => $user->user_registered,
            'user_roles'        => implode( ', ', $user->roles ),
            'super_admin'       => in_array( $user->user_login, $super_admins ) ? 'Yes' : 'No',
            'user_last_login'   => $last_login_date,
        ];
    }

    if ( $is_multisite ) {
        restore_current_blog();
    }
}

// Add orphans (users found in global list but not assigned to any site)
foreach ( $all_users as $user ) {
    if ( ! isset( $users_found[$user->ID] ) ) {
        $last_login_timestamp = get_user_meta( $user->ID, 'wfls-last-login', true );
        $last_login_date = $last_login_timestamp ? date( 'Y-m-d H:i:s', (int) $last_login_timestamp ) : 'N/A';

        $report_data[] = [
            'site_id'           => 'N/A',
            'site_url'          => 'N/A',
            'site_last_updated' => 'N/A',
            'user_login'        => $user->user_login,
            'user_email'        => $user->user_email,
            'user_registered'   => $user->user_registered,
            'user_roles'        => 'None',
            'super_admin'       => in_array( $user->user_login, $super_admins ) ? 'Yes' : 'No',
            'user_last_login'   => $last_login_date,
        ];
    }
}

WP_CLI::success( 'Audit complete. Found ' . count( $all_users ) . ' total users.' );

WP_CLI\Utils\format_items( 'table', $report_data, $fields );
