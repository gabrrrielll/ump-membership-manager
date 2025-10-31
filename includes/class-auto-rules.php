<?php
/**
 * Auto rules handler
 *
 * @package UMP_Membership_Manager
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UMP_MM_Auto_Rules {
	
	/**
	 * Single instance
	 */
	private static $instance = null;
	
	/**
	 * Get instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor
	 */
	private function __construct() {
		// Hook into IHC subscription activation
		add_action( 'ihc_action_after_subscription_activated', array( $this, 'handle_subscription_activated' ), 10, 4 );
	}
	
	/**
	 * Handle subscription activation
	 * 
	 * @param int $user_id User ID
	 * @param int $membership_id Membership ID that was activated
	 * @param bool $first_time Is this first time activation
	 * @param array $args Additional arguments
	 */
	public function handle_subscription_activated( $user_id, $membership_id, $first_time, $args ) {
		// Only process if membership is really active
		if ( ! UMP_MM_Helper::user_has_active_membership( $user_id, $membership_id ) ) {
			return;
		}
		
		// Get auto rules
		$auto_rules = get_option( 'ump_mm_auto_rules', array() );
		
		if ( empty( $auto_rules ) ) {
			return;
		}
		
		// Find rules that match this membership
		foreach ( $auto_rules as $rule ) {
			if ( ! isset( $rule['trigger'] ) || ! isset( $rule['target'] ) ) {
				continue;
			}
			
			// Check if triggered membership matches
			if ( (int) $rule['trigger'] === (int) $membership_id ) {
				$target_membership_id = (int) $rule['target'];
				
				// Verify target membership is active
				if ( ! UMP_MM_Helper::is_membership_active( $target_membership_id ) ) {
					// Log that we skipped because target is inactive
					error_log( sprintf(
						'UMP MM: Skipped auto rule for user %d - target membership %d is not active',
						$user_id,
						$target_membership_id
					) );
					continue;
				}
				
				// Check if user already has this membership active
				if ( UMP_MM_Helper::user_has_active_membership( $user_id, $target_membership_id ) ) {
					// User already has it, skip
					continue;
				}
				
				// Add the target membership
				$result = UMP_MM_Helper::add_membership_to_user( $user_id, $target_membership_id );
				
				if ( is_wp_error( $result ) ) {
					// Log error
					error_log( sprintf(
						'UMP MM: Failed to add auto membership %d to user %d: %s',
						$target_membership_id,
						$user_id,
						$result->get_error_message()
					) );
				} else {
					// Log success
					error_log( sprintf(
						'UMP MM: Successfully added auto membership %d to user %d (triggered by membership %d)',
						$target_membership_id,
						$user_id,
						$membership_id
					) );
				}
			}
		}
	}
}

