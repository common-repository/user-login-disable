<?php

declare(strict_types=1);

namespace Chexwarrior;

use \WP_User;
use \WP_Error;
use \WP_Session_Tokens;

class UserLoginDisablePlugin
{
	/**
	 * This class is a singleton
	 */
	static ?UserLoginDisablePlugin $instance = null;

	// The custom capability created by this plugin
	const DISABLE_USERS_CAP = 'disable_users';

	private function __construct()
	{
		// Setup hooks
		// Handles showing or hiding the field for enabling/disabling user login
		add_action('show_user_profile', [$this, 'addDisabledFormField']);
		add_action('edit_user_profile', [$this, 'addDisabledFormField']);
		add_action('personal_options_update', [$this, 'updateDisabledFormField']);
		add_action('edit_user_profile_update', [$this, 'updateDisabledFormField']);

		// Handles checking if a user is disabled when they're authenicated with WP
		add_filter('wp_authenticate_user', [$this, 'createErrorIfUserDisabled'], 10, 2);

		// Checks if a user is disabled when using an app password with API
		add_action('wp_authenticate_application_password_errors', [$this, 'checkIfUserDisabledForAPI'], 10, 4);

		// Add User Disabled column to admin user's list
		add_filter('manage_users_columns', [$this, 'addUserDisabledColumn']);
		add_filter('manage_users_custom_column', [$this, 'showUserDisabledColumn'], 10, 3);

		// Update Admin Notices for plugin functionality
		add_action('admin_notices', [$this, 'bulkActionNotifications']);

		// Handle Bulk Actions for enabling/disabling users
		add_filter('bulk_actions-users', [$this, 'registerBulkActions']);
		add_filter('handle_bulk_actions-users', [$this, 'processBulkActions'], 10, 3);
	}

