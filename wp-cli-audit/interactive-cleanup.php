<?php
/**
 * WP-CLI script to audit and interactively remove inactive themes and plugins.
 *
 * This script is multisite-aware and provides detailed, sequential logging.
 * It performs a backup by default to a writable /tmp/ directory.
 *
 * USAGE: sudo -u <user> wp eval-file interactive-cleanup.php
 * TO SKIP BACKUP: sudo -u <user> wp eval-file interactive-cleanup.php --no-backup
 * VERSION: 4.0
 */

// Check for the --no-backup flag.
$perform_backup = ! in_array( '--no-backup', $GLOBALS['argv'], true );

WP_CLI::log( WP_CLI::colorize( "\n%B--- Interactive Multisite Cleanup Script (v4.0) ---%n" ) );
WP_CLI::warning( "This script will permanently delete files from your filesystem." );

// --- BACKUP SECTION (RELIABLE VERSION) ---
if ( $perform_backup ) {
    $backup_filename = '/tmp/backup-' . date( 'Y-m-d-His' ) . '.sql';
    $backup_command = "db export {$backup_filename}";
    
    WP_CLI::log( "\nAttempting database backup to '{$backup_filename}'..." );
    
    $result = WP_CLI::runcommand( $backup_command, [ 'return' => 'all', 'launch' => false, 'exit_error' => false ] );

    if ( $result->return_code === 0 ) {
        WP_CLI::success( trim( $result->stdout ) );
    } else {
        WP_CLI::error( "Backup failed: " . trim( $result->stderr ) );
        if ( ! WP_CLI::confirm( "CRITICAL: Backup failed. Do you still want to continue with the cleanup?" ) ) {
            WP_CLI::halt( "Aborted by user due to backup failure." );
        }
    }
} else {
    WP_CLI::warning( "\n--no-backup flag detected. Skipping backup. Proceeding with caution." );
}


/**
 * Finds themes that are truly inactive across a multisite network.
 */
function find_inactive_themes() {
    WP_CLI::log( WP_CLI::colorize( "\n%G--- Starting Theme Audit ---%n" ) );

    $all_themes     = wp_get_themes();
    $active_themes  = [];
    $sites          = get_sites();
    $default_theme  = get_site_option( 'default_theme' );

    WP_CLI::log( 'Found all installed themes: ' . implode( ', ', array_keys( $all_themes ) ) );
    WP_CLI::log( 'Network default theme is: ' . ( $default_theme ?: 'Not set' ) );
    
    if ($default_theme) {
        $active_themes[ $default_theme ] = true;
    }

    WP_CLI::log( "Checking active themes on each site..." );
    foreach ( $sites as $site ) {
        switch_to_blog( $site->blog_id );
        $stylesheet = get_stylesheet();
        $template = get_template();
        WP_CLI::log( "  - Site {$site->blog_id} ({$site->domain}{$site->path}): Active stylesheet is '{$stylesheet}', Active parent is '{$template}'." );
        $active_themes[ $stylesheet ] = true;
        $active_themes[ $template ] = true;
        restore_current_blog();
    }
    
    WP_CLI::log( "\n" . '=> Compiled list of all themes to KEEP (active, parent, or default): ' . implode( ', ', array_unique(array_keys( $active_themes ))) );

    $inactive_themes = [];
    foreach ( $all_themes as $slug => $theme ) {
        if ( ! isset( $active_themes[ $slug ] ) ) {
            $inactive_themes[] = $slug;
        }
    }

    return $inactive_themes;
}

/**
 * Finds plugins that are truly inactive across a multisite network.
 */
function find_inactive_plugins() {
    WP_CLI::log( WP_CLI::colorize( "\n%G--- Starting Plugin Audit ---%n" ) );
    
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $all_plugins     = get_plugins();
    $inactive_plugins = [];
    $sites           = get_sites();

    WP_CLI::log( 'Found all installed plugin files: ' . implode( ', ', array_keys( $all_plugins ) ) );
    WP_CLI::log( 'Checking activation status for each plugin...' );

    foreach ( array_keys( $all_plugins ) as $plugin_file ) {
        $is_active_somewhere = false;

        if ( is_plugin_active_for_network( $plugin_file ) ) {
            $is_active_somewhere = true;
            WP_CLI::log( "  - Plugin '{$plugin_file}' is Network Active. Keeping." );
        } else {
            foreach ( $sites as $site ) {
                switch_to_blog( $site->blog_id );
                if ( is_plugin_active( $plugin_file ) ) {
                    $is_active_somewhere = true;
                    WP_CLI::log( "  - Plugin '{$plugin_file}' is active on Site {$site->blog_id}. Keeping." );
                    restore_current_blog();
                    break;
                }
                restore_current_blog();
            }
        }
        
        if ( ! $is_active_somewhere ) {
            $slug = dirname( $plugin_file );
            if ($slug !== '.' && !in_array($slug, $inactive_plugins)) { 
                $inactive_plugins[] = $slug;
                WP_CLI::log( "  - Plugin '{$plugin_file}' is not active anywhere. Marking slug '{$slug}' for potential deletion." );
            }
        }
    }
    
    return $inactive_plugins;
}

/**
 * Interactively prompts the user to delete a list of items.
 */
function process_items_for_deletion( $items, $type ) {
    if ( empty( $items ) ) {
        WP_CLI::success( "\nAudit complete. No inactive {$type}s found to clean up." );
        return;
    }

    WP_CLI::log( WP_CLI::colorize( "\n%Y--- Audit Results for {$type}s ---%n" ) );
    WP_CLI::warning( "The following {$type}s appear to be inactive across the entire network:" );
    foreach ( $items as $item ) {
        WP_CLI::log( "- " . $item );
    }

    if ( WP_CLI::confirm( "\nDo you want to proceed with deleting these {$type}s interactively?" ) ) {
        $all = false;
        foreach ( $items as $item ) {
            if ( ! $all ) {
                $prompt = "Delete {$type} '{$item}'?";
                $confirm = WP_CLI::confirm( $prompt, [ 'continue' => 'all' ] );
                if ( 'all' === $confirm ) {
                    $all = true;
                } elseif ( ! $confirm ) {
                    WP_CLI::warning( "Skipped '{$item}'." );
                    continue;
                }
            }
            
            WP_CLI::log( "Deleting {$type} '{$item}'..." );
            $result = WP_CLI::runcommand( "{$type} delete {$item}", [ 'return' => 'all', 'launch' => false, 'exit_error' => false ] );

            if ( $result->return_code === 0 ) {
                WP_CLI::success( trim( $result->stdout ) );
            } else {
                WP_CLI::error( trim( $result->stderr ), false );
            }
        }
    } else {
        WP_CLI::warning( "Aborted {$type} cleanup process." );
    }
}

// --- Main Execution Flow ---
$inactive_themes = find_inactive_themes();
process_items_for_deletion( $inactive_themes, 'theme' );

$inactive_plugins = find_inactive_plugins();
process_items_for_deletion( $inactive_plugins, 'plugin' );

WP_CLI::log( "" );
WP_CLI::success( "Cleanup script finished." );