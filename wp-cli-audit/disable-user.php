<?php
/**
 * Disables a WordPress user by resetting their password, removing capabilities, and regenerating salts.
 *
 * @package WP_CLI
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Implements the 'disable-user' command.
 */
class Disable_User_Command extends WP_CLI_Command {

	/**
	 * Disables a user, resets their password, removes capabilities, and regenerates salts.
	 *
	 * ## OPTIONS
	 *
	 * <user_login>
	 * : The login name of the user to disable.
	 *
	 * ## EXAMPLES
	 *
	 *     wp disable-user suspicious_user
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Associated arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		list( $user_login ) = $args;

		// 1. Find the user
		$user = get_user_by( 'login', $user_login );
		if ( ! $user ) {
			WP_CLI::error( "User with login '{$user_login}' not found." );
		}

		WP_CLI::log( "Disabling user '{$user_login}' (ID: {$user->ID})..." );

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

		// 6. Suggestions for further actions
		WP_CLI::success( "User '{$user_login}' has been disabled and sessions terminated." );
		WP_CLI::log( "\nFor complete security, consider the following additional steps:" );
		WP_CLI::log( "1. Lock the user account (if command is available):" );
		WP_CLI::log( "   wp user lock {$user_login}" );
		WP_CLI::log( "2. Reassign their content to another user (e.g., 'admin') and delete the account:" );
		WP_CLI::log( "   wp user delete {$user_login} --reassign=1" );
		WP_CLI::log( "   (Replace '1' with the user ID of the user you want to reassign content to.)" );
	}
}

WP_CLI::add_command( 'disable-user', 'Disable_User_Command' );
