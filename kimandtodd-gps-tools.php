<?php
/*
Plugin Name: Kim and Todd's GPS tools
Plugin URI: http://www.kimandtodd.com
Description: Processes GPX data and matches it to photo timestamps to attempt to determine a location for each.
Version: 0.3
Author: Todd Rowan
Author URI: http://www.kimandtodd.com
License: GPL2
*/

if (IS_TEST !== true)
    register_activation_hook( __FILE__, 'tr_gps_install' );

include "uploads.php";
include "admin-tools.php";
include "exif-tools.php";

//global $tr_gps_db_version;
define('TR_GPS_DB_VERSION',"1.0");
define('GPX_RECORDS', "tr_gpx");
define('GPX_ASSIGNED_RECORDS',"tr_assigned_gpx_records");

// What if I want to delete a user created value? I'll have to pass in the
// user id and the datetime. I'm going to assume I'll only be deleting values
// where I already know the entire value set.

// Will I ever want to update a record? I can't think of a reason why. 

// I guess the thing would be that a user could create a bunch of records
// that would get orphaned. Then we'd have a lot of data and it would be hard
// to figure out which ones were currently in use. Need to think about this. 
// Maybe use cron to delete all user created records that are past a certain date
// AND that aren't currently being used by an attachment.  

// Master create function when being passed the src id and type.
// Only used when creating a single record. Use the batch option if 
// you have to insert thousands of records. 
function create_gpx_record($post_id, $src_type, $lat, $long, $user_id, $when = null)
{
    global $wpdb;
    $table = $wpdb->prefix . GPX_RECORDS;
   
    $wpdb->insert($table, 
            array(
                   'created_time'=>($when == null?current_time('mysql', 1):$when),
                   'src_type'=>$src_type,
                   'post_id'=>$post_id,
                   'user_id'=>$user_id,
                   'latitude'=>$lat,
                   'longitude'=>$long
                 ), 
            array('%s','%s','%d','%f','%f'));
    
    return $wpdb->insert_id;
}

function create_gpx_file_record_batch($values)
{
    global $wpdb;
    $table = $wpdb->prefix . GPX_RECORDS;
    $sql = "INSERT INTO `$table` (`created_time`, `src_type`, `post_id`, `user_id`, `latitude`, `longitude`) VALUES ";
    $sql .= implode(",",$values);
    
    $wpdb->query($sql);  // Maybe add a sql prepare statement here?
}

// Create a record from the exif in an image file.
function create_gpx_record_from_exif($attachment_id, $lat, $long, $time)
{
    return create_gpx_record($attachment_id, "exif", $lat, $long, get_current_user_id(), $time);
}
// Create a record from a gpx file.
function create_gpx_record_from_gpx($attachment_id, $lat, $long, $time)
{
    return create_gpx_record($attachment_id, "gpx", $lat, $long, get_current_user_id(),$time);
}

// Create a record from a user clicking the map.
function create_gpx_record_from_user($attachment_id, $lat, $long, $user_id, $time=null)
{
    // Only one user record per attachment. If we create a new one, get rid of the existing one.
    delete_user_gpx_records_by_attachment_id($attachment_id);
    return create_gpx_record($attachment_id, "user", $lat, $long, $user_id, $time);
}

function delete_user_gpx_records_by_attachment_id($att_id)
{
    global $wpdb;
    $sql = "DELETE FROM " . $wpdb->prefix . GPX_RECORDS . " WHERE `src_type`='user' AND `post_id`=$att_id";
    $wpdb->query($sql);
    // do some post meta stuff here?
    // what about foreign key refs?
}

function delete_all_gpx_records_by_attachment_id($att_id)
{
    global $wpdb;
    $sql = "DELETE FROM " . $wpdb->prefix . GPX_RECORDS . " WHERE `post_id`=$att_id";
    $wpdb->query($sql);
    // do some post meta stuff here?
    // what about foreign key refs?
}

function assign_gpx_to_attachment($post_id, $gpx_id_and_type)
{
    global $wpdb;
    // First check to see if we are even changing anything?
    // prob oughta create hidden value of existing gpx setting (if one exists).
    
    // clear out any old assignment
    delete_gpx_from_attachment($post_id);
    
    // If they send in 'none' they are removing any assignment,
    // so lets just get out. 
    if ($gpx_id_and_type[1]=="none")
        return;
    
    $table = $wpdb->prefix . GPX_ASSIGNED_RECORDS;
    
    $wpdb->insert($table, 
            array(
                   'post_id'=>$post_id,
                   'assigned_gpx_id'=>intval($gpx_id_and_type[1])
                 ), 
            array('%d','%d', '%s'));        
}

