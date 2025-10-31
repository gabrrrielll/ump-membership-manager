<?php
/**
 * Helper functions
 *
 * @package UMP_Membership_Manager
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UMP_MM_Helper {
	
	/**
	 * Get all active memberships
	 * 
	 * @return array Array of membership objects with id, name, label
	 */
	public static function get_active_memberships() {
		if ( ! class_exists( '\Indeed\Ihc\Db\Memberships' ) ) {
			return array();
		}
		
		$memberships = \Indeed\Ihc\Db\Memberships::getAll();
		
		if ( ! $memberships || ! is_array( $memberships ) ) {
			return array();
		}
		
		$active_memberships = array();
		foreach ( $memberships as $id => $membership ) {
			// Status 0 means active in IHC
			if ( isset( $membership['status'] ) && $membership['status'] == 0 ) {
				$active_memberships[ $id ] = array(
					'id'    => $id,
					'name'  => isset( $membership['name'] ) ? $membership['name'] : '',
					'label' => isset( $membership['label'] ) ? $membership['label'] : '',
				);
			}
		}
		
		return $active_memberships;
	}
	
	/**
	 * Check if a membership is active
	 * 
	 * @param int $membership_id Membership ID
	 * @return bool
	 */
	public static function is_membership_active( $membership_id ) {
		if ( ! class_exists( '\Indeed\Ihc\Db\Memberships' ) ) {
			return false;
		}
		
		$membership = \Indeed\Ihc\Db\Memberships::getOne( $membership_id );
		
		if ( ! $membership ) {
			return false;
		}
		
		// Status 0 means active
		return isset( $membership['status'] ) && $membership['status'] == 0;
	}
	
	/**
	 * Check if user has active subscription for a membership
	 * 
	 * @param int $user_id User ID
	 * @param int $membership_id Membership ID
	 * @return bool
	 */
	public static function user_has_active_membership( $user_id, $membership_id ) {
		if ( ! class_exists( '\Indeed\Ihc\UserSubscriptions' ) ) {
			return false;
		}
		
		return \Indeed\Ihc\UserSubscriptions::isActive( $user_id, $membership_id );
	}
	
	/**
	 * Get users with specific active membership
	 * 
	 * @param int $membership_id Membership ID
	 * @return array Array of user IDs
	 */
	public static function get_users_with_membership( $membership_id ) {
		global $wpdb;
		
		if ( ! $membership_id ) {
			return array();
		}
		
		$current_time = current_time( 'mysql' );
		
		// Get user IDs who have this membership and it's active
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT user_id 
				FROM {$wpdb->prefix}ihc_user_levels 
				WHERE level_id = %d 
				AND status = 1
				AND (expire_time = '0000-00-00 00:00:00' OR expire_time > %s)
				AND (start_time = '0000-00-00 00:00:00' OR start_time <= %s)",
				$membership_id,
				$current_time,
				$current_time
			)
		);
		
		// Filter only those who are really active (using IHC function)
		$active_users = array();
		foreach ( $user_ids as $user_id ) {
			if ( self::user_has_active_membership( $user_id, $membership_id ) ) {
				$active_users[] = $user_id;
			}
		}
		
		return $active_users;
	}
	
	/**
	 * Add membership to user (only if membership is active)
	 * 
	 * @param int $user_id User ID
	 * @param int $membership_id Membership ID
	 * @return bool|WP_Error Success or error
	 */
	public static function add_membership_to_user( $user_id, $membership_id ) {
		if ( ! $user_id || ! $membership_id ) {
			return new WP_Error( 'invalid_params', __( 'Parametri invalizi.', 'ump-membership-manager' ) );
		}
		
		// Check if membership is active
		if ( ! self::is_membership_active( $membership_id ) ) {
			return new WP_Error( 'inactive_membership', __( 'Membership-ul nu este activ.', 'ump-membership-manager' ) );
		}
		
		// Check if user already has this membership active
		if ( self::user_has_active_membership( $user_id, $membership_id ) ) {
			return new WP_Error( 'already_has_membership', __( 'User-ul are deja acest membership activ.', 'ump-membership-manager' ) );
		}
		
		if ( ! class_exists( '\Indeed\Ihc\UserSubscriptions' ) ) {
			return new WP_Error( 'ihc_not_available', __( 'IHC nu este disponibil.', 'ump-membership-manager' ) );
		}
		
		// Assign membership
		$result = \Indeed\Ihc\UserSubscriptions::assign( $user_id, $membership_id );
		
		if ( $result === false ) {
			return new WP_Error( 'assign_failed', __( 'Nu s-a putut atribui membership-ul.', 'ump-membership-manager' ) );
		}
		
		// Activate it - makeComplete returns number of affected rows, can be 0 or false on failure
		$activate = \Indeed\Ihc\UserSubscriptions::makeComplete( $user_id, $membership_id );
		
		// makeComplete can return 0 rows if subscription already exists and is updated
		// Only fail if it explicitly returns false
		if ( $activate === false ) {
			// Check if subscription actually exists and is active now
			if ( self::user_has_active_membership( $user_id, $membership_id ) ) {
				// Membership is actually active, so it worked
				return true;
			}
			return new WP_Error( 'activate_failed', __( 'Nu s-a putut activa membership-ul.', 'ump-membership-manager' ) );
		}
		
		return true;
	}
}

