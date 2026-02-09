<?php

/**
 * Auto rules handler
 *
 * @package UMP_Membership_Manager
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

class UMP_MM_Auto_Rules
{
    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        // Hook into IHC subscription activation (various hooks for different IHC versions/scenarios)
        add_action('ihc_action_after_subscription_activated', array( $this, 'handle_subscription_activated' ), 10, 4);
        add_action('ihc_action_after_subscription_first_time_activated', array( $this, 'handle_subscription_activated_3_params' ), 10, 3);
        add_action('ihc_action_after_subscription_renew_activated', array( $this, 'handle_subscription_activated_3_params' ), 10, 3);
    }

    /**
     * Bug #3 Fix: Prevent race conditions with locking
     * Check if rule is currently being processed
     */
    private function acquire_lock($user_id, $membership_id, $target_id)
    {
        $lock_key = 'ump_mm_lock_' . $user_id . '_' . $membership_id . '_' . $target_id;
        $lock = get_transient($lock_key);
        
        if ($lock) {
            return false; // Already processing
        }
        
        // Set lock for 30 seconds
        set_transient($lock_key, true, 30);
        return true;
    }
    
    /**
     * Release lock
     */
    private function release_lock($user_id, $membership_id, $target_id)
    {
        $lock_key = 'ump_mm_lock_' . $user_id . '_' . $membership_id . '_' . $target_id;
        delete_transient($lock_key);
    }
    
    /**
     * Bug #3 Fix: Detect circular dependencies
     */
    private function check_circular_dependency($trigger_id, $target_id, $checked = array())
    {
        // Prevent infinite recursion
        if (in_array($trigger_id, $checked)) {
            return true; // Circular dependency found
        }
        
        $checked[] = $trigger_id;
        $auto_rules = get_option('ump_mm_auto_rules', array());
        
        foreach ($auto_rules as $rule) {
            if ((int) $rule['trigger'] === (int) $target_id) {
                // target triggers another rule
                if ((int) $rule['target'] === (int) $trigger_id) {
                    return true; // Direct circular dependency
                }
                // Check further
                if ($this->check_circular_dependency($trigger_id, (int) $rule['target'], $checked)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Wrapper for hooks with 3 parameters
     */
    public function handle_subscription_activated_3_params($user_id, $membership_id, $args)
    {
        // For first time or renew hooks, we can infer the state or just pass it as true/false
        // but handle_subscription_activated check user_has_active_membership anyway
        $this->handle_subscription_activated($user_id, $membership_id, null, $args);
    }

    /**
     * Handle subscription activation
     *
     * @param int $user_id User ID
     * @param int $membership_id Membership ID that was activated
     * @param bool|null $first_time Is this first time activation (can be null if unknown)
     * @param array $args Additional arguments
     */
    public function handle_subscription_activated($user_id, $membership_id, $first_time, $args)
    {
        // Only process if membership is really active
        if (! UMP_MM_Helper::user_has_active_membership($user_id, $membership_id)) {
            return;
        }

        // Get auto rules
        $auto_rules = get_option('ump_mm_auto_rules', array());

        if (empty($auto_rules)) {
            return;
        }

        // Find rules that match this membership
        foreach ($auto_rules as $rule) {
            if (! isset($rule['trigger']) || ! isset($rule['target'])) {
                continue;
            }

            // Check if triggered membership matches
            if ((int) $rule['trigger'] === (int) $membership_id) {
                $target_membership_id = (int) $rule['target'];

                // Bug #3 Fix: Try to acquire lock
                if (!$this->acquire_lock($user_id, $membership_id, $target_membership_id)) {
                    error_log(sprintf(
                        'UMP MM: Skipped auto rule for user %d - already being processed (lock active)',
                        $user_id
                    ));
                    continue;
                }

                // Bug #3 Fix: Check for circular dependencies
                if ($this->check_circular_dependency($membership_id, $target_membership_id)) {
                    error_log(sprintf(
                        'UMP MM: Skipped auto rule for user %d - circular dependency detected between %d and %d',
                        $user_id,
                        $membership_id,
                        $target_membership_id
                    ));
                    $this->release_lock($user_id, $membership_id, $target_membership_id);
                    continue;
                }

                // Verify target membership is active
                if (! UMP_MM_Helper::is_membership_active($target_membership_id)) {
                    // Log that we skipped because target is inactive
                    error_log(sprintf(
                        'UMP MM: Skipped auto rule for user %d - target membership %d is not active',
                        $user_id,
                        $target_membership_id
                    ));
                    $this->release_lock($user_id, $membership_id, $target_membership_id);
                    continue;
                }

                // Add or extend the target membership
                // Note: We always try to add/extend, even if user already has it active,
                // because the rule should apply every time the trigger membership is purchased
                $result = UMP_MM_Helper::add_membership_to_user($user_id, $target_membership_id, true);

                if (is_wp_error($result)) {
                    // Log error
                    error_log(sprintf(
                        'UMP MM: Failed to add auto membership %d to user %d: %s',
                        $target_membership_id,
                        $user_id,
                        $result->get_error_message()
                    ));
                } else {
                    // Log success
                    error_log(sprintf(
                        'UMP MM: Successfully added auto membership %d to user %d (triggered by membership %d)',
                        $target_membership_id,
                        $user_id,
                        $membership_id
                    ));
                }

                // Release lock
                $this->release_lock($user_id, $membership_id, $target_membership_id);
            }
        }
    }
}
