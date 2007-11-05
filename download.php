<? 
/************************************************************************/
/* Leonardo: Gliding XC Server					            			*/
/* ============================================                         */
/*                                                                      */
/* Copyright (c) 2004-5 by Andreadakis Manolis                          */
/* http://sourceforge.net/projects/leonardoserver                       */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/
	
	if ($_GET["show_url"]) {
		$link=substr("http://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'],0,-11);
		echo "The link to KML file is <br><a href='$link'>$link</a>";

		return;
	}

 	require_once dirname(__FILE__)."/EXT_config_pre.php";
	require_once dirname(__FILE__)."/config.php";
 	require_once dirname(__FILE__)."/EXT_config.php";

	require_once dirname(__FILE__)."/FN_functions.php";	
	require_once dirname(__FILE__)."/FN_UTM.php";
	require_once dirname(__FILE__)."/FN_pilot.php";
	require_once dirname(__FILE__)."/FN_waypoint.php";	
	require_once dirname(__FILE__)."/FN_output.php";
	require_once dirname(__FILE__)."/CL_flightData.php";
	if( ! $CONF_use_utf) {
		require_once dirname(__FILE__)."/language/lang-".$currentlang.".php";
		require_once dirname(__FILE__)."/language/countries-".$currentlang.".php";
	} else {
		require_once dirname(__FILE__)."/language/utf8/lang-".$currentlang.".php";
		require_once dirname(__FILE__)."/language/utf8/countries-".$currentlang.".php";
	}
	setDEBUGfromGET();

	$type=makeSane($_REQUEST['type']);
	if (!in_array($type,array("kml_task","kml_trk","kml_wpt","sites")) ) return;

	if ($type=="kml_task") {
		//$isExternalFile=0;
		//setLeonardoPaths();

		$moduleRelPath=moduleRelPath(0); 
		$waypointsWebPath=$moduleRelPath."/".$waypointsRelPath;
		$flightsWebPath=$moduleRelPath."/".$flightsRelPath;

		$flightID=makeSane($_REQUEST['flightID'],1);
		//echo $_SERVER['QUERY_STRING'];
		
		DEBUG("DL",1,"Will serve task for flight $flightID<BR>");

		$flight=new flight();
		$flight->getFlightFromDB($flightID);
	//	if ( $flight->userID!=$userID && ! auth::isAdmin($userID) && $flight->private) {
	//		echo _FLIGHT_IS_PRIVATE;
	//		return;
	//	}
		$xml=$flight->kmlGetTask();
		//$xml=$flight->createKMLfile("ff0000",1,2);

		$file_name=$flight->filename.".task.kml";	
		DEBUG("DL",1,"KML Filepath= $file_path<BR>");
	} else if ($type=="kml_trk") {
		$moduleRelPath=moduleRelPath(0); 
		$waypointsWebPath=$moduleRelPath."/".$waypointsRelPath;
		$flightsWebPath=$moduleRelPath."/".$flightsRelPath;

		$flightID=makeSane($_REQUEST['flightID'],1);
		//echo $_SERVER['QUERY_STRING'];
		$w=makeSane($_GET['w'],1);
		$c=makeSane($_GET['c']);
		$ex=makeSane($_GET['ex'],1);
		$an=makeSane($_GET['an'],1);

		if (!$w) $w=2;
		if (!$c) $c="ff0000";
		if (!$ex) { $ex=1; }
		
		DEBUG("DL",1,"Will serve flight $flightID<BR>");

		$flight=new flight();
		$flight->getFlightFromDB($flightID);
	//	if ( $flight->userID!=$userID && ! auth::isAdmin($userID) && $flight->private) {
	//		echo _FLIGHT_IS_PRIVATE;
	//		return;
	//	}
		$xml=$flight->createKMLfile($c,$ex,$w,$an);
		//$xml=$flight->createKMLfile("ff0000",1,2);

		$file_name=$flight->filename.".kml";	
		DEBUG("DL",1,"KML Filepath= $file_path<BR>");
	} else if ($type=="gpx_trk") {
		$flightID=makeSane($_REQUEST['flightID'],1);
		//echo $_SERVER['QUERY_STRING'];
		DEBUG("DL",1,"Will serve flight $flightID<BR>");
		$flight=new flight();
		$flight->getFlightFromDB($flightID);
		$xml=$flight->createGPXfile();
		$file_name=$flight->filename.".xml";
		DEBUG("DL",1,"GPX Filepath= $file_path<BR>");
	} else 	if ($type=="kml_wpt") {		
		$waypointID=makeSane($_REQUEST['wptID'],1);
		
		$xml=makeKMLwaypoint($waypointID);
		$file_name=$waypointID.'.kml';
	} else	if ($type=="sites") {
		$sites=makeSane($_GET['sites']);
		$sitesList=explode(",",$sites);
//		$xml='<?xml version="1.0" encoding="'.$langEncodings[$currentlang].'"? >'.
		$xml='<?xml version="1.0" encoding="UTF-8"?>'.
		'<kml xmlns="http://earth.google.com/kml/2.1">\n
		<Folder>
		<name>Leonardo Site List</name>';

		foreach($sitesList as $waypointID) {		
			list($xml_str,$countryCode)=makeWaypointPlacemark($waypointID,1);
			if (!is_array($takeoffs[$countryCode]) ) $takeoffs[$countryCode]=array();
			array_push($takeoffs[$countryCode],$xml_str);

		}

		foreach($takeoffs as $countryCode=>$countrySites) {		
			$xml.="<Folder>\n<name>".$countries[$countryCode]."</name>\n";
			foreach ($countrySites as $siteXml) {
				$xml.=$siteXml;
			}
			$xml.="</Folder>";
		}
		
		$xml.="</Folder>\n</kml>\n";
		
		require_once dirname(__FILE__)."/lib/ConvertCharset/ConvertCharset.class.php";
		$NewEncoding = new ConvertCharset;
		$FromCharset=$langEncodings[$currentlang];
		$xml = $NewEncoding->Convert($xml, $FromCharset, "utf-8", $Entities);
		
		$file_name="Leonardo site guide.kml";
	
	}

		list($browser_agent,$browser_version)=getBrowser();

		if ($browser_agent == 'opera') $attachmentMIME = 'application/kml';
		else if ($browser_agent == 'ie'  || $browser_agent == 'netscape'   || $browser_agent == 'mozilla'  ) 
			$attachmentMIME ="application/vnd.google-earth.kml+xml";
		else $attachmentMIME ='application/octet-stream';

		DEBUG("DL",1,"browser_agent=$browser_agent, browser version=$browser_version<BR>");

		header("Pragma: public"); // required
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private",false); // required for certain browsers 

		header("Content-type: $attachmentMIME");
		//header("Content-Disposition: attachment; filename=\"$kml_file_name\"", true);
		header('Content-Disposition: inline; filename="' . htmlspecialchars($file_name) . '"');
		header("Content-Transfer-Encoding: binary");

		$size = strlen($xml);
		header("Content-length: $size");
		echo $xml;

?>