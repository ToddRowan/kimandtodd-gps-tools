<?php

// See http://net.tutsplus.com/tutorials/wordpress/creating-custom-fields-for-attachments-in-wordpress/
// for more info on using the html option for the input type. This will let us build whatever we want. 
// open the template in the editor.

define ('USER_SET_CREATE_TIME', 'photo_user_create_time');
define ('EXIF_CREATE_TIME_FIELD' , 'kandt-photo-exif-time');
define ('USER_CREATE_TIME_FIELD' , 'kandt-photo-user-time');
define ('KANDT_GMAPS', 'kandt-location-map');
define ('USER_SELECTED_GPX_LOCATION' , 'kandt-gpx-entry');
define ('USER_SELECTED_GPX_LOCATION_VALUE' , 'kandt-gpx-entry-id');
define ('ACTIVE_GPX_LOCATION', 'kandt-gpx-existing');

add_filter('attachment_fields_to_edit', 'kandt_attachment_fields', 10, 2);
add_action('edit_attachment', 'kandt_save_attachment_fields',10,2);

function kandt_attachment_fields($form_fields, $post)
{
    // If this isn't an image, don't bother. 
    if (strpos($post->post_mime_type,'image/')!==0)
        return $form_fields;
    wp_enqueue_script("kandtmapsadmin");
    wp_localize_script("kandtmapsadmin", 'blogUrl',array('plugInUrl'=> plugins_url("", __FILE__)));
    $photo_exif_time = get_post_meta($post->ID, EXIF_CREATE_TIME, true);
    
    $form_fields[EXIF_CREATE_TIME_FIELD] = array(
		'label' => 'EXIF creation time',
		'input' => 'html',
		'html' => "<p>".($photo_exif_time==""?"No time set in the file.":$photo_exif_time)."</p>",
		'helps' => 'The time read from the file.',
	);
    
    $photo_user_time = get_post_meta($post->ID, USER_SET_CREATE_TIME, true);
    
    $form_fields[USER_CREATE_TIME_FIELD] = array(
		'label' => 'User set creation time',
		'input' => 'text',
		'value' => $photo_user_time,
		'helps' => 'The time set by a user.',
	);
    
   $possible_times = "<p>No time-related data available for gps matching.</p>";
    
   $search_time = ($photo_user_time!=""?$photo_user_time:$photo_exif_time);
   $gpx_values_by_time = NULL;
   if ($search_time != "")
   {
       $gpx_files = get_gpx_by_within_range($search_time);
       
       if (!is_null($gpx_files))
       {
           $att_id = $gpx_files[0]->post_id;
           $gpx_values_by_time = get_closest_values($att_id, $search_time);
       }
   }     
   
   $photo_exif_gpx_array = get_exif_gpx_records_for_attachment($post->ID);
   $photo_user_gpx_value = get_user_gpx_records_for_attachment($post->ID);
   $assigned_gpx_id_array = get_assigned_gpx_for_attachment($post->ID);
   
   if (is_array($assigned_gpx_id_array))
   {
       $form_fields[ACTIVE_GPX_LOCATION] = array(
       'input' => 'hidden',
       'value' => $assigned_gpx_id_array['src_type']."-".$assigned_gpx_id_array['assigned_gpx_id']
   );
   }
   
   $customLocationText = "";
   if (count($photo_exif_gpx_array)==0)
           $customLocationText = '<div id="picmap-add">Double-click a location in the map to create a new GPS location for the photo.</div>';
           

   $form_fields[KANDT_GMAPS] = array(
       'label' => 'Map',
       'input' => 'html',
       'html' => '<div id="searchmap"><span>Type a location to search on the map:</span>'
       . '<input type="text" id="geocodesearch" style="width:340px"></input><input id="dogeocodesearch" type="button" value="Search"></input></div><div id="picmap" style="width:600px;height:400px;"></div>'.$customLocationText
   );

   kandt_make_gps_select_buttons($form_fields,
           $assigned_gpx_id_array,
           $gpx_values_by_time,
           count($photo_exif_gpx_array)>0?$photo_exif_gpx_array[0]:null,
           $photo_user_gpx_value);

   return $form_fields;
}

// Read what the user set and store it away.
function kandt_save_attachment_fields($post_id)
{
    // Store the user time
    $new_user_time = null;
    if (isset($_POST['attachments'][$post_id][USER_CREATE_TIME_FIELD]))
        $new_user_time = $_POST['attachments'][$post_id][USER_CREATE_TIME_FIELD];
    
    if(!is_null($new_user_time) && $new_user_time!="")
    {
        update_post_meta($post_id, USER_SET_CREATE_TIME, $new_user_time);
    }
    else
    {
        delete_post_meta($post_id, USER_SET_CREATE_TIME);
    }
    
    $new_gpx_data = null;
    $old_gpx_data = 'none';
    if (isset($_POST['attachments'][$post_id][ACTIVE_GPX_LOCATION]))
        $old_gpx_data = $_POST['attachments'][$post_id][ACTIVE_GPX_LOCATION];
    
    $incoming_gpx_id = isset($_POST[USER_SELECTED_GPX_LOCATION_VALUE]);
    if ($incoming_gpx_id)
        $new_gpx_data = $_POST[USER_SELECTED_GPX_LOCATION_VALUE];
    
    if($new_gpx_data!=$old_gpx_data)
    {                
        if(strpos($new_gpx_data, 'user-new')===0)
        {
            delete_user_gpx_by_attachment_id($post_id);
            $current_user = wp_get_current_user();
            $latLng = explode("|", $new_gpx_data);
            $new_id = create_gpx_record_from_user($post_id, $latLng[1], $latLng[2], $current_user->ID);
            $new_gpx_data = 'user-'.$new_id;
        }        
        assign_gpx_to_attachment($post_id, explode("-", $new_gpx_data));
    }    
}

