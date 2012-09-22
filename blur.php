<?php
/*
Plugin Name: Blur
Description: Blurs the line between your site and your user's social network by allowing them to login using their favorite social network's account.
Version: 1.0
Author: Brux
Author URI: mailto:brux.romuar@gmail.com
Copyright: 2012 Brux Romuar
*/

/**
 * Blur
 *
 * Main Plugin Class
 * Defines the Blur environment and provides an API to allow social-network
 * specific plugins to "blur" with the class.
 *
 * @package Blur
 * @version 1.0
 * @author brux <brux.romuar@gmail.com>
 */
final class Blur
{

	/**
	 * The absolute path to our plugin.
	 *
	 * @var string
	 */
	private $plugin_path = '';

	/**
	 * An array of social-network objects that plug into Blur.
	 *
	 * @var array
	 */
	private $networks = array();

	/**
	 * Private constructor for the singleton pattern.
	 *
	 */
	public function __construct()
	{
		
		// Determine the plugin path
		define('BLUR_DIR', dirname(__FILE__));
		define('BLUR_URL', plugins_url('', __FILE__));

		// Load the base network class
		require_once BLUR_DIR . '/network.php';

		// Load our social networks
		$this->load_networks();

		// Register our shortcode
		add_shortcode('blur', array($this, 'do_shortcode'));

		// Register actions
		add_action('admin_menu', array($this, 'add_admin_menus'));

	}

	/**
	 * Loads social-network specific files inside the "networks" folder and
	 * then instantiates their classes.
	 *
	 * @return void
	 */
	private function load_networks()
	{

		$networks_dir = BLUR_DIR . '/networks';
		$dir = opendir($networks_dir);

		// Loop through each folder inside the networks folder.
		while ( ($file = readdir($dir)) !== false )
		{

			$file_path = $networks_dir . "/$file";

			// If it is a directory then it is a social network.
			if ( is_dir($file_path) && $file != '.' && $file != '..' )
			{

				// Load the social network file
				$class_path = $file_path . "/$file.php";	
				$class = 'Blur_' . ucwords($file);
				require_once $class_path;

				// Instantiate the class
				$this->networks[$file] = new $class;
				$this->networks[$file]->blur = $this;
				$name = $this->networks[$file]->name;
				$this->networks[$file]->slug = preg_replace('/[^a-z]/', '', strtolower($name));
				$this->networks[$file]->setup($this);

				do_action("blur_load_$file", $class_path);

			}

		}

		closedir($dir);

	}

	/**
	 * Registers a setting so it would be present in the Blur settings page.
	 *
	 * @param string $slug social network slug
	 * @param string $key setting key
	 * @param string $label setting label
	 * @return void
	 */
	public function add_setting($slug, $key, $label)
	{

		$settings = get_option('blur_settings');

		if ( isset($settings[$slug][$key]) ) return;

		$settings[$slug][$key] = array(
			'label'	=> $label,
			'value'	=> ''
		);
		
		update_option('blur_settings', $settings);

	}

	/**
	 * Removes a setting.
	 *
	 * @param string $slug social network slug
	 * @param string $key setting key
	 * @return void
	 */
	public function remove_setting($slug, $key)
	{

		$settings = get_option('blur_settings');
		unset($settings[$slug][$key]);
		update_option('blur_settings', $settings);

	}

	/**
	 * Returns the value of a setting. Returns false if
	 * the setting does not exist.
	 *
 	 * @param string $slug social network slug
	 * @param string $key setting key
	 * @return mixed
	 */
	public function get_setting($slug, $key)
	{

		$settings = get_option('blur_settings');
		if ( isset($settings[$slug][$key]) )
		{
			return $settings[$slug][$key]['value'];
		}
		else
		{
			return false;
		}

	}

	/**
	 * Will generate a username from $base that isn't in use by other
	 * users.
	 *
	 * @param string $base username base
	 * @return string
	 */
	public function generate_username($base)
	{

		$username = preg_replace('[^a-zA-Z0-9]', '', $base);

		$i = 1;
		while ( username_exists($username) )
		{
			$username = $base . $i;
			$i++;
		}

		return $username;

	}

	/**
	 * Finds a user with a social network specific ID set or with
	 * a specific email. Returns FALSE if a user with that ID does not exist.
	 *
	 * @param string $network_slug social network slug
	 * @param int $user_id social network specific ID
	 * @param string $email email address
	 * @return object|bool
	 */
	public function find_user($network_slug, $user_id, $email = null)
	{

		global $wpdb;

		$meta_key = "blur_{$network_slug}_id";
		$user = $wpdb->get_row("SELECT * FROM {$wpdb->users} AS u JOIN {$wpdb->usermeta} AS m ON u.ID = m.user_id WHERE (m.meta_key = '$meta_key' AND m.meta_value = '$user_id') OR u.user_email = '$email' LIMIT 1");

		if ( $user )
		{
			return $user;
		}
		else
		{
			return false;
		}

	}

