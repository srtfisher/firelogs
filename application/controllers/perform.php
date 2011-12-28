<?php
/**
 * Perform Crons
 *
 * This calls on actions, this does not do anything special.
 *
 * @package    Core
 * @author     srtfisher
 * @since      0.1
**/
class Perform extends CI_Controller
{
     /**
      * Constructor
      *
      * @access     public
     **/
     public function __construct()
     {
          parent::__construct();
          
          // Ignore it if it's aborted by the client
          ignore_user_abort(true);
     }
     
     /**
      * Five Minute Cron
      *
      * @access     public
      * @see        do_action() Called by `five_minute_cron`
     **/
     public function five_minute()
     {
          do_action('five_minute_cron');
     }
     
     /**
      * Fifteen Minute Query
      *
      * @access     public
      * @param      do_action() Called by `fifteen_minute_cron`
     **/
     public function fifteen_minute()
     {
          do_action('fifteen_minute_cron');
     }
     
     /**
      * Hourly Cron
      *
      * @access     public
      * @see        do_action() Called by `hour_cron`
     **/
     public function hour()
     {
          do_action('hour_cron');
     }
     
     /**
      * Nightly Cron
      *
      * @access     public
      * @see        do_action() Called by `nightly_cron`
     **/
     public function nightly()
     {
          do_action('nightly_cron');
     }
}
/* End of file perform.php */