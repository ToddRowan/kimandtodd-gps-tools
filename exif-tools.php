<?php

function getImgTime($img)
{    
    // So the following is how wp reads an image
    // from an iPhone. Need to see how aperture does it
    // and how whatever tool I buy for the laptop does it.
    // This is local time, so we're going to need a strategy
    // where we match and don't have to set the time always.
    $exData = exif_read_data($img, "FILE");   
    if (isset($exData['DateTimeDigitized']))
        return $exData['DateTimeDigitized'];
    else
        return null;
}

function getImgTimeMySql($img)
{    
    // The exif time spec uses colons to separate Year-month-date.
    // Let's replace those with dashes and make the time mysql equivalent. 
    $exData = getImgTime($img);   
    if (!is_null($exData))
    {
        $informat = "Y:m:d H:i:s";
        $outformat = "Y-m-d H:i:s";
        return date_format(date_create_from_format($informat, $exData), $outformat);
    }
    else
        return null;   
}

function getGoogleGpsValuesFromImg($img)
{
    $exData = exif_read_data($img);
    
    $arr = array();
    if (isset($exData["GPSLongitude"]))
    {
        $arr["lat"] = getGoogleGpsValue($exData["GPSLatitude"], $exData["GPSLatitudeRef"]);
        $arr["long"] = getGoogleGpsValue($exData["GPSLongitude"], $exData["GPSLongitudeRef"]);
    }
    
    return $arr;
}

// I got most of the following from the second answer at
// http://stackoverflow.com/questions/2526304/php-extract-gps-exif-data
function getGoogleGpsValue($exifCoord, $hemi) 
{
    $degrees = count($exifCoord) > 0 ? gps2Num($exifCoord[0]) : 0;
    $minutes = count($exifCoord) > 1 ? gps2Num($exifCoord[1]) : 0;
    $seconds = count($exifCoord) > 2 ? gps2Num($exifCoord[2]) : 0;

    $flip = ($hemi == 'W' or $hemi == 'S') ? -1 : 1;

    return round($flip * ($degrees + $minutes / 60 + $seconds / 3600), 6);
}

function gps2Num($coordPart) {

    $parts = explode('/', $coordPart);

    if (count($parts) <= 0)
        return 0;

    if (count($parts) == 1)
        return $parts[0];

    return floatval($parts[0]) / floatval($parts[1]);
}
?>
