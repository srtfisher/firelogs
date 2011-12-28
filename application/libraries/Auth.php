<?php
/**
 * A Reusable Auth System for users
 * Handling all the auth items and the user information.
 *
 * @since      1.0
 * @package    FireLog
**/
class Auth
{
	/**
	 * The current user row
	 * Default is NULL.
	 *
	 * @global object
	**/
	private static $user_row =        NULL;
	
	/**
	 * The user cookie name
	 *
	 * @global string
	**/
	private static $cookie_name =     'fl_';
	
	/**
	 * The CI Object
	 *
	 * @global object
     **/
     private static $CI =               NULL;
     
     /**
      * Table to get the users from
      *
      * @access     public
      * @global     string
     **/
     private static $tbl =              'users';
     
	/**
	 * The init function to setup everything
	 *
	 * @access public
	 * @return void
	**/
	public static function init()
	{
		self::$CI = get_instance();
		
		// Set the cookie name to something unique
		$explode = explode('.', $_SERVER['HTTP_HOST']);
		
		$hash = md5(
               $explode[count($explode)-2].'.'.$explode[count($explode)-1]
		);
		
		// Set the cookie name we're looking for
		self::$cookie_name = strtolower(ENVIRONMENT).'_'.$hash;
		
		// Setup the user session
		self::_setup_user();
	}
	
	/**
	 * Set the current user internally in the object
	 *
	 * @access    public
	 * @param     int
	 * @return    void
     **/
     public static function set_current_user($user)
     {
          self::$user_row = self::get_row($user);
     }
	
	/**
	 * Generate the cookie value for a user.
	 *
	 * The cookie data will be this:
	 * uid::hash-of-uid-and-then-the-sites-encryption-key
	 *
	 * Example:
	 * <code>
	 * 11::bncwhfib4wiyfbw4yibwiubfwfwfwfwefwef
	 * // "11" is the user ID
	 * // "bncwhfib4wiyfbw4yibwiubfwfwfwfwefwef" is the md5 hash of 
	 * 11.encryptionkey
	 * </code>
	 * 
	 * @access public
	 * @param int The user's ID
	 * @return string|bool
	**/
	public static function cookie_hash($uid)
	{
		return $uid.'::'.md5($uid. self::$CI->config->item('encryption_key'));
	}
	
	/**
	 * Setup the Current Session for the User
	 *
	 * Fires off two events: `user-not-logged-in` for when they're not logged
	 * in and `user-logged-in` for when they are.
	 *
	 * @access    private
	 * @return    void
     **/
     private static function _setup_user()
     {
          // Only if the user cookie exists
          if (! isset($_COOKIE[self::$cookie_name])) :
               do_action('user-not-logged-in');
               return;
          endif;
          
          $decode = self::$CI->encrypt->decode($_COOKIE[self::$cookie_name]);
          
          // Invalid data
          if (! is_string($decode) OR ! $decode) return;
          
          list($uid, $session_secret) = explode('::', $decode, 2);
          
          // It has an invalid hash - like checking the MD5 hash of a file
          if ($session_secret !== md5($uid.self::$CI->config->item('encryption_key'))) :
               log_message('debug', 'Removing current session because session hash is invalid. Forcing a refresh of the page');
               delete_cookie(self::$cookie_name);
               
               // Force redirect
               redirect(current_url());
               
               return;
          endif;
          
          // Setting the current row
          self::$user_row = self::get_row($uid);
          
          if (! is_array(self::$user_row) OR ! self::$user_row) :
               log_message('debug', 'Invalid user row - probably couldn\'t find it. Removing the session.');
               delete_cookie(self::$cookie_name);
               
               // Force redirect
               redirect(current_url());
               
               return;
          endif;
          
          
          // We're good, fire off an event
          do_action('user-logged-in');
     }
     
     /**
      * Get a User's Row
      *
      * We heavily cache this code so you can call on it many times.
      * And we're using MongoDB, so it doesn't even matter.
      *
      * @access     public
      * @param      int
      * @return     array
     **/
     public static function get_row($id = 0)
     {
          //$id = (int) $id;
          
          // Invalid data
          //if ($id < 1) return FALSE;
          
          $get = $this->mongo->get(self::$tbl, array('_id' => $id));
          
          // Nothing found :(
          if (! $get OR count($get) < 1) return FALSE;
          
          return $get;
     }
     
     /**
      * Setup the Session for a user
      *
      * @access     public
      * @param      int
     **/
     public static function setup_session($id)
     {
          $cookie = self::cookie_hash($id);
          
          $crypt = $this->encrypt->encode($cookie);
		set_cookie(array(
			'name'      =>	self::$cookie_name,
			'value'     =>	$crypt,
			'expire'    => apply_filter('cookie-expiration', 7889231),	// 3 Months
		));
     }
     
     /**
      * Check a pair of logins
      *
      * We accept both the username and their emails as the key
      * The password will be plaintext
      *
      * We still conform to a MD5 password in the DB.
      * If we find one, we will update it to a Hashed version.
      *
      * @access     public
      * @pararm     string {email/username}
      * @pararm     string plain-text password
      * @return     bool|object
     **/
     public static function check_login($key, $password)
     {
          if (valid_email($key))
               $is_email = true;
          else
               $is_email = false;
          
          // Find a User
          $get = $this->Mongo->get(array((($is_email) ? 'email' : 'slug') => $key));
          
          // No user found
          if (! $get OR count($get) < 1) return simple_error('No user found with that '.(($is_email) ? 'email' : 'username'), 'no-user-found');
          
          // First row
          $row = reset($get);
          
          // Validate the password
          if (self::isMD5($row['password'])) :
               // The password stored is a MD5 hash
               if (md5($password) == $row['password']) :
                    
                    // Update the MD5 password to a PHPPass hash
                    if (! defined('DISABLE_PHPPASS'))
                         $this->update('password', self::phpassDoHash($password), $row['_id']);
                    
                    return TRUE;
               endif;
          endif;
          
          // Now we're looking for a PHPPass hash
          $check = self::phpassHashCheck($password, $row['password']);
          
          if (! $check)
               return simple_error('Invalid password', 'invalid-password');
          else
               return TRUE;
     }
     
     /**
	 * See if a string is in the correct MD5 format
	 * Note: This only uses string length to determine this,
	 * not actually checking it.
	 *
	 * @return    bool
	**/
	function isMD5($string)
	{
		if ( strlen($string) >= 33 )
			return FALSE;
		
		return TRUE;
	}
	
	/**
	 * Check a string and an MD5 hash
	 *
	 * @return    bool
	 * @param     string $string The NON MD5 encoded string
	 * @param     string $hash The MD5 Encoded string
	**/
	public static function compareMD5($string, $hash)
	{
		if ( md5( $string ) == $hash )
			return TRUE;
		else
			return FALSE;
	}
	
	/**
	 * Check if a string is a valid password when compared to a PHPPass hash 
	 * 
	 * @param     string $string The NON Hashed String
	 * @param     string $hash The save Hash
	 * @return    bool
	**/
	public static function phpassHashCheck($string, $hash)
	{
		$check = Hash::CheckPassword($string, $hash);
		
		if (! $check)
			return FALSE;
		else
			return TRUE;
	}
	
	/**
	 * Hash a string with PHPPass
	 *
	 * @return    string The hashed version of $string.
	**/
	public static function phpassDoHash($string)
	{
		if (! class_exists('Hash') )
			return FALSE;
		
		return Hash::HashPassword($string);
	}
}

/* End file Auth.php */