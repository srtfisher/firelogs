<?php
/**
 * The Notification System
 * This is a fast and easy way to notify users of something.
 * Notifications are stored in the DB and heavily cached.
 *
 * This is used as a static class, so you call it via Notify::xxx(..)
 *
 * @package    Connect
 * @access     public
 * @since      0.2
**/
class Notify
{
     /**
      * The number of notifications to display on the front end
      *
      * @global int
     **/
     public static $frontend_limit = 6;
     
     /**
      * The Codeigniter Object
      *
      * @access     privat
     **/
     private static $CI;
     
     /**
      * The setup function
      * We save the Codeigniter object to the self::$CI variable
      *
      * @param      void
      * @return     void
     **/
     public static function init()
     {
          self::$CI = get_instance();
          
          add_action('js_footer', 'Notify::js_footer');
     }
     
     /**
      * Add a notification to a user
      *
      * @param      int $from Who the notification is from
      * @param      string $type The notification type
      * @param      string $url The notification detail URL
      * @param      mixed $data The notification data
      * @param      int $for Who the notification is for (defaults to current user)
      * @return     int The created notification ID
     **/
     public static function add_notification($from, $type, $url, $data = array(), $for = FALSE)
     {
          // Sanitize stuff
          if (! $for)
          {
               // Fallback to the current user
               if (! self::$CI->Users->logged_in() )
                    return FALSE;
               else
                    $for = self::$CI->Users->uid();
          }
          
          // The user it's directed for
          $for = (int) $for;
          
          self::$CI->db->set('user', $for);
          self::$CI->db->set('type', $type);
          self::$CI->db->set('data', maybe_serialize($data));
          self::$CI->db->set('url', $url);
          self::$CI->db->set('from', $from);
          
          self::$CI->db->insert('notifications');
          
          // Kill the cache
          self::$CI->cache->delete('notification-list-'.$for);
          self::$CI->cache->delete('notification-unread-'.$for);
          
          return self::$CI->db->insert_id();
     }
     
     /**
      * Get all the notifications for a user
      *
      * @param int The user ID
      * @return array
     **/
     public static function get_notifications($user = FALSE)
     {
          if (! $user)
          {
               // Fallback to the current user
               if (! self::$CI->Users->logged_in() )
                    return FALSE;
               else
                    $user = self::$CI->Users->uid();
          }
          
          return self::$CI->db->from('notifications')->where('user', $user)->order_by('id', 'desc')->get()->result();
     }
     
     /**
      * Get the cached notifications
      *
      * @access public
      * @return array
      * @param int Who's it for?
     **/
     public static function get_cached_notifications($for = FALSE)
     {
          if (! $for)
          {
               // Fallback to the current user
               if (! self::$CI->Users->logged_in() )
                    return FALSE;
               else
                    $for = self::$CI->Users->uid();
          }
          
          // Cached?
          $cached = self::$CI->cache->get('notification-list-'.$for);
          
          // Valid cache
          if (! is_bool($cached) AND is_array($cached))
               return $cached;
          
          $get = self::get_notifications($for);
          
          $i = 0;
          $notifications = array();
          foreach($get as $notification)
          {
               $i++;
               if ($i <= self::$frontend_limit)
                    $notifications[] = $notification;
          }
          
          // Cache it all
          self::$CI->cache->save('notification-list-'.$for, $notifications, 20);
          
          return $notifications;
     }
     
     /**
      * Mark the notifications as read
      *
      * @access public
     **/
     public static function mark_as_read($ID)
     {
          self::$CI->db->set('is_read', 1);
          self::$CI->db->where('id', $ID);
          self::$CI->db->update('notifications');
          
          self::$CI->cache->delete('notification_'.$ID);
          self::$CI->cache->delete('notification-unread-'.$ID);
          
          return TRUE;
     }
     
     /**
      * Mark as read for a user
      *
      * @access     public
      * @param      int
     **/
     public static function mark_read_for_user($user)
     {
          self::$CI->db->set('is_read', 1);
          self::$CI->db->where('user', $user);
          self::$CI->db->update('notifications');
          
          self::$CI->cache->delete('notification-list-'.$user);
          self::$CI->cache->delete('notification-unread-'.$user);
     }
     
     /**
      * Mark all the notifications that are on display on the fronted
      *
      * @access     public
     **/
     public static function mark_read_on_frontend()
     {
          $get = self::get_cached_notifications();
          if (count($get) == 0)
               return FALSE;
          
          foreach($get as $single)
               self::mark_as_read($single->id);
          
          self::$CI->cache->delete('notification-list-'.self::$CI->Users->uid());
          self::$CI->cache->delete('notification-unread-'.self::$CI->Users->uid());
     }
     
     /**
      * Get the unread post count
      *
      * @access     public
      * @param      int
      * @return     int
     **/
     public static function get_unread_count($for = FALSE)
     {
          if (! $for)
          {
               // Fallback to the current user
               if (! self::$CI->Users->logged_in() )
                    return FALSE;
               else
                    $for = self::$CI->Users->uid();
          }
          
          // Cached?
          $cached = self::$CI->cache->get('notification-unread-'.$for);
          
          if (! is_null($cached) AND is_int($cached))
               return $cached;
          
          $get = self::$CI->db->from('notifications')->where('user', $for)->where('is_read', 0)->get();
          
          self::$CI->cache->save('notification-unread-'.$for, $get->num_rows(), 30);
          return $get->num_rows();
     }
     
     /**
      * Checking if a post is read
      *
      * @return     bool
      * @param      int The notification (post) ID
      * @access     public
     **/
     public static function is_notification_read($post)
     {
          $cached = self::$CI->cache->get('notification_'.$post);
          
          if (is_object($cached))
               return ($cached->is_read) ? TRUE : FALSE;
          
          $from_db = self::$CI->db->from('notifications')->where('id', $post)->get();
          
          // Nothing found
          if ($from_db->num_rows() == 0)
               return FALSE;
          
          // Cache it
          self::$CI->cache->save('notification_'.$post, $from_db->row(), 20);
          
          return ($from_db->row()->is_read) ? FALSE : TRUE;
     }
     
     /**
      * The callback function for the JS in the footer to setup the notification system
      *
      * @access     private
      * @param      void
     **/
     public static function js_footer()
     {
          if ( ! self::$CI->Users->logged_in() OR ! can_do_feature('notification') )
               return;
          ?>
          $(document).ready(function () {
               Notify.ajaxURL      = '<?php echo site_url('ajax/notifications_callback'); ?>';
               Notify.ajaxReadURL  = '<?php echo site_url('ajax/notification_read'); ?>';
               
               Notify.setupCount(<?php echo self::get_unread_count(); ?>);
               Notify.setup();
          });
          <?php
     }
}
/* End of file Notify.php */