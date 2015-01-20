var mapView = false;
var toId, mapDataStore =
        {
            markers: {},
            map: null,
            locations: new Array(),
            cssHeight: 0,
            cssWidth: 0
        };

jQuery(document).ready(
        function()
        {
            if (jQuery("#maplink").length == 0) return;
            
            mapDataStore.locations = getLocations();            

            jQuery("#maplink").parent().prepend('<div id="mapBox"><div id="galmap"/></div>'); 
            
            jQuery("#mapBox").click(
                   function(evt){
                        if (jQuery(evt.target).attr('id')==='mapBox') 
                            jQuery("#mapBox").hide();
                    });
                    
            mapDataStore.cssHeight=parseInt(jQuery("#galmap").css("height"));
            mapDataStore.cssWidth=parseInt(jQuery("#galmap").css("width"));
 
            jQuery("#maplink").show().click(function(){
               
               var $mapBox = jQuery("#mapBox");
               
               $mapBox.css({top:'0px',left:'0px',height:'100%',width:'100%',"z-index":"500"});
               $mapBox.show();
               sizeMap(jQuery("#galmap"));
               initMap();
               mapViewEvent();
            }); 
            
            jQuery(window).resize(
                 function(){
                    clearTimeout(toId);
                    toId = setTimeout(doMapResize, 100);    
            });
        }
   );
   
function doMapResize()
{
    // func gets no args
    if (mapDataStore.map)sizeMap(jQuery("#galmap"));
}

function initMap()
{
    if (mapDataStore.map==null)
    {
        var mapOptions = getMapOptions(mapDataStore.locations);
        mapDataStore.map = new google.maps.Map(jQuery("#galmap")[0],mapOptions);
        
        var bounds = new google.maps.LatLngBounds();            
        
        var locslength = mapDataStore.locations.length;
        for (var inx=0; inx<locslength; inx++)
        {        
            var pos = new google.maps.LatLng(mapDataStore.locations[inx].lat,mapDataStore.locations[inx].long);
        
            //  Create a new viewpoint bound
            //  And increase the bounds to take this point
            bounds.extend(pos);       
        
            var mrkr = makeMarker(pos,mapDataStore.locations[inx], mapDataStore.map);
            google.maps.event.addListener(mrkr, 'click', function() {
                var icn = this.icon.url;
                var arr = icn.split("/");
                var img = arr[arr.length-1];
                var tgt = "img[src$='"+img+"']"
                var $a = jQuery(tgt).parent();
                $a.click();
            });

            //addMarkerEventListeners(mrkr);
            mapDataStore.markers[mapDataStore.locations[inx].title] = mrkr;        
        }  
    
        if (!bounds.isEmpty())
            mapDataStore.map.fitBounds(bounds);  
        addCloseButton(mapDataStore.map);
    }
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

function sizeMap($map)
{
    // Fix this to resize to normal when window is big enough.
    // Only trigger resize if elWidth or elHeight isn't big enough
    // Clean this up to be consistent with selection, too.
    var viewportWidth = jQuery(window).width(),
        viewportHeight = jQuery(window).height(),
        maxWidth = mapDataStore.cssWidth,
        maxHeight = mapDataStore.cssHeight;

    if (maxHeight>viewportHeight)
        $map.css({height:viewportHeight+"px"});
    else
        $map.css({height:maxHeight+"px"});
    
    if (maxWidth>viewportWidth)
        $map.css({width:viewportWidth+"px"});
    else
        $map.css({width:maxWidth+"px"});
    
    if (mapDataStore.map!=null)
    {
        google.maps.event.trigger(mapDataStore.map, 'resize');
    }
}

function makeMarker(pos,loc, map)
{
    var mrkrImg = {
        url:loc.img,
        scaledSize : new google.maps.Size(50, 50)
    };
            
    return new google.maps.Marker(
                {
                    position: pos,
                    map: map,
                    title: loc.title,
                    icon: mrkrImg
                });
}

// try opening pics with attribute ends selector:
// http://api.jquery.com/attribute-ends-with-selector/


function addCloseButton(map)
{
    // custom control:
    //https://developers.google.com/maps/documentation/javascript/controls?csw=1#CustomControls
    // Create a div to hold the control.
    var controlDiv = document.createElement('div');

    // Set CSS styles for the DIV containing the control
    // Setting padding to 5 px will offset the control
    // from the edge of the map.
    controlDiv.style.padding = '5px';

    // Set CSS for the control border.
    var controlUI = document.createElement('div');
    controlUI.style.backgroundColor = 'white';
    controlUI.style.borderStyle = 'solid';
    controlUI.style.borderWidth = '1px';
    controlUI.style.cursor = 'pointer';
    controlUI.style.textAlign = 'center';
    controlUI.title = 'Close the map';
    controlDiv.appendChild(controlUI);

    // Set CSS for the control interior.
    var controlText = document.createElement('div');
    controlText.style.fontFamily = 'Arial,sans-serif';
    controlText.style.fontSize = '11px';
    controlText.style.paddingLeft = '4px';
    controlText.style.paddingRight = '4px';
    controlText.innerHTML = 'Close';
    controlUI.appendChild(controlText);
    google.maps.event.addDomListener(controlUI, 'click', function() {
        jQuery("#mapBox").click();
    });
    map.controls[google.maps.ControlPosition.RIGHT_TOP].push(controlDiv);
}

function getLocations()
{
    var arr = jQuery('a.gallery-thumbnail').map(
           function()
            {
                if (jQuery(this).attr('data-lng'))
                    return {'img':jQuery(this).find('.gallery-content').attr('src'),
                            'title':jQuery(this).attr('data-title'),
                             'lat':jQuery(this).attr('data-lat'),
                             'long':jQuery(this).attr('data-lng')};
            }).get();
            
    return arr;
}

function mapViewEvent()
{
    if (!mapView)
    {
        try
        {
            var title = document.title.split('|')[0].trim();
            __gaTracker('send', {
                'hitType': 'event',
                'eventCategory': 'map',
                'eventAction': 'mapView',
                'eventLabel': title
            });
            
        }
        catch(e){console.log("Couldn't send mapview event.");}
        
        mapView = true;
    }
}