	public static function getInstance(): UserLoginDisablePlugin
	{
		if (!self::$instance) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function addDisabledFormField(WP_User $user): void
	{
		if (!in_array('administrator', $user->roles) && current_user_can(self::DISABLE_USERS_CAP)) : ?>
			<h3>Disable User Login</h3>
			<table class="form-table">
				<tr>
					<th>
						<label for="disabled">Is Disabled</label>
					</th>
					<td>
						<input type="checkbox" id="disabled" name="disabled" <?php
																				echo $this->isUserDisabled($user->ID) ? 'checked' : '' ?> title="If checked user will not be able to login" required>
						<p class="description">
							If checked user will not be able to login.
						</p>
					</td>
				</tr>
			</table>
		<?php endif;
	}

	// Update actual user disabled metadata and run associated functionality
	public function updateDisabledMetadata(bool $is_disabled, int|string $user_id): int|bool
	{
		// Administrators cannot be disabled
		$user = get_userdata($user_id);
		if (in_array('administrator', $user->roles)) {
			return false;
		}

		// Logout target user when they are disabled
		if ($is_disabled) {
			$sessions = WP_Session_Tokens::get_instance($user_id);
			$sessions->destroy_all();
		}

		return update_user_meta(
			$user_id,
			'disabled',
			$is_disabled
		);
	}

	public function disableUser(int|string $user_id): bool
	{
		return $this->updateDisabledMetadata(true, $user_id) !== false;
	}

	public function enableUser(int|string $user_id): bool
	{
		return $this->updateDisabledMetadata(false, $user_id) !== false;
	}

	public function enableMultipleUsers(array $user_ids): int
	{
		$count = 0;
		array_walk($user_ids, function ($id) use (&$count) {
			if ($this->enableUser($id)) $count += 1;
		});

		return $count;
	}

	public function disableMultipleUsers(array $user_ids): int
	{
		$count = 0;
		array_walk($user_ids, function ($id) use (&$count) {
			if ($this->disableUser($id)) $count += 1;
		});

		return $count;
	}

	/**
	 * This method servers as a wrapper for get_user_meta
	 *
	 * A user who is NOT disabled (enabled) or doesn't
	 * have a metadata value in the db (implying they are also
	 * enabled) will return an empty string.  A user who is
	 * disabled will return a "1".
	 *
	 * @param int|string $user_id
	 * @return bool True if user is disable, false if enabled
	 */
	public function isUserDisabled(int|string $user_id): bool
	{
		$isDisabled = get_user_meta($user_id, 'disabled', true);

		return $isDisabled === "1" ? true : false;
	}

	// Ensure meta data is updated and disabled users are logged out
	public function updateDisabledFormField(int $user_id): bool
	{
		if (!current_user_can('edit_user', $user_id) || !current_user_can(self::DISABLE_USERS_CAP)) {
			return false;
		}

		if ($_POST['disabled'] === 'on') {
			return $this->disableUser($user_id);
		}

		return $this->enableUser($user_id);
	}

	// Ensure we check disabled meta when a user logs in
	public function createErrorIfUserDisabled(WP_User|WP_Error $user, string $password): WP_User|WP_Error
	{
		if ($this->isUserDisabled($user->ID)) {
			return new WP_Error('user_disabled', 'User is disabled', $user->ID);
		}

		return $user;
	}

	public function checkIfUserDisabledForAPI(WP_Error $error, WP_User $user, array $item, string $password): void
	{
		$disabled_user_error = $this->createErrorIfUserDisabled($user, $password);

		if ($disabled_user_error instanceof WP_Error) {
			$error->add(
				$disabled_user_error->get_error_code(),
				$disabled_user_error->get_error_message()
			);
		}
	}

	public function addUserDisabledColumn(array $columns): array
	{
		$check_column = $columns['cb'];
		unset($columns['cb']);
		return array_merge(
			[
				'cb' => $check_column,
				'user_disabled' => 'User Disabled'
			],
			$columns
		);
	}

	public function showUserDisabledColumn(string $value, string $column_name, int $user_id): string
	{
		if ($column_name === 'user_disabled') {
			$user_data = get_userdata($user_id);
			return $user_data->get('disabled') === "1"
				? '<strong class="file-error">Disabled</strong>' : '';
		}

		return $value;
	}

	public function registerBulkActions(array $bulk_actions): array
	{
		if (current_user_can(self::DISABLE_USERS_CAP)) {
			$bulk_actions['disable_user'] = __('Disable User', 'disable_user');
			$bulk_actions['enable_user'] = __('Enable User', 'enable_user');
		}

		return $bulk_actions;
	}

	public function processBulkActions(string $redirect_url, string $action_name, array $user_ids): string
	{
		if (current_user_can(self::DISABLE_USERS_CAP)) {
			if ($action_name === 'disable_user') {
				$returnUrl = remove_query_arg('enable_user', $redirect_url);
				$count = $this->disableMultipleUsers($user_ids);

				return add_query_arg('disable_user', $count, $returnUrl);;
			}

			if ($action_name === 'enable_user') {
				$returnUrl = remove_query_arg('disable_user', $redirect_url);
				$count = $this->enableMultipleUsers($user_ids);

				return add_query_arg('enable_user', $count, $returnUrl);
			}
		}

		return $redirect_url;
	}

	public function bulkActionNotifications(): void
	{
		if (!empty($_REQUEST['disable_user'])) {
			$count = esc_html(intval($_REQUEST['disable_user']));
		?>
			<div class="notice notice-success is-dismissible">
				<p>Disabled <?php echo $count ?> user(s).</p>
			</div>
		<?php
		}

		if (!empty($_REQUEST['enable_user'])) {
			$count = esc_html(intval($_REQUEST['enable_user']));
		?>
			<div class="notice notice-success is-dismissible">
				<p>Enabled <?php echo $count ?> user(s).</p>
			</div>
		<?php
		}
	}

	public static function uninstallPlugin(): void
	{
		// Remove disabled user metadata from all users
		delete_metadata('user', -1, 'disabled', null, true);

		// Remove disable users capability
		$role = get_role('administrator');
		$role->remove_cap(self::DISABLE_USERS_CAP);
	}

	public static function activatePlugin(): void
	{
		// Give capability for disabling users to admins only
		$role = get_role('administrator');
		$role->add_cap(self::DISABLE_USERS_CAP, true);
	}
}
