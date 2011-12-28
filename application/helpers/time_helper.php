<?php
/**
 * Simple ways to handle time
 *
 * Time can be confusing in PHP, but we like making things simple.
 * We save all data in either a UNIX timestamp or a DATETIME timestamp, relative
 * to GMT.
 *
 * From there, we can convert it over to local time or whatever timezone you would like
 *
 * @package Connect
 * @author sean
 * @since 0.1
**/

/**
 * Convert a CI timezone to PHP
 *
 * @return string
 * @param string The CI Timezone
**/
function ci_zone_to_php_zone($ci_zone)
{
	$offset = timezones($ci_zone);
	return timezone_name_from_abbr('', $offset * 3600, false);
}

/**
 * Fixing the CI date helper
 *
 * Converts the GMT Unix timestamp to the local timezone based upon the $timezone param
 *
 * @return int
 * @param int $time The GMT Timestamp
 * @param string $timezone The CI Timezone
 * @param bool $dst Should we use DST.
**/
function gmt_to_local($time = '', $timezone = 'UTC', $dst = FALSE)
{                       
	if ($time == '')
	{
		return now();
	}
	$time += timezones($timezone) * 3600;
	
	// thx http://www.toosweettobesour.com/2009/03/10/calculating-daylight-savings-time-boundary-in-php/
	$dst_begin = strtotime('Second Sunday March 0');  
	$dst_end   = strtotime('First Sunday November 0');
	
	$dst = false;
	if ($time >= $dst_begin AND $time < $dst_end) $dst = true;
	
	if ($dst == TRUE)
	{
		$time += 3600;
	}
	
	return $time;
}

/**
 * Function will get the date format for the current user.
 *
 * @access public
 * @return date() format.
**/
function GetDateFormat( )
{
	// Just save it as a static variable because it's easier
	static $date_format = FALSE;
	
	//	Return the static variable.
	if ( $date_format !== FALSE )
		return $date_format;
	
	//	Get the Meta
	if ( !logged_in() )
		$format = get_instance()->Users->get_meta('date_format');
	else
		$format = FALSE;
	
	//	No user custom format.
	if (! $format )
		$format = "m.d.y";
	
	$date_format = $format;
	return $date_format;
}

/**
 * Function will get the time format for the current user.
 *
 * @access public
 * @return The current time format
**/
function GetTimeFormat( ) {
	static $time_format = FALSE;
	
	if ( $time_format !== FALSE )
		return $time_format;
	
	//	Logged in?
	if (! logged_in() )
		$format = FALSE;
	else
		$format = get_instance()->Users->get_meta('time_format');
	
	if (! $format )
		$format = "g:i a";
	
	$time_format = $format;
	return $time_format;
}

/**
 *	Get the current MySQL date time
 *	It will be in GMT!
 *
 *	@return string
 *	@since 1.0
 *	@category Time
**/
function current_datetime()
{
	return gmdate('Y-m-d H:i:s' );
}

/**
 * The the current timezone
 *
 * @since 3.6
**/
function get_current_timezone()
{
	if (! logged_in() )
		return 'UM5';
	
	$get = get_instance()->Users->get_meta('timezone');
	if ( ! $get OR $get === '' )
		return 'UM5';
	else
		return $get;
}

/**
 * Get the current timestamp for a GMT DATETIME
 *
 * We save all time references to MySQL in a GMT DATETIME
 * We want to display it in a local timezone and we want
 * the unix timestamp!
 *
 * @return int
 * @param string $date_time The DATETIME that should be made into a local UNIX TS
 * @param string|void $timezone The CI timezone to user (defaults to UM8) and it looks for the user's timezone!
 * @since 1.0
 * @category Time
**/
function get_local_unix( $date_time, $timezone = FALSE )
{
	//	Convert the DATETIME into a UNIX timestamp.
	//	This is an INT
	$unix_gmt = strtotime( $date_time );
	
	//	What timezone?
	if ( logged_in() AND ! $timezone )
	{
		$user_timezone = get_current_timezone();
		if ( $user_timezone !== '' AND $user_timezone !== NULL )
			$timezone = $user_timezone;
	}
	
	return gmt_to_local( $unix_gmt, $timezone );	
}

/**
 * Determine the time ago from a certain timestamp.
 *
 * @acess public
 * @param string The timestamp or unix timestamp relative to GMT
 * @return string
 * @from {http://forrst.com/posts/Formatting_Time_Difference_into_a_Phrase-sjF}
**/
function time_ago($timeStamp, $inSentence = TRUE)
{
	// We want to force it to be  UNIX timestamp.
	if (! is_numeric($timeStamp))
		$timeStamp = strtotime($timeStamp);
	
	// We don't want to display something from 1970 because of a bug with UNIX timestamp
	if ($timeStamp < 100)
		return 'Ancient History';
	
    // Let's get the time the moment this function is called
    $theTimeNow = now();

    // How many seconds it's been since the timestamp...
    $howLongSince = $theTimeNow - $timeStamp;

    // If it's a timestamp that hasn't occurred yet... i.e. future date
    if ($howLongSince < 0)
        return "In the future!";

   // Begin the conversion into phrases
	if ($howLongSince == 0)
	{
		$finalPhrase = "Now";
	} elseif ($howLongSince < 60)
	{
		$plural = ($howLongSince > 1) ? "s" : "";
		$finalPhrase = "" . $howLongSince . " second" . $plural . " ago";
	} elseif ($howLongSince < 3600)
	{
		// Less than an hour ago
		// How about minutes?
		$plural = (floor($howLongSince / 60) > 1) ? "s" : "";
		$mins = (floor($howLongSince / 60) == 1) ? "Just a" : floor($howLongSince / 60);
		$finalPhrase = "$mins minute" . $plural . " ago";
	} elseif (($howLongSince >= 3600) && ($howLongSince < 86400)) { // Less than a day ago
		/* ### How many hours since? ### */
		$plural = (floor($howLongSince / 3600) > 1) ? "s" : "";
		$finalPhrase = "About " . floor($howLongSince / 3600) . " hour" . $plural . " ago";
    } elseif (($howLongSince >= 86400) && ($howLongSince < 604800)) { // Less than a week ago
		// More than a day, say something like - Tuesday at 5:39PM
		$finalPhrase = date("l", $timeStamp) . " at " . date("g:iA", $timeStamp);
    } elseif (($howLongSince >= 604800) && ($howLongSince < 604800 * 52)) { // Less than a year ago
        // More than a week, say something like - January 2nd at 9:15AM
        $finalPhrase = date("F jS \@ g:i a", gmt_to_local($timeStamp));
    } elseif ($howLongSince >= 604800 * 52) { // More than a year ago
        $finalPhrase = "Over a year ago";
    }

    return ($inSentence) ? $finalPhrase : $finalPhrase;
}

/**
 * Calculate the age of somebody
 *
 * @access public
 * @param string Their birthday in the YYYY-MM-DD format
 * @return int The age in years
 * @link http://snippets.dzone.com/posts/show/1310
**/
function calculate_age($birthday)
{
	list($year,$month,$day) = explode("-",$birthday);
	
	$year_diff  = date("Y") - $year;
	$month_diff = date("m") - $month;
	$day_diff   = date("d") - $day;
	
	if ($day_diff < 0 || $month_diff < 0)
		$year_diff--;
	
	return $year_diff;
}
  
/* End of file time_helper.php */