<?php
/**
 * Uninstall script for UMP Membership Manager
 * Bug #18 Fix: Proper cleanup on uninstall
 *
 * @package UMP_Membership_Manager
 */

// If uninstall not called from WordPress, exit
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Security check
if (! current_user_can('activate_plugins')) {
    exit;
}

global $wpdb;

// Delete plugin options
delete_option('ump_mm_auto_rules');

// Delete all transients (rate limits and locks)
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ump_mm_%' OR option_name LIKE '_transient_timeout_ump_mm_%'");

// Log uninstall
error_log('UMP Membership Manager: Plugin uninstalled, all data cleaned up');
