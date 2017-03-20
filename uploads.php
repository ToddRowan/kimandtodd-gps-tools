<?php
define ('GPX_START', 'gpx_start_time');
define ('GPX_END', 'gpx_end_time');
define ('GPX_MIME_TYPE', 'application/xml');
define ('EXIF_CREATE_TIME', 'photo_exif_create_time');
define ('EXIF_GPS_VALUES', 'photo_exif_gps_data');

add_action('add_attachment', 'process_gpx');
add_action('add_attachment', 'process_jpeg');
add_action('delete_attachment', 'delete_all_gpx_records_by_attachment_id');
add_filter('upload_mimes', 'permit_gpx_upload');

function process_gpx($post_id)
{    
    $p = get_post($post_id);
    // figure out if the post is a gpx file
    if ($p->post_mime_type != GPX_MIME_TYPE)
        return;
    
    // if so, find the file and do the processing
    $file = get_attached_file($post_id);
    process_gpx_file($file, $post_id);    
    $high_low = get_min_max_gpx_times($post_id);
    update_post_meta($post_id, GPX_START, $high_low["min"]); 
    update_post_meta($post_id, GPX_END, $high_low["max"]); 
}

function permit_gpx_upload ( $existing_mimes=array() ) 
{
  // add your extension to the array
  $existing_mimes['gpx'] = GPX_MIME_TYPE;

  return $existing_mimes;
}

function process_jpeg($post_id)
{
    $p = get_post($post_id);
    // figure out if the post is a jpeg file
    if ($p->post_mime_type != 'image/jpeg')
        return;
    
    // if so, find the file and do the processing
    $file = get_attached_file($post_id);
    $gmtImageTime = getImgTimeMySql($file);
    $exifGps = getGoogleGpsValuesFromImg($file);
    if (!is_null($gmtImageTime))
    {
        update_post_meta($post_id, EXIF_CREATE_TIME, $gmtImageTime);
    }        
    if (count($exifGps)>0)
    {
        $u_id = get_current_user_id();
        create_gpx_record($post_id, "exif", $exifGps["lat"], $exifGps['long'], $u_id, $gmtImageTime);
        update_post_meta($post_id, EXIF_GPS_VALUES, $exifGps);
    }
}

?>
