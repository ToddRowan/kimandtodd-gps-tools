var mapDataStore =
        {
            userMrkr: null,
            selectedMrkr: null,
            markers: {},
            map: null
        };

var geocoder = new google.maps.Geocoder();
var CURRENT_LOCATION = "Current location";
var AVAILABLE_LOCATION = "Available location";
function initialize() 
{
    var locations=getLocations();

    var mapOptions = getMapOptions(locations);
    mapDataStore.map = new google.maps.Map(document.getElementById("picmap"),mapOptions);
    
    var bounds = new google.maps.LatLngBounds();
            
    var locslength = locations.length;
    for (var inx=0; inx<locslength; inx++)
    {        
        var pos = new google.maps.LatLng(locations[inx].lat,locations[inx].long);
        
        //  Create a new viewpoint bound
        //  And increase the bounds to take this point
        bounds.extend(pos);       
        
        var mrkr;
        var customId = locations[inx].isUser?'user':locations[inx].value;        
        if (locations[inx].checked===true)
        {             
            mrkr = makeMarker(pos, mapDataStore.map,CURRENT_LOCATION, customId);
            mapDataStore.selectedMrkr = mrkr;
        }
        else
        {
            mrkr = makeCircle(pos, mapDataStore.map,AVAILABLE_LOCATION,customId);
        }
        addMarkerEventListeners(mrkr);
        mapDataStore.markers['gpx-label-'+customId] = mrkr;        
        if (locations[inx].isUser)
            mapDataStore.userMrkr = mrkr;
    }
    
    if (!bounds.isEmpty())
        mapDataStore.map.fitBounds(bounds);
    
    google.maps.event.addListener(mapDataStore.map,'dblclick', handleDblClick );
}

google.maps.event.addDomListener(window, 'load', initialize);

function getLocations()
{
   return  jQuery('input[name=kandt-gpx-entry-id]:radio').map(
           function()
            {
                if (jQuery(this).attr('value') !== 'none-none')
                    return [{'value':jQuery(this).attr('value'),
                             'lat':jQuery(this).attr('data-lat'),
                             'long':jQuery(this).attr('data-long'),
                             'isUser':jQuery(this).attr('id')==='gpx-id-user',
                             'checked':jQuery(this).is(':checked')}];
            }).get();
}
   
function getMarkerByAssocEl($el)
{
    var tag = $el.is('input')?'input':'label';
    var $idEl;
    if (tag==='input')
    {
        $idEl = $el.next('label');
    }
    else
    {
        $idEl = $el;
    }
    
    var id = $idEl.attr('id');
    return mapDataStore.markers[id]?mapDataStore.markers[id]:null;
}
      
function doMarkerMouseOver(mrkr)
{
    jQuery('#gpx-label-'+mrkr.customId).css('font-weight', 'bold');
}

function doMarkerMouseOut(mrkr)
{
    jQuery('#gpx-label-'+mrkr.customId).css('font-weight', 'normal');
}

function getMapOptions(locations)
{
    var mapOptions = {            
            mapTypeId: google.maps.MapTypeId.ROADMAP
        };
  
    // We only need to set zoom and center if there aren't any locations.
    // Otherwise we zoom in as close as possible but fit the markers. 
    if (locations.length === 0)
    {
        mapOptions.zoom = 1;
        mapOptions.center = new google.maps.LatLng(0,0); 
    }
     
    return mapOptions;
}

function handleDblClick(evt)
{
    setRadioButton(evt.latLng.lat().toFixed(6),evt.latLng.lng().toFixed(6));
}

function setRadioButton(lat, long)
{
    var $el = jQuery('#gpx-id-user');
    var pos = new google.maps.LatLng(lat,long);
    var customId = 'user';
    var mrkr;
                
    if ($el.length>0)
    {
        $el.attr({'data-lat': lat,'data-long': long,'value': 'user-new|'+lat+"|"+long});
        jQuery('#gpx-label-user').text(" User selected location (" + lat + ", " + long + ")");
        mapDataStore.userMrkr.setMap(null);       
        if ($el.is(':checked'))
        {
            mrkr = makeMarker(pos,mapDataStore.map,CURRENT_LOCATION,customId);
            mapDataStore.selectedMrkr=mrkr;
        }
        else
        {
            mrkr = makeCircle(pos,mapDataStore.map,AVAILABLE_LOCATION,customId);
        }
    }
    else
    {
        $el = jQuery('#userCreatePlaceholder');
        if ($el.length>0)
        {
            mrkr = makeCircle(pos,mapDataStore.map,AVAILABLE_LOCATION,customId);
            var x = getRadioButtonAndLabel(lat,long);
            addRadioHoverEventListeners(x);
            addRadioClickEventListeners(x);
            $el.replaceWith(x);
        }
    }
    
    addMarkerEventListeners(mrkr);
    mapDataStore.userMrkr = mrkr;
    mapDataStore.markers['gpx-label-'+customId] = mrkr;    
}

