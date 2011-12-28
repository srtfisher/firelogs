<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class Core extends CI_Model
{
	/**
	 * The constructor
	 *
	 * @access    public
	**/
	public function __construct()
	{
		parent::__construct();
		
		// Check maintance mode
		// Maintance mode is NOT active in CLI so that we can perform migrations
		if (! IS_CLI ) :
			if ( file_exists(FCPATH.DS.'.maint'))
			{		
				// We're still in maint!
				set_status_header(503);
				include(FCPATH.DS.'maint-mode.php');
				exit;
			}
		endif;
		
		// Load the cache driver (We have APC setup in production with file on development servers
		$this->load->driver('cache',  apply_filters('cache-drivers', array('adapter' => 'apc', 'backup' => 'file')));
		
		// Auth
		$this->load->static_lib('Auth');
		Auth::init();
		
		
	}
	
	/**
	 * Generate a short URL
	 *
	 * @access public
	 * @param string $url The URL to shorten
	 * @return string The short URL
	**/
	public function generate_short_url($url)
	{
		$short_domain = 'http://tntct.co/';
		$url = trim($url);
		
		//	Check if one exists
		$get = $this->db->from('short_links')->where('link_full', $url)->get();
		if ($get->num_rows() > 0)
		{
			//	It exists, use that one
			return $short_domain.'g~'.$get->row()->link_slug;
		}
		
		//	Create one!
		$hash = generate_custom_str(6);
		$this->db->set('link_slug', $hash);
		$this->db->set('link_full', $url);
		$this->db->set('link_time', current_datetime());
		$this->db->insert('short_links');
		
		return $short_domain.'g~'.$hash;
	}
	
	/**
	 * Set the current good message
	 *
	 * @access public
	 * @param mixed Either string or simple_error
	**/
	public function set_good($argument)
	{
		if (is_simple_error($argument))
			$argument = get_simple_error($argument);
		
		$argument = (string) $argument;
		$this->session->set_userdata('current_good', $argument);
	}
	
	/**
	 * Set the current error message
	 *
	 * @access public
	 * @param mixed Either string or simple_error
	**/
	public function set_error($argument)
	{
		if (is_simple_error($argument))
			$argument = get_simple_error($argument);
		
		$argument = (string) $argument;
		$this->session->set_userdata('current_error', $argument);
	}
	
	/**
	 * Get the current error
	 *
	 * @return null|string
	**/
	public function get_current_error()
	{
		$userdata = $this->session->userdata('current_error');
		if (! $userdata)
			return NULL;
		else
			return $current_good;
	}
	
	/**
	 * Get the current good message
	 *
	 * @access public
	 * @return bool|string
	**/
	public function get_current_good()
	{
		$userdata = $this->session->userdata('current_good');
		if (! $userdata)
			return NULL;
		else
			return $current_good;
	}
	
	/**
	 * Get an option
	 *
	 * @access public
	**/
	public function get_option($key)
	{
		$cached = $this->cache->get('option_'.$key);
		if (! $cached )
		{
			$this->db->where('option_name', $key);
			$get = $this->db->get('options');
			
			if ($get->num_rows() == 0)
				return NULL;
			
			$this->cache->save('option_'.$key, $get->row()->meta_value, 3600);
			return maybe_unserialize( $get->row()->meta_value);
		}
		else
		{
			return maybe_unserialize($cached);
		}
	}
	
	/**
	 * Save an option
	 *
	 * @access public
	**/
	public function save_option($key, $value)
	{
		$exists = ($this->get_option($key) == NULL) ? FALSE : TRUE;
		
		$value = maybe_serialize($value);
		$this->cache->save('option_'.$key, $value);
		
		if ( $exists)
		{
			$this->db->set('option_value', $value);
			$this->db->where('option_name', $key);
			return $this->db->update('options');
		}
		else
		{
			$this->db->set('option_value', $value);
			$this->db->set('option_name', $key);
			return $this->db->insert('options');
		}
	}
	
	/**
	 * Setup the application's cache folder
	 * We want to make sure that there is an index.html file there.
	 *
	 * @access private
	 * @return void
	**/
	public function setup_cache_folder( $board = FALSE )
	{
		//	The file cache folder
		$cache_folder = dirname(dirname(__FILE__)).DS.'cache'.DS;
		
		if ( ! file_exists($cache_folder))
		{
			mkdir($cache_folder);
			chmod($cache_folder, 755);
		}
		
		//	Asset cache folder
		$cache_folder = dirname(FCPATH.DS.'cache'.DS);
		
		if ( ! file_exists($cache_folder))
		{
			mkdir($cache_folder);
			chmod($cache_folder, 755);
		}
	}
	
	/**
	 * Get a list of recent tweets for a user
	 * This is internally used ONLY.
	 *
	 * @access provate
	**/
	public function get_tweets( $user = 'teensintech' )
	{
		if ($get = $this->cache->get('tweets_from_'.$user))
			return json_decode($get);
		
		$url = 'http://api.twitter.com/1/statuses/user_timeline.json?screen_name=' . $user.'&count=6';
		$data = get_web_page($url);
		
		//	Cache it
		$this->cache->save('tweets_from_'.$user, $data, 900);
		
		return json_decode( $data );
	}
}
/* End of file connect.php */