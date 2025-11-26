<?php
/**
 * Disables a WordPress user by resetting their password, removing capabilities, and regenerating salts.
 *
 * @package WP_CLI
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

// Check if a user login is provided as an argument.
if ( empty( $args ) ) {
	WP_CLI::error( 'Please provide a user login as an argument. Example: wp eval-file disable-user.php <user_login>' );
}

$user_login = $args[0];

// 1. Find the user
$user = get_user_by( 'login', $user_login );
if ( ! $user ) {
	WP_CLI::error( "User with login '{$user_login}' not found." );
}

WP_CLI::log( "Disabling user '{$user_login}' (ID: {$user->ID})..." );

// Display current capabilities
WP_CLI::log( "Current capabilities for user '{$user_login}':" );
if ( ! empty( $user->allcaps ) ) {
	foreach ( $user->allcaps as $cap => $value ) {
		WP_CLI::log( "  - {$cap}: " . ( $value ? 'true' : 'false' ) );
	}
}
else {
	WP_CLI::log( "  (No specific capabilities found)" );
}

// 2. Reset the user's password to a long random string
$new_password = wp_generate_password( 64, true, true );
wp_set_password( $new_password, $user->ID );
WP_CLI::log( "Password reset for user '{$user_login}'. The new password is not displayed for security." );

// 3. Remove all capabilities from the user
$user->remove_all_caps();
WP_CLI::log( "All capabilities removed for user '{$user_login}'." );

// 4. List and destroy user sessions
WP_CLI::log( "Listing active sessions for user '{$user_login}':" );
WP_CLI::runcommand( "user session list {$user_login}" );
WP_CLI::log( "Destroying all sessions for user '{$user_login}'..." );
WP_CLI::runcommand( "user session destroy {$user_login} --all" );

// 5. Regenerate WordPress salts
WP_CLI::log( "Regenerating WordPress salts in wp-config.php..." );
WP_CLI::runcommand( 'config shuffle-salts' );

// 6. Multisite specific actions
if ( is_multisite() ) {
	WP_CLI::log( "Multisite detected. Performing additional checks..." );

	// Revoke Super Admin privileges if the user has them
	if ( is_super_admin( $user->ID ) ) {
		WP_CLI::log( "User '{$user_login}' is a Super Admin. Revoking privileges..." );
		if ( revoke_super_admin( $user->ID ) ) {
			WP_CLI::log( "Super Admin privileges revoked." );
		} else {
			WP_CLI::warning( "Failed to revoke Super Admin privileges." );
		}
	}

	// Mark user as spam (effectively disables them across the network)
	WP_CLI::log( "Marking user '{$user_login}' as spam to prevent login across the network..." );
	wp_update_user( array( 'ID' => $user->ID, 'spam' => 1 ) );
	WP_CLI::log( "User '{$user_login}' marked as spam." );
}

// 6. Suggestions for further actions
WP_CLI::success( "User '{$user_login}' has been disabled and sessions terminated." );
WP_CLI::log( "\nFor complete security, consider the following additional steps:" );
WP_CLI::log( "1. Reassign their content to another user (e.g., 'admin') and delete the account:" );
WP_CLI::log( "   wp user delete {$user_login} --reassign=1" );
WP_CLI::log( "   (Replace '1' with the user ID of the user you want to reassign content to.)" );
