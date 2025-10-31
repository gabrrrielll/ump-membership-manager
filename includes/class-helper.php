<?php

/**
 * Helper functions
 *
 * @package UMP_Membership_Manager
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

class UMP_MM_Helper
{
    /**
     * Get all active memberships
     *
     * @return array Array of membership objects with id, name, label
     */
    public static function get_active_memberships()
    {
        if (! class_exists('\Indeed\Ihc\Db\Memberships')) {
            return array();
        }

        $memberships = \Indeed\Ihc\Db\Memberships::getAll();

        if (! $memberships || ! is_array($memberships)) {
            return array();
        }

        $active_memberships = array();
        foreach ($memberships as $id => $membership) {
            // Status 0 means active in IHC
            if (isset($membership['status']) && $membership['status'] == 0) {
                $active_memberships[ $id ] = array(
                    'id'    => $id,
                    'name'  => isset($membership['name']) ? $membership['name'] : '',
                    'label' => isset($membership['label']) ? $membership['label'] : '',
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
    public static function is_membership_active($membership_id)
    {
        if (! class_exists('\Indeed\Ihc\Db\Memberships')) {
            return false;
        }

        $membership = \Indeed\Ihc\Db\Memberships::getOne($membership_id);

        if (! $membership) {
            return false;
        }

        // Status 0 means active
        return isset($membership['status']) && $membership['status'] == 0;
    }

    /**
     * Check if user has active subscription for a membership
     *
     * @param int $user_id User ID
     * @param int $membership_id Membership ID
     * @return bool
     */
    public static function user_has_active_membership($user_id, $membership_id)
    {
        global $wpdb;

        if (! $user_id || ! $membership_id) {
            return false;
        }

        $current_time = current_time('mysql');

        // Check directly in database for an ACTIVE subscription
        $subscription = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, expire_time, start_time, status 
				FROM {$wpdb->prefix}ihc_user_levels 
				WHERE user_id = %d 
				AND level_id = %d 
				AND status = 1
				ORDER BY id DESC
				LIMIT 1",
                $user_id,
                $membership_id
            )
        );

        if (! $subscription) {
            return false;
        }

        // Check if subscription is actually active (not expired and started)
        $now = strtotime($current_time);

        // Check start time
        if (! empty($subscription->start_time) && $subscription->start_time !== '0000-00-00 00:00:00') {
            $start_time = strtotime($subscription->start_time);
            if ($start_time > $now) {
                // Subscription hasn't started yet
                return false;
            }
        }

        // Check expire time
        if (! empty($subscription->expire_time) && $subscription->expire_time !== '0000-00-00 00:00:00') {
            $expire_time = strtotime($subscription->expire_time);
            if ($expire_time < $now) {
                // Subscription has expired
                return false;
            }
        }

        // If we got here, subscription exists and dates are valid - it's active
        return true;
    }

    /**
     * Get users with specific active membership
     * Sorted by membership assignment date (newest first)
     *
     * @param int $membership_id Membership ID
     * @return array Array of user IDs sorted by assignment date (newest first)
     */
    public static function get_users_with_membership($membership_id)
    {
        global $wpdb;

        if (! $membership_id) {
            return array();
        }

        $current_time = current_time('mysql');

        // Get user IDs with their assignment date and ID (for sorting by newest)
        // Using id field as it increases with time, so higher id = newer assignment
        $user_subscriptions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id, update_time, start_time
				FROM {$wpdb->prefix}ihc_user_levels 
				WHERE level_id = %d 
				AND status = 1
				AND (expire_time = '0000-00-00 00:00:00' OR expire_time > %s)
				AND (start_time = '0000-00-00 00:00:00' OR start_time <= %s)
				ORDER BY id DESC",
                $membership_id,
                $current_time,
                $current_time
            )
        );

        // Filter only those who are really active and collect with dates
        $active_users_with_dates = array();
        foreach ($user_subscriptions as $subscription) {
            $user_id = (int) $subscription->user_id;

            // Verify user really has active membership
            if (self::user_has_active_membership($user_id, $membership_id)) {
                // Use update_time as assignment date, fallback to start_time
                // Also use id for secondary sorting (newer id = newer assignment)
                $assignment_date = ! empty($subscription->update_time) && $subscription->update_time !== '0000-00-00 00:00:00'
                    ? $subscription->update_time
                    : (! empty($subscription->start_time) && $subscription->start_time !== '0000-00-00 00:00:00' ? $subscription->start_time : date('Y-m-d H:i:s', 0));

                $active_users_with_dates[] = array(
                    'user_id' => $user_id,
                    'assignment_date' => $assignment_date,
                    'id' => (int) $subscription->id, // Use id as tiebreaker and primary sort
                );
            }
        }

        // Sort by ID descending first (newest first), then by date
        usort($active_users_with_dates, function ($a, $b) {
            // Primary sort: by id (higher id = newer)
            $id_diff = $b['id'] - $a['id'];
            if ($id_diff !== 0) {
                return $id_diff;
            }
            // Secondary sort: by date
            return strtotime($b['assignment_date']) - strtotime($a['assignment_date']);
        });

        // Return only user IDs in sorted order
        return array_map(function ($item) {
            return $item['user_id'];
        }, $active_users_with_dates);
    }

    /**
     * Add membership to user (only if membership is active)
     *
     * @param int $user_id User ID
     * @param int $membership_id Membership ID
     * @return bool|WP_Error Success or error
     */
    public static function add_membership_to_user($user_id, $membership_id)
    {
        if (! $user_id || ! $membership_id) {
            return new WP_Error('invalid_params', __('Parametri invalizi.', 'ump-membership-manager'));
        }

        // Check if membership is active
        if (! self::is_membership_active($membership_id)) {
            return new WP_Error('inactive_membership', __('Membership-ul nu este activ.', 'ump-membership-manager'));
        }

        // Check if user already has this membership active
        // Only skip if membership is TRULY active (not expired, not pending)
        $has_active = self::user_has_active_membership($user_id, $membership_id);

        if ($has_active) {
            // Double-check: verify subscription is really active by checking dates
            global $wpdb;
            $current_time = current_time('mysql');
            $now = strtotime($current_time);

            $subscription = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT expire_time, start_time, status 
					FROM {$wpdb->prefix}ihc_user_levels 
					WHERE user_id = %d 
					AND level_id = %d 
					AND status = 1
					ORDER BY id DESC
					LIMIT 1",
                    $user_id,
                    $membership_id
                )
            );

            if ($subscription) {
                // Check if it's really active
                $is_really_active = true;

                // Check start time
                if (! empty($subscription->start_time) && $subscription->start_time !== '0000-00-00 00:00:00') {
                    $start_time = strtotime($subscription->start_time);
                    if ($start_time > $now) {
                        $is_really_active = false;
                    }
                }

                // Check expire time
                if ($is_really_active && ! empty($subscription->expire_time) && $subscription->expire_time !== '0000-00-00 00:00:00') {
                    $expire_time = strtotime($subscription->expire_time);
                    if ($expire_time < $now) {
                        $is_really_active = false;
                    }
                }

                // Only return error if it's REALLY active
                if ($is_really_active) {
                    return new WP_Error('already_has_membership', __('User-ul are deja acest membership activ.', 'ump-membership-manager'));
                }
                // If not really active, continue and add/update the membership
            }
        }

        if (! class_exists('\Indeed\Ihc\UserSubscriptions')) {
            return new WP_Error('ihc_not_available', __('IHC nu este disponibil.', 'ump-membership-manager'));
        }

        // Assign membership
        $result = \Indeed\Ihc\UserSubscriptions::assign($user_id, $membership_id);

        if ($result === false) {
            return new WP_Error('assign_failed', __('Nu s-a putut atribui membership-ul.', 'ump-membership-manager'));
        }

        // Activate it - makeComplete returns number of affected rows, can be 0 or false on failure
        $activate = \Indeed\Ihc\UserSubscriptions::makeComplete($user_id, $membership_id);

        // makeComplete can return 0 rows if subscription already exists and is updated
        // Only fail if it explicitly returns false
        if ($activate === false) {
            // Check if subscription actually exists and is active now
            if (self::user_has_active_membership($user_id, $membership_id)) {
                // Membership is actually active, so it worked
                return true;
            }
            return new WP_Error('activate_failed', __('Nu s-a putut activa membership-ul.', 'ump-membership-manager'));
        }

        return true;
    }
}
