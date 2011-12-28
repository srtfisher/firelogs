<?php
/**
 * Essential Functions that will actually help us
 *
 * @package Connect
 * @since 0.1
**/

/**
 * Serialize data, if needed.
 *
 * @since 2.0.5
 *
 * @param mixed $data Data that might be serialized.
 * @return mixed A scalar data
 */
function maybe_serialize($data) {
	if (is_array($data) OR is_object($data))
		return serialize($data);

	if (is_serialized($data))
		return serialize($data);

	return $data;
}

/**
 * Unserialize value only if it was serialized.
 *
 * @since 2.0.0
 *
 * @param string $original Maybe unserialized original, if is needed.
 * @return mixed Unserialized data can be any type.
 */
function maybe_unserialize($original)
{
	if (is_serialized($original)) // don't attempt to unserialize data that wasn't serialized going in
		return @unserialize($original);
	return $original;
}

/**
 * Check value to find if it was serialized.
 *
 * If $data is not an string, then returned value will always be false.
 * Serialized data is always a string.
 *
 *
 * @param mixed $data Value to check to see if was serialized.
 * @return bool False if not serialized and true if it was.
 */
function is_serialized($data)
{
	// if it isn't a string, it isn't serialized
	if (!is_string($data))
		return false;
	$data = trim($data);
	if ('N;' == $data)
		return true;
	if (!preg_match('/^([adObis]):/', $data, $badions))
		return false;
	switch ($badions[1]) {
		case 'a' :
		case 'O' :
		case 's' :
			if (preg_match("/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data))
				return true;
			break;
		case 'b' :
		case 'i' :
		case 'd' :
			if (preg_match("/^{$badions[1]}:[0-9.E-]+;\$/", $data))
				return true;
			break;
	}
	return false;
}

/**
 * Check whether serialized data is of string type.
 *
 * @since 2.0.5
 *
 * @param mixed $data Serialized data
 * @return bool False if not a serialized string, true if it is.
 */
function is_serialized_string($data)
{
	// if it isn't a string, it isn't a serialized string
	if (!is_string($data))
		return false;
	$data = trim($data);
	if (preg_match('/^s:[0-9]+:.*;$/s', $data)) // this should fetch all serialized strings
		return true;
	return false;
}

/**
 * Function to get the domain from a URL
 *
 * @param string
 * @return string
**/
function getDomain($url)
{
	$parsed = parse_url($url); 
	return str_replace('www.', '', $parsed['host']); 
	return $hostname; 
}

/**
 * See if a string is in a valid URL format
 *
 * @param string
 * @return bool
**/
function valid_url($url)
{
	$pattern = '|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i';
	if (!preg_match($pattern, $url))
		return FALSE;
	else
		return TRUE;
}

/*
function write_file($file, $what) {
	if (!$fp = fopen($file, 'w'))
		return false;
		
		
	fwrite($fp, $what);
	fclose($fp);
}
*/

/**
 * Get a web page's content via cURL
 * You can pass other options via the second argument
 *
 * @access public
 * @return string|bool
 * @todo Setup error handling
**/
function get_web_page( $url ) {
    $options = array(
        CURLOPT_RETURNTRANSFER => true,     // return web page
        CURLOPT_HEADER         => false,    // don't return headers
        CURLOPT_FOLLOWLOCATION => true,     // follow redirects
        CURLOPT_ENCODING       => "",       // handle all encodings
        CURLOPT_USERAGENT      => "Teens in Tech Connect v0.1", // who am i
        CURLOPT_AUTOREFERER    => true,     // set referer on redirect
        CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
        CURLOPT_TIMEOUT        => 15,      // timeout on response
        CURLOPT_MAXREDIRS      => 3,       // stop after 10 redirects
    );


    $ch      = curl_init( $url );
    curl_setopt_array( $ch, $options );
    $content = curl_exec( $ch );
    $err     = curl_errno( $ch );
    $errmsg  = curl_error( $ch );
    $header  = curl_getinfo( $ch );
    curl_close( $ch );
	
	if ( !empty( $errmsg ) )
		return $errmsg;
		
	return $content;
}

/**
 * Deprecated
 *
 * @access public
 * @deprecated
**/
function shorten_url($url) {
	$api = 'http://yrn.me/api/get.php?url=';
	$arr =  get_web_page($api . $url);
	return $arr['content'];
}

/**
 * Is the user currently logged in
 *
 * @access     public
 * @return     bool
 * @see Users::is_logged_in()
**/
function logged_in()
{
	return get_instance()->Users->is_logged_in();
}

/**
 * An average message.
 *
 * @return NULL
 * @deprecated Don't use this
**/
function msg($what, $center = FALSE) {
	?><div class="msg <?php if ($center) echo 'alignCenter'; ?>"><p><?=$what; ?></p></div><?php
}

/**
 * An error message.
 *
 * @return NULL
 * @deprecated Don't use this
**/
function msg_error($what, $center = FALSE) {
	?><div class="msg bad <?php if ($center) echo 'alignCenter'; ?>"><p><?=$what; ?></p></div><?php
}

/**
 * A good message.
 *
 * @return NULL
 * @deprecated Don't use this
**/
function msg_good($what, $center = FALSE) {
	?><div class="msg good <?php if ($center) echo 'alignCenter'; ?>"><p><?=$what; ?></p></div><?php
}

/**
 * Function to get a var from a DB result.
 * It'll return whatever is in the first column of a DB result.
 *
 * @param object The CI Database query object
 * @return mixed The first column of the first row
**/
function get_var($result)
{
	if ($result->num_rows() < 1)
		return false;
	foreach($result->row() as $row_item)
		return stripslashes($row_item);
}

/**
 * Function to get the var after executing a query
 *
 * We execute a query and get the var - simple stuff.
 *
 * @param string The DB Query
 * @return mixed
**/
function get_var_query($query)
{
	$CI =& get_instance();
	$do = $CI->db->query($query);
	return get_var($do);
}

/**
 * Get a user's meta
 *
 * @deprecated
**/
function GetUserMeta($what, $ID = false)
{
	return get_instance()->Users->get($what, $ID);
}

/**
 * Get a user's data
 *
 * @deprecated
**/
function GetUserData($what, $ID = false)
{
	return get_instance()->Users->get($what, $ID);
}

/**
 * Update a user's data
 *
 * @deprecated
**/
function UpdateUser($what, $value, $ID = false) {
	return get_instance()->Users->update($what,$value,$ID);
}

/**
 * Update a user's meta
 *
 * @deprecated
**/
function UpdateMeta($what, $value, $ID = false)
{
	return get_instance()->Users->update_meta($what,$value,$ID);
}

/**
 * Echo a user's data
 *
 * @deprecated
**/
function e_UserData($data, $ID = false)
{
	$CI =& get_instance();
	$lookup = $CI->Users->get($data, $ID);
	
	//	We don't echo objects|arrays.
	if (is_object($lookup) OR is_array($lookup))
		return $lookup;
	
	echo $lookup;
}

/**
 * Function will return the initials from a name
 *
 * @access public
**/
function GetInitials($uid)
{
	$name_array = explode(" ", GetUserData('name', $uid));
	return substr($name_array[0], 0, 1) . substr(end($name_array), 0, 1);	
}

/***
 * Geneate a random string
 *
 * Usefull for hashes and passwords.
 * @return string 7 Chars long
**/
function gen_hash() {
	$chars = "abcdefghijkmnopqrstuvwxyz023456789";
    srand((double)microtime()*1000000);
    $i = 0;
    $pass = '' ;
		while ($i <= 7) {
   	    $num = rand() % 33;
    	   $tmp = substr($chars, $num, 1);
    	   $pass = $pass . $tmp;
    	   $i++;
	}
 	  
	return $pass;
}

/**
 * Generate a small string
 *
 * @return string 4 Chars long
**/
function generate_small_str()
{
	$chars = "abcdefghijkmnopqrstuvwxyz";
    srand((double)microtime()*1000000);
    $i = 0;
    $pass = '' ;
		while ($i <= 4) {
   	    $num = rand() % 33;
    	   $tmp = substr($chars, $num, 1);
    	   $pass = $pass . $tmp;
    	   $i++;
	}
 	  
	return $pass;
}

/**
 * Generate a random string with a custom length
 *
 * @return string Length based upon argument
**/
function generate_custom_str($length = 6)
{
	$length = (int) $length;
	
	$chars = "abcdefghijkmnopqrstuvwxyz";
    srand((double)microtime()*1000000);
    $i = 0;
    $pass = '' ;
		while ($i <= $length) {
   	    $num = rand() % 33;
    	   $tmp = substr($chars, $num, 1);
    	   $pass = $pass . $tmp;
    	   $i++;
	}
 	  
	return $pass;
}

/**
 * Password generator
 *
 * @return string
**/
function rand_pw($length=15,$moreCharacters=array())
{
        if (!is_array($moreCharacters))
                if (!is_object($moreCharacters))
                        $moreCharacters = array($moreCharacters);
                        
        $chars = array_merge(
                        range('a','z'),
                        range('A','Z'),
                        range(1,9),
                        array('~','!','@','#','$','%','^','&','*','(',')','_','+','|','{','}','"',':','?','>','<','`','-','=','\\','[',']','\'',';','/','.',','),
                        $moreCharacters
              );
                
        shuffle($chars);
        array_splice($chars,intval($length));
        return implode('',$chars);
}

/**
 * Generic function to see if an array contains a specific value.
 *
 * @since 2.0
**/
function does_array_have_value($array, $value) {
	foreach($array as $key => $ar) {
		if ($ar == $value)
			return $key;
	}
	return false;
}

/**
 * Find the geographical center between two points.
 * It's just averages them and returns the average.
 * Useful for Google Maps!
 *
 * @return string.
 * @param string
**/
function FindCenter($lat1, $lon1, $lat2, $lon2)
{
	//	Useful for Google Maps
	$lat_avg = $lat1+$lat2;
	$lat_avg = $lat_avg/2;
	
	$lon_avg = $lon1+$lon2;
	$lon_avg = $lon_avg/2;
	
	return $lat_avg . ',' . $lon_avg;
}

/**
 * Turn all @ links (@username here) into links relative to the site url
 * 
 * A text with @username would turn into domain.com/username, with domain.com
 * being the site_url()
 * 
 * @return string
 * @param string $text
 */
function linkify($text)
{
    $text = preg_replace("/@(\w+)/", '<a href="'.site_url('people').'/$1">@$1</a>', $text);
    return $text;
}

/**
 * Bold a string
 * 
 * @return     string
 * @param      string
 */
function bold($text = '')
{
	echo '<strong>'.$text.'</strong>';
}

/**
 * Testing if we are running Internet Explorer
 * 
 * @return     bool
 * @param      void
**/
function is_ie()
{
    if (isset($_SERVER['HTTP_USER_AGENT']) AND (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false))
        return TRUE;
    else
        return FALSE;
}

/**
 * Load a view file
 * 
 * @category Sys_core
 * @return void
 * @param string $what What view the load
 * @param array|void $second The parameters to pass.
**/
function load_view($what, $second = FALSE)
{
	get_instance()->load->view($what, $second = FALSE);
}

/**
 * Expand all shortend URLs
 *
 * @param string The URL to expand
 * @return string The expanded URL or the original URL if already expanded
**/
function expandShortUrl($url)
{
     $url = prep_url($url);
     
     $headers = get_headers($url, 1);
     if (isset($headers['Location']))
          return $headers['Location'];
     else
          return $url;
}


/**
 * Remove numbers from a string
 *
 * @param string
 * @return string
**/
function remove_numbers($str = '')
{
	return str_replace(array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'), '', $str);
}

/**
 * Display the current form key
 *
 * @return     bool
 * @deprecated
**/
function e_form_key()
{
	$CI =& get_instance();
	echo $CI->Sys_core->get_form_key();
}

/**
 * Compare a key with the user's form key
 * 
 * @return     bool
 * @deprecated
**/
function compare_form_keys($input)
{
    $CI =& get_instance();
    
    return $CI->Sys_core->compare_form_keys($input);
}

/**
 * Merge user defined arguments into defaults array.
 *
 * This function is used throughout WordPress to allow for both string or array
 * to be merged into another array.
 *
 *
 * @param string|array $args Value to merge with $defaults
 * @param array $defaults Array that serves as the defaults.
 * @return array Merged user defined values with defaults.
 * @category WordPress
 */
function wp_parse_args($args, $defaults = '') {
	if (is_object($args))
		$r = get_object_vars($args);
	elseif (is_array($args))
		$r =& $args;
	else
		wp_parse_str($args, $r);

	if (is_array($defaults))
		return array_merge($defaults, $r);
	return $r;
}

/**
 * So we don't have to repeat ourselves dozens of times
 * 
 * @return string
 * @param string The URL to redirect them back to after they are logged in
 * @access public
 * @category Sys_core
**/
function login_url($redirect = '')
{
    $url = site_url('login');
    
    //  Quick and easier than having a two function stack, just throw 'current' as the
    //  redirect url and it will set it to the current url.
    if ($redirect === 'current')
        $redirect = current_url();
    
    
    if ($redirect === '')
        return $url;
    
    //  We need to setup a redirect
    return $url . '?redirect=' . urlencode($redirect);
}

/**
 * Set the current good message
 *
 * @access public
 * @param string
**/
function set_good($what)
{
	return get_instance()->Connect->set_good($what);
}

/**
 * Set the current error message
 *
 * @param string
 * @access public
**/
function set_error($what)
{
	return get_instance()->Connect->set_error($what);
}

/**
 * Get the current user ID
 *
 * @return bool|int
**/
function current_uid()
{
	if (! logged_in())
		return FALSE;
	
	return get_instance()->Users->uid();
}


/**
 * A simple error handler
 *
 * @access public
 * @return object
**/
function simple_error($error)
{
	$obj = new stdClass();
	$obj->simple_error = $error;
	return $obj;
}

/**
 * Detect if a variable is an simple_error object
 *
 * @return bool
**/
function is_simple_error($obj)
{
	if (! is_object($obj))
		return FALSE;
	
	if (! property_exists($obj, 'simple_error'))
		return FALSE;
	
	return TRUE;
}

/**
 * Get the error from a `simple_error`
 *
 * @return string|bool
**/
function get_simple_error($obj)
{
	if (! is_simple_error($obj))
		return FALSE;
	
	return $obj->simple_error;
}

/**
 * A simple way to pageinate and generate an offset based upon a page
 *
 * @access public
 * @param int $page The current page numer (default is 1)
 * @param int $limit The amount per page (default is 20)
 * @return int The offset
**/
function simple_page($page = 1, $limit = 20)
{
	$page = (int) $page;
	$limit = (int) $limit;
	
	// To small of a limit, or an invalid one!
	if ($limit < 1)
		$limit = 20;
	
	// We don't want a page < 1
	if ($page < 1)
		$page = 1;
	
	$page = $page-1;
	$offset = $page*$limit;
	
	return $offset;
}

/**
 * Get a post variable or get the default
 *
**/
function fallback_post($var, $default = '')
{
	if (! isset($_POST[$var]))
		return $default;
	else
		return $_POST[$var];
}

/**
 * We protect an email in Javascript
 *
 * @access public
 * @return string
 * @param string The email to hide!
 * @link http://www.maurits.vdschee.nl/php_hide_email/
**/
function hide_email($email)
{
	$character_set = '+-.0123456789@ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz';
	$key = str_shuffle($character_set); $cipher_text = ''; $id = 'e'.rand(1,999999999);
	for ($i=0;$i<strlen($email);$i+=1) $cipher_text.= $key[strpos($character_set,$email[$i])];
	$script = 'var a="'.$key.'";var b=a.split("").sort().join("");var c="'.$cipher_text.'";var d="";';
	$script.= 'for(var e=0;e<c.length;e++)d+=b.charAt(a.indexOf(c.charAt(e)));';
	$script.= 'document.getElementById("'.$id.'").innerHTML="<a href=\\"mailto:"+d+"\\">"+d+"</a>"';
	$script = "eval(\"".str_replace(array("\\",'"'),array("\\\\",'\"'), $script)."\")"; 
	$script = '<script type="text/javascript">/*<![CDATA[*/'.$script.'/*]]>*/</script>';
	return '<span id="'.$id.'">[javascript protected email address]</span>'.$script;
}

/**
 * Get the url to a resized image
 *
 * @access public
 * @return string
**/
function resize_image($src, $w = NULL, $h = NULL, $zc = NULL, $q = NULL)
{
	// Shhhh!
	$internal_secret = 'paloalto-tint-2011';
	$hash = md5($internal_secret.'-'.$src);
	
	
	$url = site_url('resize');
	$url = $url.'?src='.$src.'&hash='.$hash;
	
	if (! is_null($w))
		$url = $url.'&w='.$w;
	
	if (! is_null($h))
		$url = $url.'&h='.$h;
	
	
	if (! is_null($zc))
		$url = $url.'&zc='.$zc;
	
	
	if (! is_null($q))
		$url = $url.'&q='.$q;
	
	return $url;
}

/**
 * Generate a RSS URL for the current URL
 *
 * @return     string
**/
function generate_rss_url( $url = FALSE )
{
     if (! $url)
          $url = site_url($_SERVER['REQUEST_URI']);
     
     $explode = explode('?', $url, 2);
     
     if (count($explode) == 1)
          return $explode[0].'?rss=true';
     else
          return $explode[0].'?'.$explode[1].'&rss=true';
}

/**
 * Determine if the URL is looking for a RSS Feed
 *
 * @return     bool
**/
function is_rss()
{
     return (isset($_GET['rss']) AND $_GET['rss'] == 'true') ? TRUE : FALSE;
}

/**
 * Simple function to wrap something in a HTML element
 *
 * @access     public
 * @return     string
 * @param      string The element name (div, block, article, etc)
 * @param      string The string to wrap
 * @param      string Attributes to add to the HTML element (title, style, etc)
 * @param      bool Should we return or echo it out (defaults to return)
**/
function wrap_str($element, $string, $attr = '', $echo = FALSE)
{
     $element = (string) $element;
     $string = (string) $string;
     $attr = (string) $attr;
     $echo = (bool) $echo;
     
     // Creating the string
     $return = '<'.$element.' '.$attr.'>'.$string.'</'.$element.'>';
     
     // Echo it if they want, we'll still return it regardless
     if ($echo)
          echo $return;
     
     return $return;
}

/**
 * Linkify usernames in content
 * We want to link to user's on Connect
 *
 * @param      string
 * @return     string
**/
function link_names($string)
{
     $pattern = '/@([a-zA-Z0-9_]+)/';
     $replace = '<a href="'.site_url().'people/\1">@\1</a>';
     $new_string = preg_replace($pattern, $replace, $string);
     
     return $new_string;
}
/* End of file core_helper.php */