<?php
/**
 * WP-CLI script to find content modified within a specific date range across a multisite network.
 *
 * To run: wp eval-file find-modified-content.php
 */

// --- Configuration ---
$start_date = '2025-10-20';
$end_date   = '2025-10-25';

// --- Script Starts Here ---

WP_CLI::line( "Searching for content MODIFIED between $start_date and $end_date..." );

// This is the array that will hold all our results.
$report_data = [];

// Define the columns for the final output table.
$fields = [
    'site_url',
    'ID',
    'post_type',
    'post_title',
    'post_modified',
    'url',
];

// Get all sites in the multisite network.
$sites = get_sites();

foreach ( $sites as $site ) {
    // Switch the context to the current site in the loop.
    switch_to_blog( $site->blog_id );

    WP_CLI::log( 'Querying Site: ' . get_home_url() );

    // Arguments for the WordPress query. This is the PHP equivalent of the wp post list flags.
    $args = [
        'post_type'      => ['post', 'page', 'attachment'],
        'post_status'    => 'publish',
        'posts_per_page' => -1, // Get all matching posts, not just the first few.
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'date_query'     => [
            [
                'column'    => 'post_modified', // We are querying against the modified date.
                'after'     => "$start_date 00:00:00",
                'before'    => "$end_date 23:59:59",
                'inclusive' => true,
            ],
        ],
    ];

    // Run the query to get the posts.
    $posts = get_posts( $args );

    // If we found posts, process them and add them to our report data.
    if ( ! empty( $posts ) ) {
        foreach ( $posts as $post ) {
            $report_data[] = [
                'site_url'      => get_home_url(),
                'ID'            => $post->ID,
                'post_type'     => $post->post_type,
                'post_title'    => $post->post_title,
                'post_modified' => $post->post_modified,
                'url'           => get_permalink( $post->ID ),
            ];
        }
    }

    // IMPORTANT: Restore the context back to the original site before the next loop iteration.
    restore_current_blog();
}

if ( empty( $report_data ) ) {
    WP_CLI::warning( 'No content found matching the criteria.' );
} else {
    WP_CLI::success( 'Found ' . count( $report_data ) . ' matching items.' );

    // Use the powerful WP-CLI formatter to display the collected data in a clean table.
    WP_CLI\Utils\format_items(
        'table', // Format as a table.
        $report_data, // The data to display.
        $fields // The columns to show.
    );
}

WP_CLI::line( 'Search complete.' );