	/**
	 * Creates a user with a social network specific ID set. Returns
	 * the internal WP user ID on success.
	 *
	 * Take note that this doesn't check if the username or email
	 * is already in use. Make sure you use generate_username() first
	 * before invoking this method.
	 * 
	 * @param string $network_slug social network slug
	 * @param int $user_id social network specific ID
	 * @param array $user user data
	 * @return int
	 */
	public function create_user($network_slug, $user_id, $user)
	{

		$user['user_pass'] = uniqid();

		$wp_user_id = wp_insert_user($user);

		if ( $wp_user_id )
		{
			add_user_meta($wp_user_id, "blur_{$network_slug}_id", $user_id);
			return $wp_user_id;
		}
		else
		{
			return false;
		}

	}

	/**
	 * Returns the user's social network ID.
	 *
	 * @param string $network_slug social network slug
	 * @param int $user_id WP user ID
	 * @return int|bool
	 */
	public function get_user_network_id($network_slug, $user_id)
	{

		$meta_key = "blur_{$network_slug}_id";
		$meta_value = get_user_meta($user_id, $meta_key, true);

		return $meta_value;

	}

	/**
	 * Calls the appropriate social network to process a shortcode.
	 *
	 * Take note that the social network "slug" must be present
	 * in the shortcode for this to work (e.g [blur facebook] or 
	 * [blur twitter]) otherwise this would just print an empty string.
	 *
	 * Non-existent social networks would print an empty string.
	 *
	 * @param array $attributes shortcode attributes
	 * @return void
	 */
	public function do_shortcode($attributes)
	{

		$networks = array_keys($this->networks);
		$n = array_intersect($networks, $attributes);
		if ( count($n) > 0 )
		{
			$network = reset($n);
			$this->networks[$network]->shortcode($attributes);
		}
		else
		{
			echo '';
		}

	}

	/**
	 * Adds the necessary plugin admin menus.
	 *
	 * @return void
	 */
	public function add_admin_menus()
	{

		add_options_page('Blur Settings', 'Blur', 'manage_options', 'blur', array($this, 'settings_page'));

	}

	/**
	 * Creates the Blur Settings page.
	 *
	 * @return void
	 */
	public function settings_page()
	{

		$saved = false;
		$all_settings = get_option('blur_settings');

		// Process form
		if ( isset($_POST['submit']) )
		{

			check_admin_referer('blur_settings');

			// Loop through all registered settings and set their values
			foreach ( $all_settings as $network_key => $network )
			{
				foreach ( $network as $setting_key => $setting )
				{
					$field_key = "{$network_key}_{$setting_key}";
					$value = $_POST[$field_key];
					$value = apply_filters("hookup_setting_$field_key", $value);
					$all_settings[$network_key][$setting_key]['value'] = $value;
				}
			}
			
			update_option('blur_settings', $all_settings);

			$saved = true;

		}

?>
	
		<div class="wrap">

			<?php screen_icon(); ?>
			<h2>Blur Settings</h2>

			<?php if ( $saved ): ?>
			<div class="updated"> 
				<p><strong>Settings saved.</strong></p>
			</div>
			<?php endif; ?>

			<form action="options-general.php?page=blur" method="post">

			<?php
				foreach ( $this->networks as $network_key => $network ):

					if ( isset($all_settings[$network_key]) )
						$settings = $all_settings[$network_key];
			?>

				<h3><?php echo $network->name; ?></h3>
				<table class="form-table">
					<tbody>
						<?php
							foreach ( $settings as $setting_key => $setting ):
								$field_key = "{$network_key}_{$setting_key}";
						?>
						<tr>
							<th scope="row"><label for="<?php echo $field_key; ?>"><?php echo $setting['label']; ?>:</label></th>
							<td><input type="text" name="<?php echo $field_key; ?>" id="<?php echo $field_key; ?>" value="<?php echo $setting['value']; ?>" class="regular-text"></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

			<?php endforeach; ?>

				<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('blur_settings'); ?>">

				<p class="submit"><input type="submit" name="submit" value="Save Changes" class="button-primary"></p>

			</form>

		</div>

<?php

	}

}

$blur = new Blur;