function kandt_make_gps_select_buttons(&$form_fields,
        $assigned_gpx_id_array,
        $vals_from_time = array(),
        $val_from_pic = array(),
        $val_from_user = array()) 
{
    // Structure is 
    // List from time
    // Val from user
    // None
    // OR
    // Val from pic
    // None
    // OR
    // Val from user
    // None
    $possible_times = "";
    $select_none = true;
    
    if (count($val_from_pic)>0)
    {
        $selected_gpx_id = -1;        
        // If there's gps embedded in the picture, just show that option and None.
        if (is_array($assigned_gpx_id_array) &&
                $assigned_gpx_id_array['src_type']=='exif')
        {
            $selected_gpx_id = $assigned_gpx_id_array['assigned_gpx_id'];  
            $select_none = false;
        }
        $possible_times .= "<input type=\"radio\" class=\"gpx-radio\" name=\""
                        . USER_SELECTED_GPX_LOCATION_VALUE 
                        . "\" value=\"exif-". $val_from_pic['id'] ."\" data-lat=\"" 
                        . $val_from_pic['latitude']
                        . "\" data-long=\"" 
                        . $val_from_pic['longitude']
                        . "\" " 
                        . ($selected_gpx_id==$val_from_pic['id']?"checked":"") 
                        . " id=\"gpx-id-exif" . $val_from_pic['id'] ."\"/>" 
                        . "<label id=\"gpx-label-exif-" . $val_from_pic['id'] . "\" for=\"gpx-id-exif" 
                        . $val_from_pic['id'] . "\" class=\"gpx-label\"> GPS data embedded in photo</label>"
                        . "<br>";
    }
    else if (count($vals_from_time)>0)
    {
        $selected_gpx_id = -1; 
        // If no exif gps value but some time-related values, output the time-related ones.
        if (is_array($assigned_gpx_id_array) &&
                $assigned_gpx_id_array['src_type']=='gpx')
        {
            $selected_gpx_id = $assigned_gpx_id_array['assigned_gpx_id'];   
            $select_none = false;
        }
        // build out the standard set
        if (!is_null($vals_from_time) && count($vals_from_time)>0)
        {            
            foreach ($vals_from_time as $val)
            {
                $possible_times .= "\n<input type=\"radio\" class=\"gpx-radio\" name=\""
                        . USER_SELECTED_GPX_LOCATION_VALUE 
                        . "\" value=\"gpx-" . $val->id 
                        . "\" data-lat=\"" 
                        . $val->latitude 
                        . "\" data-long=\"" 
                        . $val->longitude 
                        . "\" " 
                        . ($selected_gpx_id==$val->id?"checked":"") 
                        . " id=\"gpx-id-gpx-" . $val->id ."\"/>" 
                        . "<label id=\"gpx-label-gpx-" . $val->id ."\" for=\"gpx-id-gpx-" 
                        . $val->id . "\" class=\"gpx-label\"> " . $val->created_time ."</label>"
                        . "<br>";
            }
        }
    }
    if (count($val_from_pic)==0 && count($val_from_user)>0)
    {  
        // Add the user-created ones
        if (is_array($assigned_gpx_id_array) &&
                $assigned_gpx_id_array['src_type']=='user')
        {
            $selected_gpx_id = $assigned_gpx_id_array['assigned_gpx_id'];
            $select_none = false;
        }            
        $possible_times .= outputUserSelectedValue($val_from_user, $selected_gpx_id);
    }
    else if (count($val_from_pic)==0)
    {
        $possible_times .= "<p><p id=\"userCreatePlaceholder\">No user-created locations available.</p></p>";
    }   
    
    // Always output None.
    // START HERE: Need to fix this in such a way as to not select NONE when another is selected
    $possible_times .= "<br><input type=\"radio\" name=\"". USER_SELECTED_GPX_LOCATION_VALUE 
                    . "\" value=\"none-none\" id=\"gpx-none\" " 
                    . "class=\"gpx-radio\" " . ($select_none==true?"checked":"") . ">"
                    . "<label class=\"gpx-label\" for=\"gpx-none\" id=\"gpx-label-none\">"
                    . " None</label><br>";
    
    $form_fields[USER_SELECTED_GPX_LOCATION] = array(
                'label' => 'Location points',
		'input' => 'html',
		'html' => "$possible_times"
	);
}

function outputUserSelectedValue($val_from_user, $selected_gpx_id)
{
    return "<input id=\"gpx-id-user\" type=\"radio\" class=\"gpx-radio\" "
                    . "name=\"" . USER_SELECTED_GPX_LOCATION_VALUE 
                    . "\" value=\"user-" . $val_from_user[0]['id']
                    . "\" data-lat=\"" . $val_from_user[0]['latitude']
                    . "\" data-long=\"" . $val_from_user[0]['longitude']
                    . "\" " . ($selected_gpx_id==$val_from_user[0]['id']?"checked":"")
                    . " id=\"gpx-id-user-" . $val_from_user[0]['id'] ."\"/>" 
                    . "<label id=\"gpx-label-user\" " 
                    . "for=\"gpx-id-user\" " 
                    . "class=\"gpx-label\"> User-selected location (" 
                    . $val_from_user[0]['latitude'] . ", " 
                    . $val_from_user[0]['longitude'] . ")</label>"
                    . "<br>";
}
?>