function getRadioButtonAndLabel(lat, long)
{
    var inputAtts = {'type':'radio',
                'name':'kandt-gpx-entry-id',
                'value':'user-new|'+lat+"|"+long,
                'id':'gpx-id-user',
                'data-lat': lat,
                'data-long': long};
    
    var labelAtts = {'id': "gpx-label-user",
                     'for':'gpx-id-user' };
         
    var y = jQuery('<input>').attr(inputAtts).addClass('gpx-radio');
         
    return [y[0],
            (jQuery('<label>').attr(labelAtts).addClass("gpx-label").
                    text(" User selected location (" + lat + ", " + long + ")"))[0]];
}

function makeMarker(pos, map, title, customId)
{
    return new google.maps.Marker(
                {
                    position: pos,
                    map: map,
                    title: title,
                    customId: customId,
                    isCircle: false
                });
}

function makeCircle(pos, map, title, customId)
{
    return new google.maps.Marker(
                {
                    position: pos,
                    map: map,
                    title: title,
                    customId: customId,
                    icon: blogUrl.plugInUrl+"/img/red_dot.png",
                    isCircle: true
                });
}

function togglePinCircle(mrkr)
{
    if (mrkr.isCircle)
    {
        mrkr.setIcon('https://maps.gstatic.com/mapfiles/ms2/micons/red-dot.png');
        mrkr.isCircle=false;
        mrkr.title=CURRENT_LOCATION;
    }
    else
    {
        mrkr.setIcon(blogUrl.plugInUrl+"/img/red_dot.png");
        mrkr.isCircle=true;
        mrkr.title=AVAILABLE_LOCATION;
    }
}

function addMarkerEventListeners(mapThing)
{
    google.maps.event.addListener(mapThing, 'mouseover', function(){doMarkerMouseOver(this);});
    google.maps.event.addListener(mapThing, 'mouseout', function(){doMarkerMouseOut(this);});
}

function addRadioHoverEventListeners(el)
{
    jQuery(el).hover(
              function(evt)
              {
                  if (jQuery(evt.currentTarget).attr('id').indexOf('none')>-1) return;
                  var mrkr = getMarkerByAssocEl(jQuery(evt.currentTarget));
                  if (mrkr.isCircle)
                  {
                      mrkr.setIcon(blogUrl.plugInUrl+"/img/green_dot.png");
                  }
                  else
                  {
                      mrkr.setIcon('https://maps.gstatic.com/mapfiles/ms2/micons/green-dot.png');
                  }
                  
                  // do the hover tag thing
              },function(evt){
                  if (jQuery(evt.currentTarget).attr('id').indexOf('none')>-1) return;    
                  var mrkr = getMarkerByAssocEl(jQuery(evt.currentTarget));
                  if (mrkr.isCircle)
                  {
                      mrkr.setIcon(blogUrl.plugInUrl+"/img/red_dot.png");
                  }
                  else
                  {
                      mrkr.setIcon('https://maps.gstatic.com/mapfiles/ms2/micons/red-dot.png');
                  }});
}

function addRadioClickEventListeners(el)
{
    jQuery(el).click(
              function(evt)
              {
                  var clickedMrkr = getMarkerByAssocEl(jQuery(evt.currentTarget));
                  
                  // They clicked none
                  if(clickedMrkr == null)
                  {                      
                      if (mapDataStore.selectedMrkr!=null)
                      {
                          togglePinCircle(mapDataStore.selectedMrkr);
                          mapDataStore.selectedMrkr=null;
                      }
                  }
                  else if (mapDataStore.selectedMrkr!=null && clickedMrkr.customId != mapDataStore.selectedMrkr.customId)
                  {
                      // They're replacing a selection.
                      // Change pin to circle, change circle to pin
                      togglePinCircle(mapDataStore.selectedMrkr);
                      togglePinCircle(clickedMrkr);
                      mapDataStore.selectedMrkr = clickedMrkr
                  }
                  else if (mapDataStore.selectedMrkr==null)
                  {
                      // Nothing is selected
                      // Change circle to pin
                      togglePinCircle(clickedMrkr);
                      mapDataStore.selectedMrkr = clickedMrkr;
                  }     
              });
}

function geocode() 
{
    var address = jQuery("#geocodesearch").val();
    geocoder.geocode({
      'address': address,
      'partialmatch': true}, geocodeResult);
}

function geocodeResult(results, status) 
{
    if (status == 'OK' && results.length > 0) {
      mapDataStore.map.fitBounds(results[0].geometry.viewport);
    } else {
      alert("Geocode was not successful for the following reason: " + status);
    }
}

jQuery(document).ready(
   function()
   {
      jQuery('.gpx-radio, .gpx-label').each(function(inx,el){addRadioHoverEventListeners(el)});
      jQuery('.gpx-radio').each(function(inx,el){addRadioClickEventListeners(el)});
      jQuery('#geocodesearch').keydown(function (e) {
        if (e.keyCode == 13)
        {
            e.preventDefault();
            geocode();
        }
    });
      jQuery('#dogeocodesearch').click(function(){geocode();});
   });

// Thx to: 
// Look here for code on how to fit within the bounds of the markers:
// http://blog.shamess.info/2009/09/29/zoom-to-fit-all-markers-on-google-maps-api-v3/
// Google sample for drawing circles on the map (useful for non-selected items).
// https://developers.google.com/maps/documentation/javascript/examples/circle-simple
// Search stuff
// view-source:http://gmaps-samples-v3.googlecode.com/svn/trunk/geocoder/getlatlng.html