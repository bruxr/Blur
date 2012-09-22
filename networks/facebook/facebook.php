<?php
/**
 * Blur
 *
 * Facebook
 * Allows users to login using their facebook account.
 *
 * @package Blur
 * @version 1.0
 * @author brux <brux.romuar@gmail.com>
 */
class Blur_Facebook extends Blur_Network
{

	/**
	 * The name.
	 *
	 * @var string
	 */
	public $name = 'Facebook';

	/**
	 * Exchanges a code returned by the Facebook OAuth Dialog with an
	 * Access Token. Returns FALSE if there was an error while trying to
	 * do the exchange.
	 *
	 * @param string $code code returned by facebook
	 * @return string|bool
	 */
	private function exchange_code($code)
	{

		global $blur;
		$app_id = $blur->get_setting('facebook', 'app_id');
		$secret = $blur->get_setting('facebook', 'secret');

		$access_token_url = sprintf('https://graph.facebook.com/oauth/access_token?client_id=%s&redirect_uri=%s&client_secret=%s&code=%s', $app_id, urlencode(get_bloginfo('url') .'?blur=facebook_login'), $secret, $code);
		$response_str = wp_remote_fopen($access_token_url);
		$response = array();
		parse_str($response_str, $response);

		if ( isset($response['access_token']) )
		{
			$access_token = $response['access_token'];
			return $access_token;
		}	
		else
		{
			return false;
		}

	}

	/**
	 * Returns details of the currently logged-in user in Facebook.
	 *
	 * @param string $access_token the access token
	 * @return array
	 */
	private function get_user_info($access_token)
	{

		$response_str = wp_remote_fopen("https://graph.facebook.com/me?access_token=$access_token");
		$response = json_decode($response_str);
		return $response;

	}
	
	/**
	 * Performs setup. Duh.
	 *
	 * @param Blur $hookup the mothership instance
	 * @return void
	 */
	public function setup($hookup)
	{

		// Register our Settings
		$hookup->add_setting('facebook', 'app_id', 'App ID');
		$hookup->add_setting('facebook', 'secret', 'Secret');

		// Then our hooks
		add_action('init', array($this, 'process_login'));
		add_filter('get_avatar', array($this, 'avatar'), 11, 5);

	}

	/**
	 * Prints a login with facebook button.
	 *
	 * @param array $atts shortcode attributes
	 * @return void
	 */
	public function shortcode($atts)
	{

		global $blur;

		$app_id = $blur->get_setting('facebook', 'app_id');

?>

	<a href="https://www.facebook.com/dialog/oauth?client_id=<?php echo $app_id; ?>&amp;redirect_uri=<?php echo urlencode(get_bloginfo('url')); ?>?blur=facebook_login&amp;scope=email"><img src="<?php echo plugins_url('login-button.gif', __FILE__); ?>" alt="Login with Facebook"></a>

<?php

	}

	/**
	 * Processes login attempts using facebook.
	 *
	 * @return void
	 */
	public function process_login()
	{

		if ( isset($_GET['blur']) && $_GET['blur'] == 'facebook_login' && ! empty($_GET['code']) )
		{

			global $blur;

			// Get the user's details
			$code = $_GET['code'];
			$access_token = $this->exchange_code($code);
			$user = $this->get_user_info($access_token);

			// Find the user with the same facebook id or email
			$wp_user = $this->find_user($user->id, $user->email);

			// No users? Create one.
			if ( ! $wp_user )
			{

				$username = $blur->generate_username($user->username);
				$wp_user_id = $this->create_user($user->id, array(
					'user_login'	=> $username,
					'user_email'	=> $user->email,
					'user_url'		=> $user->link,
					'first_name'	=> $user->first_name,
					'last_name'		=> $user->last_name,
					'display_name'	=> "$user->first_name $user->last_name"
				));

				if ( is_wp_error($wp_user_id) ) wp_redirect(get_bloginfo('url'));

				wp_set_auth_cookie($wp_user_id, true, false);
				do_action('wp_login', $username);

			}
			// We found the user
			else
			{

				wp_set_auth_cookie($wp_user->ID, true, false);
				do_action('wp_login', $wp_user->user_login);

			}

			$this->done_login();

		}

	}

	/**
	 * Uses the user's facebook primary photo is we found a facebook user ID
	 * set in the user's meta.
	 *
	 * @param string $avatar original avatar <img>
	 * @param int|string $id_or_email user ID or email address
	 * @param int $size avatar size
	 * @param string $default default avatar
	 * @param string $alt alt avatar
	 * @return string
	 */
	public function avatar($avatar, $id_or_email, $size, $default, $alt)
	{

		if ( is_string($id_or_email) )
		{
			$user = get_user_by_email($id_or_email);
			$user_id = $user->ID;
		}
		else
		{
			$user_id = $id_or_email;
		}

		$fb_id = $this->get_user_network_id($user_id);

		if ( is_int($fb_id) )
		{
			return '<img src="https://graph.facebook.com/'. $fb_id .'/picture" alt="'. $fb_id .'" class="avatar avatar-'. $size .' photo" width="'. $size .'" height="'. $size .'">';
		}
		else
		{
			return $avatar;
		}

	}

}