<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * The Post Feed Model
 *
 * We are borrowing a lot of our thinking from WordPress.
 * Storing "posts" in one central table (activity_posts). But there is other things, too.
 * {@see http://codex.wordpress.org/Post_Types}
 *
 * We use a post table to store the activity for the site. We store quite a lot of different data
 * in that post table so we use post types. The `post_type` will differentiate the types.
 * The types are limitless, but they allow us to add more types as the types of posts we support
 * grows. Use the functions in this model to create a post, not manually inserting it.
 *
 * We will use these post_types for different things.
 * PLEASE read /doc/feed-template.md for document on how we setup the feed in the Controller.
 *
 * @since 0.5
 * @package Connect
**/
class Feed extends CI_Model
{
	/**
	 * We loop though the feed. We hold the current item in this variable.
	 * This variable is used though the model but not directly because
	 * it is a private variable.
	 *
	 * @global array|null
	 * @access private
	**/
	private $current_post_row = NULL;
	
	/**
	 * The current post ID
	 * Only setup if the loop has started. It starts with -1 so that
	 * when we start the loop it will begin at 0.
	 * You can use next_post() to bump the post number.
	 *
	 * @access private
	 * @global int
	**/
	private $current_post_id = -1;
	
	/**
	 * The current feed objects
	 * Don't touch this object directly. It's an array of the results from the DB
	 *
	 * @access public
	 * @global array
	**/
	public $current_feed = array();
	
	/**
	 * The compare value
	 *
	 * @access public
	**/
	public $compare_value = 'unix_time';
	
	/**
	 * The current limit of posts
	 *
	 * @gloabl int
	**/
	public $limit		=	20;
	
	/**
	 * Load posts after a certain ID
	 * If it's = 0, it will be ignored
	 *
	 * @global int
     **/
     public $after		=	0;
	
	/**
	 * Load posts before a certain ID
	 * If it's = 0, it will be ignored.
	 *
	 * @global int
     **/
     public $before      =    0;
     
     /**
      * The URL base to poll for updates.
      * This has to be an absolute URL that doesn't contain '?' or any GET array.
      *
      * @global string
     **/
     public $base		=	'/';
	
	/**
	 * Should we disable the polling on this page?
	 * Only used for the JS side.
	 *
	 * @global bool
     **/
     public $disable_poll     =    FALSE;
     
	/**
	 * The constructor
	 *
	 * @access public
	**/
	public function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * Create a internal post with ease
	 *
	 * @access public
	 * @param mixed $data The data to create the post with
	**/
	public function quick_post( $subject, $content, $type, $data = '', $author = FALSE, $is_company = false)
	{
		if (! $author AND is_bool($author) )
		{
			if ( ! logged_in() )
				return FALSE;
			else
				$to = current_uid();
		}
		
		$author = (int) $author;
		
		// If it's == 2, it's a system generated post
		if($author < 1 OR $author == 0) $author = 2;
		
		return $this->create_post(array(
			'post_author'		=>	$author,
			'post_title'		=>	$subject,
			'post_content'		=>	$content,
			'post_type'			=>	$type,
			'post_data'			=>	$data,
			'is_company'		=>	$is_company,
		));
	}
	
	/**
	 * Create a post
	 *
	 * @access    public 
	 * @param     array $postarr The array of data for the posts
	 * @param     int|null $post_id The post ID we're editing (leave NULL for a new post)
	 * @return    int|object
	**/
	public function create_post($data, $post_id = NULL)
	{
		$defaults = array(
			'post_id'			=>	0,	// We check if it's < 0
			'post_author' 		=>	$this->Users->uid(),
			'is_company'		=>	0,
			'post_date_gmt'		=>	current_datetime(),
			'post_content'		=>	'',
			'post_title'		=>	'',
			'post_status'		=>	'publish',
			'comment_status'	=>	'open',
			'post_slug'			=>	'',
			'post_modified_gmt'	=>	'',
			'post_parent'		=>	0,
			'post_type'			=>	'general',
			'post_mime_type'	=>	'',
			'comment_count'		=>	0,
			'post_data'			=>	'',
			'moderator_hide'	=>	0,
			'topic_id'			=>	0,
		);

		// Merge the passed data with the defaults
		$data = wp_parse_args($data, $defaults);
		
		// Validate it!
		if (empty($data['post_title']))
			return simple_error('You can\'t have an empty post title!');
		
		if (empty($data['post_content']))
			return simple_error('You can\'t have an empty post content!');
		
		if (! $data['post_type'] OR empty($data['post_type']))
			$data['post_type'] = 'general';
		
		if (! $data['post_status'] OR empty($data['post_status']))
			$data['post_status'] = 'publish';
		
		$data['post_author'] = (int) $data['post_author'];
		$data['is_company'] = (int) $data['is_company'];
		$data['post_parent'] = (int) $data['post_parent'];
		$data['moderator_hide'] = (int) $data['moderator_hide'];
		$data['topic_id'] = (int) $data['topic_id'];
		
		// It's a tiny int (either 1 or 0)
		if ($data['is_company'] < 0 OR $data['is_company'] > 1) 
			$data['is_company'] = 0;
		
		// Feed data can be stored in a serialized array
		$data['post_data'] = maybe_serialize($data['post_data']);
		
		// Force somethings
		$data['post_date_gmt'] = current_datetime();
		if (empty($data['post_slug'])) $data['post_slug'] = do_title($data['post_title']);
		
		// Set the rows up
		// We loop though the defaults because they contain the columns.
		foreach($defaults as $row_name => $null) :
			if ($row_name !== 'post_id')
				$this->db->set($row_name, $data[$row_name]);
		endforeach;
		
		if ($data['post_id'] > 0)
		{
			// Updating it
			$this->db->where('post_id', $data['post_id']);
			$this->db->update('activity_posts');
			
			do_action('update_post', $data['post_id']);
			
			return $data['post_id'];
		}
		else
		{
			// Creating it
			$this->db->insert('activity_posts');
			$post_id = $this->db->insert_id();
			
			do_action('create_post', $post_id);
			
			return $post_id;
		}
	}
	
	/**
	 * Get the post types that we want to load into the feed from other users.
	 * When a user is following someone else, we don't want to see who is following them, get it?
	 *
	 * @access public
	 * @return string
	**/
	public function get_public_post_types($array = FALSE)
	{
		if ($array)
		{
               return array(
                         'forum',
                         'press_person',
                         'press_company',
                         'link',
                         (can_do_feature('board')) ? 'jobs' : 'fake',
               );
          }
          
          // Wrap them in quotes for proper SQL syntax
		$array = array(
			'\'forum\'',
			'\'press_person\'',
			'\'press_company\'',
			'\'link\'',
			(can_do_feature('board')) ? '\'jobs\'' : '\'fake\'',
		);
		
		return implode(', ', $array);
	}
	
	//	-------------------------------------------------------------
	//	 Loading posts into the feed
	//	------------------------------------------------------------
	/**
	 * Set 'before' and 'after' variables to integers.
	 *
	 * @access public
	 * @return void
     **/
     public function compares_to_int()
     {
          $this->before = (int) $this->before;
          $this->after = (int) $this->after;
     }
     
     /**
	 * Load posts from the people/companies that a user follows and their own feed, too
	 * They need to be logged in, this function will return false if they aren't.
	 *
	 * @access public
	 * @param int The user (default is current)
	**/
	public function load_following_post($user = 0)
	{
		$user = (int) $user;
          if ($user < 1)
		{
			if (! $this->Users->logged_in() )
				return FALSE;
			else
				$user = $this->Users->uid();
		}
		
		// These posts are publicly displayed for all user to see.
		$public_post_types = $this->get_public_post_types();
		
		// Reset the comparable values.
		$this->compares_to_int();
		
		if ($this->before > 0)
		{
		   // Before a certain ID
		   $query = $this->db->query("
	SELECT * FROM activity_posts WHERE
	(
	(
		`is_company` = 1 AND
		`post_author` IN (
			SELECT `to` FROM `follows` WHERE `from` = ? AND `is_company` = 1
		)
		AND `post_type` IN (".$public_post_types.")
	) 
	OR
	(
		`is_company` = 0 AND
		`post_author` IN (
			SELECT `to` FROM follows WHERE `from` = ? AND `is_company` = 0
		)
		AND `post_type` IN (".$public_post_types.")
	)
	OR
	(`post_author` = ?)
	)
	AND
	(`post_id` > ".$this->before.")
	ORDER BY `post_date_gmt` DESC
	LIMIT 0, ".$this->limit."
		", array($user, $user, $user));
		   
		}
		else if ($this->after > 0)
		{
		   // After a certain ID
		   $query = $this->db->query("
	SELECT * FROM activity_posts WHERE
	(
	(
		`is_company` = 1 AND
		`post_author` IN (
			SELECT `to` FROM `follows` WHERE `from` = ? AND `is_company` = 1
		)
		AND `post_type` IN (".$public_post_types.")
	) 
	OR
	(
		`is_company` = 0 AND
		`post_author` IN (
			SELECT `to` FROM follows WHERE `from` = ? AND `is_company` = 0
		)
		AND `post_type` IN (".$public_post_types.")
	)
	OR
	(`post_author` = ?)
	)
	AND
	(`post_id` < ".$this->after.")
	ORDER BY `post_date_gmt` DESC
	LIMIT 0, ".$this->limit."
		", array($user, $user, $user));
          }
          else
          {
               // Default feed  
               $query = $this->db->query("
	SELECT * FROM activity_posts WHERE
	(`is_company` = 1 AND
		`post_author` 
		IN (
			SELECT `to` FROM `follows` WHERE `from` = ? AND `is_company` = 1
		)
		AND `post_type` IN (".$public_post_types.")
	) 
	OR
	(
		is_company = 0 AND
		`post_author` IN (
			SELECT `to` FROM follows WHERE `from` = ? AND `is_company` = 0
		)
		AND `post_type` IN (".$public_post_types.")
	)
	OR
	(`post_author` = ?)
	ORDER BY `post_date_gmt` DESC
	LIMIT 0, ".$this->limit."
		", array($user, $user, $user));
          }
          
          // We didn't find anything
          if ($query->num_rows() == 0)
			return FALSE;
		
		// Add all of them and return true
		foreach($query->result() as $row)
			$this->add_item($row);
          
          return true;
     }
	
	/**
	 * Load posts for the public frontend
	 * We want a very small selection of groups that will sell Connect to them
	 * but not give away personal info.
	 *
	 * @access    public
	 * @param     void
	**/
	public function load_public_eye()
	{
		$this->db->where_in('post_type', array(
			'forum',
			'questions',
			'press_person',
			'press_company',
			// TODO: Add more
		));
		
		 // Reset the vars.
		$this->compares_to_int();
		
		// Before/After a certain ID
		if ($this->before > 0)
			$this->db->where('post_id >', $this->before);
          elseif($this->after > 0)
               $this->db->where('post_id <', $this->after);
          
		$this->db->limit($this->limit);
		$this->db->order_by('post_date_gmt', 'DESC');
		$query = $this->db->get('activity_posts');
		
		if ($query->num_rows() == 0)
			return false;
		
		foreach($query->result() as $row)
			$this->add_item($row);
	}
	
	/**
	 * Build a Query
	 * Perform a query on the feed and load certain posts.
	 *
	 * You have to call `get()` on the returned object to get a MySQL query if you pass `no_get` to the data array
	 * If you pass that, we cannot cache it.
	 *
	 * @param     array An array to be used to build the query
	 * @param     Add the resulted item to the feed?
	 * @param     bool You can specify a cache time for the query
	 * @todo      Adding support for more creative things!
	 * @return    object It will return the active record object, not a MySQL result!
	**/
	public function build_query($data = array(), $add_to_feed = TRUE, $cache_time = 0)
	{
		$defaults = array(
			// Default ordering is by date from the most recent posts
			'order'               =>	'desc',
			'order_by'            =>	'post_date_gmt',
			
			// Post type (see doc)
			'post_type'           =>	'post',
			
			// The author ID (must be > 0)
			'post_author'         =>	-1,
               
               // Post Titles
               'post_title'             => '',
               'post_title_like'        => '',
               'post_slug'              => '',
               
               // Post content
               'post_content_like'      => '',
               
               // Post ID
               'post_id'                => -1,
               'post_id_compare'        => '=',
               
               // Offset and limit
			'offset'              =>	0,
			'limit'               =>	20,
			
			// Are these the posts from the people the user follows?
			'is_user_followed'    =>	FALSE,
			
			// Meta compare and other things
			'meta'                => array(),
			
			// Default to getting the query
			'no_get'              => FALSE,
			
			// Don't' support the Feed before/after variables
			'no_support_feed_compares' =>   FALSE,
		);
		
		// Merge the passed data with the defaults
		$data = wp_parse_args($data, $defaults);
		
		$query_name = http_build_query($data);
		$cached = $this->cache->get('query-'.$query_name);
		
		// It exists!
		if ( ! $data['no_get'] AND ! is_null($cached) AND ! is_bool($cached) AND $cache_time > 0)
               return maybe_unserialize($cached);
          
		// Post Type, ftw - You can pass an array for multiple post types
          if (is_array($data['post_type']) AND count($data['post_type']) > 0)
               $this->db->where_in('post_type', $data['post_type']);
          elseif (! empty($data['post_type']))
               $this->db->where('post_type', $data['post_type']);
          
          // Post author
          if ($data['post_author'] > 0)
               $this->db->where('post_author', $data['post_author']);
          
          // Posts from the user's following (people they follow)
          if ($data['is_user_followed'])
          {    
               // Huh… Not quite there yet!
          }
          
          // Post ID and comparing it to something (=, >, >==, etc)
          if (! $data['no_support_feed_compares'] AND ($this->before > 0 OR $this->after > 0))
          {
               if ($this->before > 0)
                    $this->db->where('activity_posts.post_id > ', $this->before);
               elseif($this->after > 0)
                    $this->db->where('activity_posts.post_id < '.$this->after);
          }
          elseif ($data['post_id'] > 0)
          {
               $this->db->where('post_id '.$data['post_id_compare'] . ' ', $data['post_id']);
          }
          
          // Post Title
          if (! empty($data['post_title']))
          {
               $this->db->where('post_title', $data['post_title']);
          }
          elseif(! empty($data['post_title_like']))
          {
               $this->db->like('post_title', $data['post_title']);
          }
          
          // Post content
          if (! empty($data['post_content_like']))
               $this->db->like('post_content', $data['post_content_like']);
          
          // Post slug
          if (! empty($data['post_slug']))
               $this->db->where('post_slug', $data['post_slug']);
          
          // Join in meta for the posts
          if (count($data['meta']) > 0)
          {
               foreach($data['meta'] as $array_key => $array_val) :
                   
                    // We can look for a specific key
                    // Otherwise, it's going to just be joining the meta key for use in the result
                    if (isset($array_val['meta_compare']))
                    {
                         $this->db->join('postmeta', 'postmeta.post_id = activity_posts.post_id '
                         .'AND postmeta.meta_key = \''.$array_val['meta_key'].'\' '
                         .'AND postmeta.meta_value '.$array_val['meta_compare'] .' \''. $array_val['meta_value'].'\'');
                    }
                    else
                    {
                         $this->db->join('postmeta', 'postmeta.post_id = activity_posts.post_id AND postmeta.meta_key = \''.$array_val['meta_key'].'\'');
                    } 
               endforeach;
          }
          
		// Order By
		if (is_string($data['order_by']) AND ! empty($data['order_by']))
		   $this->db->order_by($data['order_by'], $data['order']);
		
		// Limit, supporting the model-level limit
		if (! $data['no_support_feed_compares'] AND $this->limit > 0)
               $this->db->limit($this->limit, $data['offset']);
          elseif($data['limit'] > 0)
               $this->db->limit($data['limit'], $data['offset']);
		
		// Return it
          if ($data['no_get'])
               return $this->db->from('activity_posts');
          
          // Are we caching it?
          $get_it = $this->db->get('activity_posts');
          
          // Cache it since they asked for it
          if ( $cache_time > 0)
               $this->cache->save('query-'.$query_name, maybe_serialize($get_it), $cache_time);
          
          if ($add_to_feed)
          {
               foreach($get_it->result() as $row)
                    $this->add_item($row);
          }
          
          return $get_it;
     }
	
	/**
	 * Generate a SQL WHERE argument that will check the before/after variables.
	 *
	 * @return    string
	 * @param     void
     **/
     public function generate_sql_id_compare()
     {
          // Make them integers
          $this->compares_to_int();
          
          if ($this->before > 0)
               return '`post_id` > '.$this->before;
          elseif($this->after > 0)
               return '`post_id` < '.$this->after;
          else
               return '';
     }
     
	/**
	 * Get the posts from all the users/companies that a user follows
	 *
	 * @return    object
	 * @param     int The user ID
	 * @deprcated Just don't use it - Will be removed soon!
	**/
	public function get_followed_posts($user = 0, $offset = 0, $limit = 20)
	{
		if ($user < 1)
		{
			if (! logged_in() )
				return FALSE;
			else
				$user = current_uid();
		}
		$public_post_types = $this->get_public_post_types();
		
		// v0.1
		$cached = false;// $this->cache->get('followed_user_'.$user.'_offset_'.$offset.'_limit_'.$limit);
		if (! $cached)
		{
			$query = $this->db->query("
	SELECT * FROM activity_posts WHERE
	(`is_company` = 1 AND
	`post_author` IN (
		SELECT `to` FROM `follows` WHERE `from` = ? AND `is_company` = 1)
		AND `post_type` IN (".$public_post_types.")
	) 
	OR
	(is_company = 0 AND
	`post_author` IN (
		SELECT `to` FROM follows WHERE `from` = ? AND `is_company` = 0)
		AND `post_type` IN (".$public_post_types.")
	)
	OR
	(`post_author` = ?)
	ORDER BY `post_date_gmt` DESC
	LIMIT ".$offset.", ".$limit."
		", array($user, $user, $user));
			// Cache it (30 seconds because it's pretty realtime)
			//$this->cache->save('followed_user_'.$user.'_offset_'.$offset.'_limit_'.$limit, serialize($query->result()), 25);
			
			return $query->result();
		}
		else
		{
			// The cached result
			return maybe_unserialize($cached);
		}
	}
	
	/**
	 * Add items to the feed.
      * We have a very simple way of adding posts. You can add any post at any time.
      * We will sort them when we display the feed based upon their post time.
      *
	 * The argument should be a row from a query to the post table.
	 *
	 * @access public
	 * @param array
	 * @return void
	**/
	public function add_item($data)
	{
		if (! is_object($data))
			throw new InvalidArgumentException('You can only pass an object to Feed::add_item().');
		
		//	We use a unix timestamp because it's really simple to sort with int
		if (! isset($data->unix_time))
			$data->unix_time = strtotime($data->post_date_gmt);
		
		$data->post_title = stripslashes($data->post_title);
		$data->post_content = stripslashes($data->post_content);
		$this->current_feed[] = $data;
	}
	
	/**
	 * Export the current feed.
	 * We are using this only if we're going to export a number of posts and look to sort them
	 *
	 * @access public
	 * @return array
	**/
	public function export()
	{
		// We're just starting!
          if (count( $this->current_feed ) > 1 )
			$this->sort();
		
		return $this->current_feed;
	}
	
	/**
	 * Sort the current post feed
	 *
	 * @access public
	 * @return void
	**/
	public function sort()
	{
		//	We can't sort only one item!
		if (count( $this->current_feed ) == 1 ) return;
		
		$temp_array = array();
		foreach($this->current_feed as $key => $post)
		{
			if (! isset($temp_array[$post->unix_time]))
				$temp_array[$post->unix_time] = array();
			
			$temp_array[$post->unix_time][$post->post_id] = $post;
		}
		
		// Reset it so we can repopulate it again
		$this->current_feed = array();
		
		krsort($temp_array);
		foreach($temp_array as $time => $data)
		{
			if (count($data) > 1)
				krsort($data);
			
               foreach($data as $sub_data)
			{
                    // Link the posts
                    $sub_data->post_content = linkify($sub_data->post_content);
                    
                    // Add it to the feed
                    $this->current_feed[] = $sub_data;
               }
		}
	}
	
	/**
	 * Set the current post row
	 *
	 * @access    public
	 * @param     int
	**/
	public function set_current_row($ID)
	{
		//	Already set to the current post
		if ($this->current_post_id == $ID) return TRUE;
		
		// Reset these
		$this->current_post_row = NULL;
		$this->current_post_id = NULL;
		
		//	Find it and set it if it exists
		for ($i = 0; $i <= count($this->current_feed); $i++)
		{
			//	Just making sure it exists
			if (! isset($this->current_feed[$i]))
				continue;
			
			//	Found the post we were looking for!
			if ($this->current_feed[$i]->ID == $ID)
			{
				$this->current_post_row = $this->current_feed[$i];
				$this->current_post_id = $ID;
				
				return TRUE;
			}
		}
		
		//	Nothing found :(
		return FALSE;
	}
	
	/**
	 * Add a post by ID
	 *
	 * @access public
	 * @return void|bool Nothing if it's good, FALSE if it can't be found
	 * @todo caching
	**/
	public function add_by_id($ID)
	{
		$ID = (int) $ID;
		if (0 == $ID) return FALSE;
		
		$this->db->where('post_id', $ID);
		$get = $this->db->get('activity_posts');
		
		if ( $get->num_rows() == 0 )
			return FALSE;
		else
			$this->add_item($get->row());
			return TRUE;
	}
	
	/**
	 * The current post
	 * Returns the current post row
	 *
	 * @access public
	 * @return bool|object
	**/
	public function current_post()
	{
		if ($this->current_post_id < 0)
			return FALSE;
		
		return (isset($this->current_feed[$this->current_post_id])) ? $this->current_feed[$this->current_post_id] : FALSE;
	}
	
	/**
	 * Bump to the next post
	 *
	 * @access public
	**/
	public function next_post()
	{
		$this->current_post_id++;
	}
	
	/**
	 * Do we have a post active?
	 * Useful when we have to loop though the posts
	 *
	 * @access public
	 * @return bool
	**/
	public function have_posts()
	{
		if ( $this->current_post_id + 1 < count($this->current_feed) ) {
			return true;
		} elseif ( $this->current_post_id + 1 == count($this->current_feed) && count($this->current_feed) > 0 ) {
			do_action_ref_array('loop_end', array(&$this));
			// Do some cleaning up after the loop
			$this->rewind_posts();
		}

		$this->in_the_loop = false;
		return false;
	}
	
	/**
	 * Sets up the current post
	 * Will bump to the next post and filter the loop when we first begin.
	 *
	 * @access public
	 * @return void
	**/
	public function the_post()
	{
		if ($this->current_post_id == -1)
		{
			do_action_ref_array('loop_start', array(&$this));
			$this->sort();
			$this->in_the_loop = TRUE;
		}
		
		// Bump to the next version
		$this->next_post();
		
	}
	
	/**
	 * Rewind the posts and reset post index.
	 *
	 * @access public
	 */
	public function rewind_posts()
	{
		$this->current_post_id = -1;
	}
	
	/**
	 * Is the previous post the same post type from the same user?
	 *
	 * @access public
	 * @return bool
	**/
	public function previous_type_check()
	{
		if (! isset($this->in_the_loop) OR ! $this->in_the_loop OR $this->current_post_id == 0)
			return FALSE;
		
		// We're in the loop
		if (! isset($this->current_feed[$this->current_post_id-1]))
			return FALSE;
		
		$previous_post = $this->current_feed[$this->current_post_id-1];
		$current_post = $this->current_post();
		
		if ($previous_post->post_type == $current_post->post_type AND $previous_post->post_author == $current_post->post_author)
			return TRUE;
		else
			return FALSE;
	}
	
	/**
	 * Is the next post the same post type from the same user?
	 *
	 * @access public
	 * @return bool
	**/
	public function next_type_check()
	{
		if (! isset($this->in_the_loop) OR ! $this->in_the_loop)
			return FALSE;
		
		// We're in the loop
		if (! isset($this->current_feed[$this->current_post_id+1]))
			return FALSE;
		
		$next_post = $this->current_feed[$this->current_post_id+1];
		$current_post = $this->current_post();
		
		if ($next_post->post_type == $current_post->post_type AND $next_post->post_author == $current_post->post_author)
			return TRUE;
		else
			return FALSE;
	}
	
	/**
	 * Keep searching though previous posts until you find a parent post that doesn't have
	 * a similar parent post.
	 *
	 * @access public
	 * @return bool|int
	**/
	public function parent_post_search()
	{
		if (! $this->previous_type_check() )
			return $this->current_post()->post_id;
		
		if ($this->current_post_id == 0)
			return $this->current_post()->post_id;
		
		// Loop - from the bottom up!
		for($i=$this->current_post_id;$i > -1; $i-1)
		{
			$find = $this->check_post_for_parent($i);
			
			if (! $find AND is_bool($find))
				return $this->current_post()->post_id;
			else
				return $find;
		}
		return $this->current_post()->post_id;
	}
	
	/**
	 * Check a post
	 * This function calls on itself to eventually loop to the most parent of a set of posts.
	 * It can take a while if there are a lot of posts loaded into the feed.
	 *
	 * @param int
	**/
	public function check_post_for_parent($ID)
	{
		if (! isset($this->current_feed[$ID]))
			return FALSE;
		
		if ($ID == 0)
			return $this->current_feed[0]->post_id;
		
		
		$previous = $this->current_feed[$ID-1];
		$current = $this->current_feed[$ID];
		
		if ($current->post_type == $previous->post_type AND $current->post_author == $previous->post_author)
			return $this->check_post_for_parent($ID-1);
		else
			return $current->post_id;
	}
	
	/**
	 * Return a JSON string for use in the feed
	 * This is used in the controller so that when they load a before/after post,
	 * we want to make it really easy to load it into the UI.
	 *
	 * @return string
     **/
     public function json_export_page($is_board = false)
     {
          
          if ($is_board)
               $feed_html = $this->load->view('board/feed/feed', array(), TRUE);
          else
               $feed_html = $this->load->view('feed/the-feed', array(), TRUE);
          
          return json_encode(array(
               'post_count'        =>   count($this->current_feed),
               'feed_html'         =>   $feed_html,
               'first_id'          =>   FIRST_ID,
               'last_id'           =>   LAST_ID,
          ));
     }
     
     /**
      * Set a single meta for a post
      * This is a persistent storage for the post rather than using a cache or redis
      *
      * @param      string The meta key
      * @param      mixed The meta value
      * @param      int The post ID
      * @return     bool
     **/
     public function set_postmeta($key, $value = '', $post_id)
     {
          $check = $this->db->from('postmeta')
          ->where('post_id', $post_id)
          ->where('meta_key', $key)
          ->get();
          
          if ($check->num_rows() == 0)
          {
               $this->db->set('meta_key', $key)
               ->set('meta_value', maybe_serialize($value))
               ->set('post_id', $post_id)
               ->insert('postmeta');
          }
          else
          {
               $this->db->where('meta_key', $key)
               ->set('meta_value', maybe_serialize($value))
               ->where('post_id', $post_id)
               ->update('postmeta');
               
               // Kill the cache
               $this->cache->delete('postmeta-'.$key.'-'.$post_id);
          }
          
          return TRUE;
     }
     
     /**
      * Get a post's meta for a key
      * We support serialized things, so we will attempt to unserialize it for you.
      *
      * @access     public
      * @param      string The meta key
      * @param      int
      * @return     mixed
     **/
     public function get_postmeta($key, $post_id, $no_cache = FALSE)
     {
          $cached = $this->cache->get('postmeta-'.$key.'-'.$post_id);
          
          if (! is_null($cached) AND ! is_bool($cached) AND ! $no_cache)
               return maybe_unserialize($cached);
          
          $get = $this->db->where('meta_key', $key)
               ->where('post_id', $post_id)
               ->order_by('meta_id', 'DESC')
               ->get('postmeta');
          
          if ($get->num_rows() == 0)
               return FALSE;
          
          // Cache it
          $cached = $this->cache->save('postmeta-'.$key.'-'.$post_id, $get->row()->meta_value, 3600);
          
          // Return! :)
          return maybe_unserialize($get->row()->meta_value);
     }
     
     /**
      * Load posts from a tag
      *
      * @param      string
      * @return     void
     **/
     public function load_from_tag($tag, $post_type)
     {
          $tag = do_title($tag);
          
          $tag_data = $this->db->from('terms')->where('slug', $tag)->get();
          if ($tag_data->num_rows() == 0)
               show_404();
          
          $tag_row = $tag_data->row();
     }
     
     /**
      * Export the current feed to a RSS Feed
      * It will echo it out and die right there
      *
      * @access     public
      * @return     void
      * @param      void
     **/
     public function export_to_rss()
     {
          // Load the Class
          require_once(dirname(dirname(__FILE__)).DS.'libraries'.DS.'feedcreator'.EXT);
          $this->load->helper('inflector');
          
          //define channel
          $rss = new UniversalFeedCreator();
          $rss->useCached();
          $rss->title         = $this->Connect->title();
          $rss->description   = "Feeds from Young Entrepreneurs";
          $rss->link          = site_url();
          $rss->syndicationURL = current_url();
          
          // Public Post Types
          $public = $this->Feed->get_public_post_types(TRUE);
          
          // The loop
          if ( $this->Feed->have_posts() ) : while ( $this->Feed->have_posts() ) : $this->Feed->the_post();
               
               // The current post
               $current_post = $this->Feed->current_post();
               
               if (in_array($current_post->post_type, $public)) : 
                    
                    // It passed, add it to the feed
                    switch($current_post->post_type)
                    {
                         case('jobs');
                              $url = site_url('board/p/'.$current_post->post_slug);
                         break;
                         
                         case('forum');
                              $url = 'http://tntct.co/f~'.end(explode('-', $current_post->post_slug));
                         default;
                              $url = site_url('single/'.$current_post->post_id);
                         break;
                    }
                    
                    //channel items/entries
                    $item = new FeedItem();
                    $item->title   = humanize($current_post->post_type).': '.$current_post->post_title;
                    $item->link    = $url;
                    $item->description = Markdown($current_post->post_content);
                    //$item->source = "http://mydomain.net";
                    $item->author = $this->Users->get('user_slug', $current_post->post_author);;
                    
                    // Actually add the object
                    $rss->addItem($item);
               endif;
          endwhile; endif;
          
          //Valid parameters are RSS0.91, RSS1.0, RSS2.0, PIE0.1 (deprecated),
          // MBOX, OPML, ATOM, ATOM1.0, ATOM0.3, HTML, JS
          $rss->outputFeed("RSS2.0"); 
     }
}
/* End of file feed.php */