<?php
/**
 * Admin interface
 *
 * @package UMP_Membership_Manager
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

class UMP_MM_Admin
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
        // Use priority 99 to ensure IHC menu is registered first
        add_action('admin_menu', array( $this, 'add_admin_menu' ), 99);
        add_action('admin_enqueue_scripts', array( $this, 'enqueue_scripts' ));

        // AJAX handlers
        add_action('wp_ajax_ump_mm_search_users', array( $this, 'ajax_search_users' ));
        add_action('wp_ajax_ump_mm_add_membership_bulk', array( $this, 'ajax_add_membership_bulk' ));
        add_action('wp_ajax_ump_mm_save_auto_rule', array( $this, 'ajax_save_auto_rule' ));
        add_action('wp_ajax_ump_mm_delete_auto_rule', array( $this, 'ajax_delete_auto_rule' ));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        // Check if user has permissions
        if (! current_user_can('manage_options')) {
            return;
        }

        // Check if IHC menu exists
        global $submenu;
        $ihc_menu_exists = false;

        if (isset($submenu['ihc_manage'])) {
            $ihc_menu_exists = true;
        }

        // If IHC menu doesn't exist, add as standalone menu
        if (! $ihc_menu_exists) {
            add_menu_page(
                __('Membership Manager', 'ump-membership-manager'),
                __('Membership Manager', 'ump-membership-manager'),
                'manage_options',
                'ump-membership-manager',
                array( $this, 'render_admin_page' ),
                'dashicons-groups',
                30
            );
        } else {
            // Add as submenu under IHC main menu
            add_submenu_page(
                'ihc_manage',
                __('Membership Manager', 'ump-membership-manager'),
                __('Membership Manager', 'ump-membership-manager'),
                'manage_options',
                'ump-membership-manager',
                array( $this, 'render_admin_page' )
            );
        }
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook)
    {
        // Check if this is our admin page (various possible hook formats)
        $valid_hooks = array(
            'ultimate-membership-pro_page_ump-membership-manager',
            'toplevel_page_ump-membership-manager',
            'ihc_page_ump-membership-manager',
        );

        if (! in_array($hook, $valid_hooks, true) && strpos($hook, 'ump-membership-manager') === false) {
            return;
        }

        // Check user permissions
        if (! current_user_can('manage_options')) {
            return;
        }

        wp_enqueue_script(
            'ump-membership-manager-admin',
            UMP_MM_URL . 'assets/admin.js',
            array( 'jquery' ),
            UMP_MM_VERSION,
            true
        );

        wp_enqueue_style(
            'ump-membership-manager-admin',
            UMP_MM_URL . 'assets/admin.css',
            array(),
            UMP_MM_VERSION
        );

        wp_localize_script(
            'ump-membership-manager-admin',
            'umpMM',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('ump_mm_nonce'),
                'strings' => array(
                    'loading'      => __('Se încarcă...', 'ump-membership-manager'),
                    'noUsers'      => __('Nu s-au găsit utilizatori.', 'ump-membership-manager'),
                    'success'      => __('Succes!', 'ump-membership-manager'),
                    'error'        => __('Eroare!', 'ump-membership-manager'),
                    'selectUsers'  => __('Te rugăm să selectezi cel puțin un utilizator.', 'ump-membership-manager'),
                    'selectMembership' => __('Te rugăm să selectezi un membership.', 'ump-membership-manager'),
                ),
            )
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page()
    {
        // Check user permissions
        if (! current_user_can('manage_options')) {
            wp_die(__('Nu ai permisiuni suficiente pentru a accesa această pagină.', 'ump-membership-manager'));
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'search';
        ?>
		<div class="wrap ump-mm-admin-wrap">
			<h1><?php esc_html_e('UMP Membership Manager', 'ump-membership-manager'); ?></h1>
			
			<nav class="nav-tab-wrapper">
				<a href="?page=ump-membership-manager&tab=search" class="nav-tab <?php echo 'search' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e('Căutare și Gestionare', 'ump-membership-manager'); ?>
				</a>
				<a href="?page=ump-membership-manager&tab=auto-rules" class="nav-tab <?php echo 'auto-rules' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e('Reguli Automate', 'ump-membership-manager'); ?>
				</a>
			</nav>
			
			<div class="ump-mm-tab-content">
				<?php
                if ('search' === $active_tab) {
                    $this->render_search_tab();
                } elseif ('auto-rules' === $active_tab) {
                    $this->render_auto_rules_tab();
                }
        ?>
			</div>
		</div>
		<?php
    }

    /**
     * Render search tab
     */
    private function render_search_tab()
    {
        $memberships = UMP_MM_Helper::get_active_memberships();
        ?>
		<div class="ump-mm-search-section">
			<h2><?php esc_html_e('Căutare Utilizatori după Membership', 'ump-membership-manager'); ?></h2>
			
			<div class="ump-mm-search-form">
				<label for="ump-mm-search-membership">
					<?php esc_html_e('Selectează Membership:', 'ump-membership-manager'); ?>
				</label>
				<select id="ump-mm-search-membership" class="ump-mm-select">
					<option value=""><?php esc_html_e('-- Selectează --', 'ump-membership-manager'); ?></option>
					<?php foreach ($memberships as $id => $membership) : ?>
						<option value="<?php echo esc_attr($id); ?>">
							<?php echo esc_html($membership['label'] . ' (' . $membership['name'] . ')'); ?>
						</option>
					<?php endforeach; ?>
				</select>
				
				<button type="button" id="ump-mm-search-btn" class="button button-primary">
					<?php esc_html_e('Caută Utilizatori', 'ump-membership-manager'); ?>
				</button>
			</div>
			
			<div id="ump-mm-users-results" class="ump-mm-users-results" style="display: none;">
				<h3><?php esc_html_e('Rezultate:', 'ump-membership-manager'); ?></h3>
				
				<div class="ump-mm-bulk-actions">
					<label for="ump-mm-add-membership">
						<?php esc_html_e('Adaugă Membership:', 'ump-membership-manager'); ?>
					</label>
					<select id="ump-mm-add-membership" class="ump-mm-select">
						<option value=""><?php esc_html_e('-- Selectează --', 'ump-membership-manager'); ?></option>
						<?php foreach ($memberships as $id => $membership) : ?>
							<option value="<?php echo esc_attr($id); ?>">
								<?php echo esc_html($membership['label'] . ' (' . $membership['name'] . ')'); ?>
							</option>
						<?php endforeach; ?>
					</select>
					
					<button type="button" id="ump-mm-add-membership-btn" class="button button-primary">
						<?php esc_html_e('Adaugă la Utilizatorii Selectați', 'ump-membership-manager'); ?>
					</button>
				</div>
				
				<div class="ump-mm-users-list-wrapper">
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th class="check-column">
									<input type="checkbox" id="ump-mm-select-all">
								</th>
								<th><?php esc_html_e('ID', 'ump-membership-manager'); ?></th>
								<th><?php esc_html_e('Username', 'ump-membership-manager'); ?></th>
								<th><?php esc_html_e('Email', 'ump-membership-manager'); ?></th>
								<th><?php esc_html_e('Nume', 'ump-membership-manager'); ?></th>
							</tr>
						</thead>
						<tbody id="ump-mm-users-list">
							<!-- Users will be loaded here via AJAX -->
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
    }

    /**
     * Render auto rules tab
     */
    private function render_auto_rules_tab()
    {
        $auto_rules = get_option('ump_mm_auto_rules', array());
        $memberships = UMP_MM_Helper::get_active_memberships();
        ?>
		<div class="ump-mm-auto-rules-section">
			<h2><?php esc_html_e('Reguli Automate de Atribuire', 'ump-membership-manager'); ?></h2>
			<p class="description">
				<?php esc_html_e('Configurează reguli care adaugă automat un membership când un utilizator primește un anumit membership activ.', 'ump-membership-manager'); ?>
			</p>
			
			<div class="ump-mm-add-rule-form">
				<h3><?php esc_html_e('Adaugă Regulă Nouă', 'ump-membership-manager'); ?></h3>
				
				<div class="ump-mm-rule-field">
					<label for="ump-mm-rule-trigger">
						<?php esc_html_e('Când un utilizator primește membership:', 'ump-membership-manager'); ?>
					</label>
					<select id="ump-mm-rule-trigger" class="ump-mm-select">
						<option value=""><?php esc_html_e('-- Selectează --', 'ump-membership-manager'); ?></option>
						<?php foreach ($memberships as $id => $membership) : ?>
							<option value="<?php echo esc_attr($id); ?>">
								<?php echo esc_html($membership['label'] . ' (' . $membership['name'] . ')'); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				
				<div class="ump-mm-rule-field">
					<label for="ump-mm-rule-target">
						<?php esc_html_e('Adaugă automat membership:', 'ump-membership-manager'); ?>
					</label>
					<select id="ump-mm-rule-target" class="ump-mm-select">
						<option value=""><?php esc_html_e('-- Selectează --', 'ump-membership-manager'); ?></option>
						<?php foreach ($memberships as $id => $membership) : ?>
							<option value="<?php echo esc_attr($id); ?>">
								<?php echo esc_html($membership['label'] . ' (' . $membership['name'] . ')'); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				
				<button type="button" id="ump-mm-save-rule-btn" class="button button-primary">
					<?php esc_html_e('Salvează Regula', 'ump-membership-manager'); ?>
				</button>
			</div>
			
			<div class="ump-mm-existing-rules">
				<h3><?php esc_html_e('Reguli Existente', 'ump-membership-manager'); ?></h3>
				
				<?php if (empty($auto_rules)) : ?>
					<p><?php esc_html_e('Nu există reguli configurate.', 'ump-membership-manager'); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e('Membership Trigger', 'ump-membership-manager'); ?></th>
								<th><?php esc_html_e('Membership Adăugat', 'ump-membership-manager'); ?></th>
								<th><?php esc_html_e('Acțiuni', 'ump-membership-manager'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($auto_rules as $rule_id => $rule) : ?>
								<?php
                                $trigger_label = isset($memberships[ $rule['trigger'] ]) ? $memberships[ $rule['trigger'] ]['label'] : 'N/A';
							    $target_label = isset($memberships[ $rule['target'] ]) ? $memberships[ $rule['target'] ]['label'] : 'N/A';
							    ?>
								<tr>
									<td><?php echo esc_html($trigger_label); ?></td>
									<td><?php echo esc_html($target_label); ?></td>
									<td>
										<button type="button" class="button button-small ump-mm-delete-rule" data-rule-id="<?php echo esc_attr($rule_id); ?>">
											<?php esc_html_e('Șterge', 'ump-membership-manager'); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
    }

    /**
     * AJAX: Search users
     */
    public function ajax_search_users()
    {
        check_ajax_referer('ump_mm_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array( 'message' => __('Nu ai permisiuni suficiente.', 'ump-membership-manager') ));
        }

        $membership_id = isset($_POST['membership_id']) ? intval($_POST['membership_id']) : 0;

        if (! $membership_id) {
            wp_send_json_error(array( 'message' => __('Membership ID invalid.', 'ump-membership-manager') ));
        }

        $user_ids = UMP_MM_Helper::get_users_with_membership($membership_id);

        if (empty($user_ids)) {
            wp_send_json_success(array(
                'users' => array(),
                'count' => 0,
            ));
        }

        $users_data = array();
        foreach ($user_ids as $user_id) {
            $user = get_userdata($user_id);
            if ($user) {
                $users_data[] = array(
                    'id'       => $user_id,
                    'username' => $user->user_login,
                    'email'    => $user->user_email,
                    'name'     => trim($user->first_name . ' ' . $user->last_name),
                );
            }
        }

        wp_send_json_success(array(
            'users' => $users_data,
            'count' => count($users_data),
        ));
    }

    /**
     * AJAX: Add membership in bulk
     */
    public function ajax_add_membership_bulk()
    {
        check_ajax_referer('ump_mm_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array( 'message' => __('Nu ai permisiuni suficiente.', 'ump-membership-manager') ));
        }

        $user_ids = isset($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : array();
        $membership_id = isset($_POST['membership_id']) ? intval($_POST['membership_id']) : 0;

        if (empty($user_ids)) {
            wp_send_json_error(array( 'message' => __('Nu ai selectat utilizatori.', 'ump-membership-manager') ));
        }

        if (! $membership_id) {
            wp_send_json_error(array( 'message' => __('Membership ID invalid.', 'ump-membership-manager') ));
        }

        $results = array(
            'success' => 0,
            'errors'  => 0,
            'messages' => array(),
        );

        foreach ($user_ids as $user_id) {
            // Skip if user_id is invalid
            if (! $user_id || $user_id <= 0) {
                $results['errors']++;
                $results['messages'][] = sprintf(
                    __('User ID invalid: %s', 'ump-membership-manager'),
                    $user_id
                );
                continue;
            }

            $result = UMP_MM_Helper::add_membership_to_user($user_id, $membership_id);

            if (is_wp_error($result)) {
                $results['errors']++;
                $user = get_userdata($user_id);
                $error_message = $result->get_error_message();
                $error_code = $result->get_error_code();
                
                $results['messages'][] = sprintf(
                    __('User %s (ID: %d): %s [Code: %s]', 'ump-membership-manager'),
                    $user ? $user->user_login : 'N/A',
                    $user_id,
                    $error_message,
                    $error_code
                );
                
                // Log for debugging
                error_log(sprintf(
                    'UMP MM: Failed to add membership %d to user %d: %s (Code: %s)',
                    $membership_id,
                    $user_id,
                    $error_message,
                    $error_code
                ));
            } else {
                $results['success']++;
            }
        }

        wp_send_json_success($results);
    }

    /**
     * AJAX: Save auto rule
     */
    public function ajax_save_auto_rule()
    {
        check_ajax_referer('ump_mm_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array( 'message' => __('Nu ai permisiuni suficiente.', 'ump-membership-manager') ));
        }

        $trigger = isset($_POST['trigger']) ? intval($_POST['trigger']) : 0;
        $target = isset($_POST['target']) ? intval($_POST['target']) : 0;

        if (! $trigger || ! $target) {
            wp_send_json_error(array( 'message' => __('Parametri invalizi.', 'ump-membership-manager') ));
        }

        if ($trigger === $target) {
            wp_send_json_error(array( 'message' => __('Trigger-ul și target-ul nu pot fi același membership.', 'ump-membership-manager') ));
        }

        // Check if both are active
        if (! UMP_MM_Helper::is_membership_active($trigger) || ! UMP_MM_Helper::is_membership_active($target)) {
            wp_send_json_error(array( 'message' => __('Ambele memberships trebuie să fie active.', 'ump-membership-manager') ));
        }

        $auto_rules = get_option('ump_mm_auto_rules', array());

        // Check if rule already exists
        foreach ($auto_rules as $rule) {
            if ($rule['trigger'] == $trigger && $rule['target'] == $target) {
                wp_send_json_error(array( 'message' => __('Această regulă există deja.', 'ump-membership-manager') ));
            }
        }

        // Add new rule
        $rule_id = time() . '_' . wp_rand(1000, 9999);
        $auto_rules[ $rule_id ] = array(
            'trigger' => $trigger,
            'target'  => $target,
        );

        update_option('ump_mm_auto_rules', $auto_rules);

        wp_send_json_success(array( 'message' => __('Regula a fost salvată cu succes.', 'ump-membership-manager') ));
    }

    /**
     * AJAX: Delete auto rule
     */
    public function ajax_delete_auto_rule()
    {
        check_ajax_referer('ump_mm_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array( 'message' => __('Nu ai permisiuni suficiente.', 'ump-membership-manager') ));
        }

        $rule_id = isset($_POST['rule_id']) ? sanitize_text_field($_POST['rule_id']) : '';

        if (! $rule_id) {
            wp_send_json_error(array( 'message' => __('ID regulă invalid.', 'ump-membership-manager') ));
        }

        $auto_rules = get_option('ump_mm_auto_rules', array());

        if (isset($auto_rules[ $rule_id ])) {
            unset($auto_rules[ $rule_id ]);
            update_option('ump_mm_auto_rules', $auto_rules);
            wp_send_json_success(array( 'message' => __('Regula a fost ștearsă.', 'ump-membership-manager') ));
        } else {
            wp_send_json_error(array( 'message' => __('Regula nu a fost găsită.', 'ump-membership-manager') ));
        }
    }
}
