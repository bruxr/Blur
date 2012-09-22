<?php
/**
 * Blur
 *
 * Twitter
 * Allows users to login using their twitter account.
 *
 * @package Blur
 * @version 1.0
 * @author brux <brux.romuar@gmail.com>
 */
class Blur_Twitter extends Blur_Network
{

	/**
	 * The Name. It's the name.
	 *
	 * @var string
	 */
	public $name = 'Twitter';

	/**
	 * Performs setup. Duh.
	 *
	 * @param Blur $hookup the mothership instance
	 * @return void
	 */
	public function setup($hookup)
	{

		// Register our Settings
		$this->add_setting('key', 'Consumer Key');
		$this->add_setting('secret', 'Secret');

		// And our actions & filters
		add_action('init', array($this, 'process_login'));
		add_filter('get_avatar', array($this, 'avatar'), 11, 5);

	}

	/**
	 * Prints a sign in with twitter button.
	 *
	 * @param array $atts shortcode attributes
	 * @return void
	 */
	public function shortcode($atts)
	{

		$key = $this->get_setting('key');

?>

	<a href="<?php bloginfo('url'); ?>?blur=twitter_login_1"><img src="<?php echo plugins_url('login-button.png', __FILE__); ?>" alt="Sign in with Twitter"></a>

<?php

	}