function delete_user_gpx_by_attachment_id($post_id)
{
    global $wpdb;
    $sql = "DELETE FROM " . $wpdb->prefix . GPX_RECORDS . " WHERE `post_id`=$post_id";
    $wpdb->query($sql);
}

function delete_gpx_from_attachment($post_id)
{
    global $wpdb;
    $sql = "DELETE FROM " . $wpdb->prefix . GPX_ASSIGNED_RECORDS . " WHERE `post_id`=$post_id";
    $wpdb->query($sql);
}

function get_exif_gpx_records_for_attachment($att_id)
{
    global $wpdb;
    
    $sql = "SELECT * FROM " . $wpdb->prefix . GPX_RECORDS . 
        " WHERE `post_id`=$att_id AND `src_type`='exif'";
    
    $result = $wpdb->get_results($sql, ARRAY_A);
    
    return $result;
}

function get_user_gpx_records_for_attachment($att_id)
{
    global $wpdb;
    
    $sql = "SELECT * FROM " . $wpdb->prefix . GPX_RECORDS . 
        " WHERE `post_id`=$att_id AND `src_type`=\"user\"";
    
    return $wpdb->get_results($sql, ARRAY_A);    
}

function get_assigned_gpx_for_attachment($att_id)
{
    global $wpdb;
    
    $sql = "SELECT a.`post_id`, b.`src_type`, a.`assigned_gpx_id` FROM " . $wpdb->prefix . GPX_ASSIGNED_RECORDS . 
        " a " .
        "INNER JOIN " . $wpdb->prefix . GPX_RECORDS . " b ON a.`assigned_gpx_id` = b.`id` " .
        "WHERE a.`post_id`=$att_id ";    
    
    return $wpdb->get_row($sql,ARRAY_A);    
}

function get_coords_for_gpx($gpx_id)
{
    global $wpdb;
    
    $sql = "SELECT `latitude`, `longitude` FROM " . $wpdb->prefix . GPX_RECORDS . 
        " WHERE `id`=$gpx_id "; 
    
    return $wpdb->get_row($sql,ARRAY_A); 
}

function get_coords_for_attachment($att_id)
{
    $coords=get_assigned_gpx_for_attachment($att_id);
    
    if (is_null($coords))
    {
        return null;
    }
    else
    {
        return get_coords_for_gpx($coords['assigned_gpx_id']);
    }
}

