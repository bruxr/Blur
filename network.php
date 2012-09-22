<?php
/**
 * Blur
 *
 * Social Network Base Class
 * All social network classes extend from this class.
 *
 * @package Blur
 * @version 1.0
 * @author brux <brux.romuar@gmail.com>
 */
class Blur_Network
{

	/**
	 * The Social Network Name. All inheriting classes should
	 * override this property.
	 *
	 * @var string
	 */
	public $name = '';

	/**
	 * The Social Network slug. Usually this is just a lowercased
	 * $this->name. Take note that this is automatically set by 
	 * the main class Blur.
	 *
	 * @var string
	 */
	public $slug = '';

	/**
	 * Private constructor prevents external instantiation.
	 *
	 */
	public function __construct()
	{

		$name = strtolower($this->name);
		$this->slug = preg_replace('/[^a-z]/', '', $name);

	}

	/**
	 * Convenience method for Blur::add_setting().
	 *
	 * @param string $key setting key
	 * @param string $label setting label
	 * @return void
	 */
	protected function add_setting($key, $label)
	{

		$this->blur->add_setting($this->slug, $key, $label);

	}

	/**
	 * Convenience method for Blur::remove_setting().
	 *
	 * @param string $key setting key
	 * @return void
	 */
	protected function remove_setting($key)
	{

		$this->blur->remove_setting($this->slug, $key);

	}

	/**
	 * Convenience method for Blur::get_setting().
	 *
	 * @param string $key setting key
	 * @return mixed
	 */
	protected function get_setting($key)
	{

		return $this->blur->get_setting($this->slug, $key);

	}

	/**
	 * Convenience function for the main class'
	 * find_user() method.
	 *
	 * @param int $user_id social network specific ID
	 * @param string $email email address
	 * @return object
	 */
	protected function find_user($user_id, $email = null)
	{

		return $this->blur->find_user($this->slug, $user_id, $email);

	}

	/**
	 * Just a convenience function for the main class'
	 * create_user() method.
	 *
	 * @param int $user_id social network ID
	 * @param array $user user info
	 * @return int
	 */
	protected function create_user($user_id, $user)
	{

		return $this->blur->create_user($this->slug, $user_id, $user);

	}

	/**
	 * Convenience function for the main class' get_user_network_id()
	 * method.
	 *
	 * @param int $user_id WP user ID
	 * @return int|bool
	 */
	protected function get_user_network_id($user_id)
	{

		return $this->blur->get_user_network_id($this->slug, $user_id);

	}

	/**
	 * Marks the finish of the login process. Redirects the user to
	 * the referring URI or to the current URI instead.
	 *
	 * @return void
	 */
	protected function done_login()
	{

		do_action('blur_did_login');

		if ( wp_get_referer() )
		{
			wp_redirect(wp_get_referer());
		}
		else
		{
			wp_redirect($_SERVER['REQUEST_URI']);
		}
		exit;

	}

	/**
	 * Performs social network specific setup.
	 *
	 * @param Blur $blur the Blur instance
	 * @return void
	 */
	public function setup($blur)
	{

	}

	/**
	 * Inheriting classes can override this method so they can
	 * output a login button that pertains to their social network.
	 *
	 * @param array $attributes an array of attributes in the shortcode
	 * @return void
	 */
	public function shortcode($attributes)
	{

	}

}