	public function process_login()
	{

		if ( ! isset($_GET['blur']) ) return;
		$blur_action = trim($_GET['blur']);

		$key = $this->get_setting('key');
		$secret = $this->get_setting('secret');

		// Auth Step 1
		if ( $blur_action == 'twitter_login_1' )
		{

			$callback = home_url() . '?blur=twitter_login_2';

			// Load up TwitterOAuth Lib and request an oauth token
			require_once BLUR_DIR . '/networks/twitter/twitteroauth/twitteroauth.php';
			$tw_oauth = new TwitterOAuth($key, $secret);
			$request_token = $tw_oauth->getRequestToken($callback);

			// Store the token and secret for later use.
			setcookie('blur_twitteroauth', $request_token['oauth_token'] . ',' . $request_token['oauth_token_secret']);

			// Then redirect the user to twitter's auth page
			if ( $tw_oauth->http_code == 200 )
			{
				$auth_url = $tw_oauth->getAuthorizeURL($request_token['oauth_token']);
				wp_redirect($auth_url);
				exit;
			}
			// Or if we encounter an error, just fail silently.
			else
			{
				wp_redirect(home_url());
				exit;
			}

		}
		// Auth Step 2
		elseif ( $blur_action == 'twitter_login_2' )
		{

			// Gather data
			$oauth_data = isset($_COOKIE['blur_twitteroauth']) ? $_COOKIE['blur_twitteroauth'] : '';
			$oauth_token = isset($_GET['oauth_token']) ? $_GET['oauth_token'] : '';

			// Make sure we have our stored oauth data and a valid oauth token
			if ( $oauth_data && $oauth_token )
			{

				// Remove the cookie
				setcookie('blur_twitteroauth', '', time() - 3600);

				$oauth_data = explode(',', $oauth_data);

				// Make sure our cookie oauth data is composed of 2 parts
				if ( count($oauth_data) != 2 )
				{
					wp_redirect(home_url());
					exit;
				}

				// Make sure we have a valid oauth token
				if ( $oauth_data[0] != $oauth_token )
				{
					wp_redirect(home_url());
					exit;
				}

				$oauth_verifier = isset($_GET['oauth_verifier']) ? $_GET['oauth_verifier'] : '';

				// Initialize the OAuth library again and request an access token
				require_once BLUR_DIR . '/networks/twitter/twitteroauth/twitteroauth.php';
				$tw_oauth = new TwitterOAuth($key, $secret, $oauth_data[0], $oauth_data[1]);
				$access_token = $tw_oauth->getAccessToken($oauth_verifier);

				// If we have a valid response, do the login
				if ( $tw_oauth->http_code == 200 )
				{

					$response = $tw_oauth->get('account/verify_credentials');
					
					// Fail silently if we encounter a failed response
					if ( $tw_oauth->http_code != 200 )
					{
						wp_redirect(home_url());
						exit;
					}

					// Try to find a user with the specific twitter ID
					$twitter_id = $response->id;
					$user = $this->find_user($twitter_id);

					// Found user, just login 
					if ( $user )
					{

						wp_set_auth_cookie($user->ID, true, false);
						do_action('wp_login');
						$this->done_login();

					}
					// Otherwise, we need to display a form that will ask for the user's email
					else
					{

						$submit_url = home_url() . '?blur=twitter_login_3';
						$nonce = wp_create_nonce('blur_twitter_ask_email');

						$form = <<<EMAIL_FORM
						<h3>Just one more Step!</h3>
						<p>We need to ask for your email address to complete the signup.</p>
						<form action="$submit_url" method="post">
							<p><label>Email Address: 
								<input type="email" name="email" size="40" maxlength="100"></label></p>
							<input type="hidden" name="screen_name" value="$response->screen_name">
							<input type="hidden" name="display_name" value="$response->name">
							<input type="hidden" name="twitter_user_id" value="$twitter_id">
							<input type="hidden" name="avatar" value="$response->profile_image_url">
							<input type="hidden" name="_wpnonce" value="$nonce">
							<p><input type="submit" name="submit" value="Continue"></p>
						</form>
EMAIL_FORM;
						$form = apply_filters('blur_twitter_ask_email_form', $form, '', $response->screen_name, $response->name, $twitter_id, $response->profile_image_url, $nonce);
						wp_die($form);
						exit;

					}

				}
				else
				{
					wp_redirect(home_url());
					exit;
				}

			}
			else
			{
				wp_redirect(home_url());
				exit;
			}

		}
		// Auth Step 3
		elseif ( $blur_action == 'twitter_login_3' )
		{

			$nonce = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '';
			$email = isset($_POST['_wpnonce']) ? trim($_POST['email']) : '';
			$screen_name = isset($_POST['screen_name']) ? trim($_POST['screen_name']) : '';
			$display_name = isset($_POST['display_name']) ? trim($_POST['display_name']) : '';
			$twitter_id = isset($_POST['twitter_user_id']) ? intval($_POST['twitter_user_id']) : 0;
			$avatar = isset($_POST['avatar']) ? trim($_POST['avatar']) : '';
			$error = '';

			// Validate the nonce and make sure our credentials are not empty
			if ( ! wp_verify_nonce($nonce, 'blur_twitter_ask_email') || empty($screen_name) || empty($display_name) || $twitter_id == 0 || empty($avatar) )
			{
				wp_redirect(home_url());
				exit;
			}

			// Validate the email address
			if ( ! filter_var($email, FILTER_VALIDATE_EMAIL) )
			{
				$error = 'Invalid Email Address.';
			}
			// Check if the email exists
			elseif ( email_exists($email) )
			{
				$error = 'That email is already in use by another user.';
			}

			// If we have an error, print the form again
			if ( $error )
			{

				$submit_url = home_url() . '?blur=twitter_login_3';
				$nonce = wp_create_nonce('blur_twitter_ask_email');

				$form = <<<EMAIL_FORM
					<h3>Just one more Step!</h3>
					<p style="color: red"><strong>$error</strong></p>
					<form action="$submit_url" method="post">
						<p><label>Email Address: 
							<input type="email" name="email" value="$email" size="40" maxlength="100"></label></p>
						<input type="hidden" name="screen_name" value="$screen_name">
						<input type="hidden" name="display_name" value="$display_name">
						<input type="hidden" name="twitter_user_id" value="$twitter_id">
						<input type="hidden" name="avatar" value="$avatar">
						<input type="hidden" name="_wpnonce" value="$nonce">
						<p><input type="submit" name="submit" value="Continue"></p>
					</form>
EMAIL_FORM;
				$form = apply_filters('blur_twitter_ask_email_form', $form, $email, $screen_name, $display_name, $twitter_id, $avatar, $nonce);
				wp_die($form);
			}
			// We proceed if we have no errors
			else
			{

				global $blur;

				// Create the user
				$username = $blur->generate_username($screen_name);
				$wp_user_id = $this->create_user($twitter_id, array(
					'user_login'	=> $username,
					'user_url'		=> "http://twitter.com/$screen_name",
					'user_email'	=> $email,
					'display_name'	=> $display_name
				));

				// If we have a WP_Error, fail silently
				if ( is_wp_error($wp_user_id) ) wp_redirect(get_bloginfo('url'));

				// Store the avatar as well so we won't be accessing the API later on
				add_user_meta($wp_user_id, 'blur_twitter_avatar', $avatar);

				// Login
				wp_set_auth_cookie($wp_user_id, true, false);
				do_action('wp_login', $username);

				$this->done_login();

			}

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

		$twitter_id = $this->get_user_network_id($user_id);

		if ( ! is_wp_error($twitter_id) )
		{
			$avatar = get_user_meta($user_id, 'blur_twitter_avatar', true);
			return '<img src="'. $avatar .'" alt="'. $twitter_id .'" class="avatar avatar-'. $size .' photo" width="'. $size .'" height="'. $size .'">';
		}
		else
		{
			return $avatar;
		}

	}

}