function tr_gps_install() {
   global $wpdb;
   require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
      
  $sqlGpx = "CREATE TABLE `" . $wpdb->prefix . GPX_RECORDS . "` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `src_type` enum('exif','gpx', 'user') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `post_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_time` datetime NOT NULL,
  `latitude` decimal(9,6) NOT NULL,
  `longitude` decimal(9,6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";

  dbDelta($sqlGpx);
 
  $sqlGpxAssigned ="CREATE TABLE `" . $wpdb->prefix . GPX_ASSIGNED_RECORDS . "` (
  `post_id` bigint(20) unsigned NOT NULL,
  `assigned_gpx_id` bigint(20) unsigned NOT NULL,
  UNIQUE KEY `post_id` (`post_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
  
  dbDelta($sqlGpxAssigned);
 
   add_option( "tr_gps_db_version", TR_GPS_DB_VERSION );
}

function tr_gps_drop_tables()
{
    global $wpdb;
    $tableAll = $wpdb->prefix.GPX_RECORDS;
    $tableAssigned = $wpdb->prefix.GPX_ASSIGNED_RECORDS;

    // Need to delete the tables in reverse order, as dependencies exist.
    $wpdb->query("DROP TABLE IF EXISTS $tableAssigned");
    $wpdb->query("DROP TABLE IF EXISTS $tableAll");
    //Delete any options thats stored, too.    
    delete_option( "tr_gps_db_version", TR_GPS_DB_VERSION );
}

// Does batches because doing them one at a time is really freakin' slow.
function process_gpx_file($fname, $attachment_id, $limit=40)
{
    $z = new XMLReader();
    $z->open($fname);
    $counter = 0;
    $vals = array();
    $u_id = get_current_user_id();
    
    while($z->read())
    {
        if ($z->name == "trkpt" && $z->nodeType != XMLReader::END_ELEMENT)
        {
            $lat = $z->getAttribute('lat');
            $long = $z->getAttribute('lon');
            $time = "";
            while($z->read())
            {
                if ($z->name == "time")
                {
                    while ($z->read())
                    {
                        $n = $z->nodeType;
                        if ($n == XMLReader::TEXT)
                        {
                            $time = $z->value;                   
                            break 2;
                        }
                    }                    
                }
            }
            // Do the insert here:
            $vals[]="('".convert_gpx_date_to_mysql($time)."','gpx',$attachment_id,$u_id,$lat,$long)";
            if (++$counter % $limit == 0)
            {
                create_gpx_file_record_batch($vals);
                $vals = array();
            }
        }
    }
    
    if (count($vals)>0)
        create_gpx_file_record_batch($vals);
    
    return $counter;
}

function convert_gpx_date_to_mysql($d)
{
    $informat = "Y-m-d?H:i:sT";
    $outformat = "Y-m-d H:i:s";
    return date_format(date_create_from_format($informat, $d), $outformat);
}

function get_min_max_gpx_times($attachment_id)
{
    global $wpdb;
    $sql = "SELECT MIN( `created_time` ) AS minDate, MAX(`created_time`) AS maxDate FROM " . $wpdb->prefix . GPX_RECORDS;
    $sql .= " WHERE `post_id`=$attachment_id AND `src_type`='gpx'";
    
    $res = $wpdb->get_row($sql);
    if (isset($res->minDate))
    {
        return array("min"=>$res->minDate, "max"=>$res->maxDate);
    }
    else 
    {        
        return array();
    }
}

 // This should then return the IDs of one or more gpx files 
 // from which we can read location points. 
function get_gpx_by_within_range($exif_time_stamp)
{
    global $wpdb;
    
    $sql = "SELECT a.post_id FROM (
        SELECT `post_id`, MIN( `created_time` ) AS minDate, MAX(`created_time`) AS maxDate FROM " 
        . $wpdb->prefix . GPX_RECORDS . 
        " WHERE `src_type`='gpx' 
        GROUP BY `post_id` ) AS a 
        WHERE '$exif_time_stamp' BETWEEN minDate AND maxDate";
    
    $results = $res = $wpdb->get_results($sql);
    
    if ($wpdb->num_rows > 0)
        return $results;
    else
        return null;   
}

// Returns an ordered list of the location records that are closest to the 
// date and time that you've submitted.
// $attachment_id = the id of the gpx file we're searching. 
// $time - The mysql date/time we're trying to match
// $rec_limit - How many gpx records to return. Default is 5
// $sec_limit - Within how much time on either side of our time are we willing to look for a match?
// Default there is 30 min, so within an hour. 
function get_closest_values($attachment_id, $time, $rec_limit = 5, $sec_limit=1800)
{
    global $wpdb;
    
    $sql = "SELECT g.`id`, g.`created_time`, g.`latitude`, g.`longitude`,";
    $sql .= " ABS( TIMEDIFF('$time', g.`created_time` ) ) AS `diff` ";
    $sql .= " FROM " . $wpdb->prefix . GPX_RECORDS . " g ";  
    $sql .= " WHERE `post_id`=$attachment_id AND `src_type`='gpx'";
    $sql .= " AND ABS( TIMEDIFF('$time', g.`created_time` ) )<$sec_limit";
    $sql .= " ORDER BY `diff` LIMIT $rec_limit";
    
    $res = $wpdb->get_results($sql);
    if ($wpdb->num_rows > 0)
    {
        return $res;
    }
    else 
    {        
        return array();
    }
}

function add_maps_on_single()
{
    if (is_single() && !is_admin())
    {
        wp_enqueue_script("kandtmapssingle");
    }
}

// let's register some scripts:
$gmapsUrl = "https://maps.googleapis.com/maps/api/js?key=AIzaSyA3yDvin7u69cVme-npZwy7aZHTPeNAk5Q&sensor=false";
$kandtadminscript = plugins_url("js/kandtmapsadmin.js", __FILE__);
$kandtsinglescript = plugins_url("js/kandtmaps.js", __FILE__);
$kandtclusterer = plugins_url("js/markerclusterer_packed.js", __FILE__);
wp_register_script("gmaps", $gmapsUrl);
wp_register_script("markerclusterer", $kandtclusterer, array("gmaps"));
wp_register_script('kandtmapsadmin', $kandtadminscript, array("gmaps", "jquery"));
wp_register_script('kandtmapssingle', $kandtsinglescript, array("jquery","markerclusterer"), '0_2');

add_action( 'wp_enqueue_scripts', 'add_maps_on_single' );

?>
