<?php
/**
 * WP-CLI script to find content modified within a specific date range across a multisite network.
 *
 * To run: wp eval-file find-modified-content.php
 */

// --- Configuration ---
if ( isset( $args[0] ) && isset( $args[1] ) ) {
    $start_date = $args[0];
    $end_date   = $args[1];
} else {
    // Fallback or error
    WP_CLI::error( "Please provide start and end dates: wp eval-file script.php YYYY-MM-DD YYYY-MM-DD" );
}

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
    'modified_by',
    'url',
];

/**
 * Queries posts within the date range for the current site and adds them to the report.
 *
 * @param array  &$report_data The array to accumulate report data.
 * @param string $start_date The start of the date range.
 * @param string $end_date   The end of the date range.
 */
function query_and_process_posts( &$report_data, $start_date, $end_date ) {
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
            $modified_by = 'N/A';

            // Try to get the author of the last revision
            $revisions = wp_get_post_revisions( $post->ID, ['posts_per_page' => 1, 'orderby' => 'post_date', 'order' => 'DESC'] );
            if ( ! empty( $revisions ) ) {
                $latest_revision = array_shift( $revisions );
                $user = get_user_by( 'id', $latest_revision->post_author );
                if ( $user ) {
                    $modified_by = $user->display_name;
                }
            } else {
                // If no revisions, fall back to the post author
                $user = get_user_by( 'id', $post->post_author );
                if ( $user ) {
                    $modified_by = $user->display_name;
                }
            }

            $report_data[] = [
                'site_url'      => get_home_url(),
                'ID'            => $post->ID,
                'post_type'     => $post->post_type,
                'post_title'    => $post->post_title,
                'post_modified' => $post->post_modified,
                'modified_by'   => $modified_by,
                'url'           => get_permalink( $post->ID ),
            ];
        }
    }
}

if ( is_multisite() ) {
    // Get all sites in the multisite network.
    $sites = get_sites();

    foreach ( $sites as $site ) {
        // Switch the context to the current site in the loop.
        switch_to_blog( $site->blog_id );
        query_and_process_posts( $report_data, $start_date, $end_date );
        // IMPORTANT: Restore the context back to the original site before the next loop iteration.
        restore_current_blog();
    }
} else {
    // Handle single site.
    query_and_process_posts( $report_data, $start_date, $end_date );
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
