<?php

	class EAN_API{

  /*
  EAN API Class
  Author: Patrick Butkiewicz (butkiewicz.p@gmail.com)
  Version: 0.1
  
  Supports Version 3 of the EAN-Hotels API.
  */

		// API Settings	
		protected $cid = '55505';
		protected $apiKey = 'YOUR_API_KEY_HERE';  
		protected $secret = 'YOUR_SECRET_HERE';
		protected $currency = "USD";
		protected $lastRequest = 0;
		protected $DEFAULT_SHOW_RESULTS = '10';
		protected $minorRev = '7';
		protected $countryLocale = 'en_US';
		protected $currencyCode = 'USD';
		protected $dataType = 'xml';
	
		protected $api_connection_retries = 5;
	
		// CLASS CONSTRUCTOR
		public function __construct(){
			$this->lastRequest = 0;
		}
	
	/**********************
	* Function: make_xml_request
	* Args: service -> any of the expedia services that can be called via the API
	*       xml -> the XML string to send
	*       [optional] method -> HTTP request method. GET or POST
	*       [optional] timestamp -> server timestamp to be sent (used for some expedia errors)
	*
	*	Returns: Object of class SimpleXMLElement containing the EAN servers XML response 
	*/
		public function make_xml_request($service, $xml, $method = "get", $timestamp = ""){
			
			// re-case variables
			$method = strtolower($method);

			// For catching the server-latency errors from expedia
			if(empty($timestamp))
				$timestamp = gmdate('U');
			
			// Create signature
			$sig = md5($this->apiKey . $this->secret . $timestamp);
			
			// Set post-data array
			$postData = array(
				'minorRev' => $this->minorRev,
				'cid' => $this->cid,
				'apiKey' => urlencode($this->apiKey),
				'customerUserAgent' => urlencode($_SERVER['HTTP_USER_AGENT']),
				'customerIpAddress' => urlencode($_SERVER['REMOTE_ADDR']),
				'locale' => urlencode($this->countryLocale),
				'currencyCode' => urlencode($this->currency),
				'sig' => urlencode($sig),
				'_type' => urlencode($this->dataType),
				'xml' => urlencode($xml)
			);
			
			// Construct URL to send request to
			$url = "http://api.ean.com/ean-services/rs/hotel/v3/" . $service . '?';
			
			// If using GET as request method, create postdata string
			if(strtolower($method) == "get"){
				foreach($postData as $key => $value)
					$url .= $key . '=' . $value . '&'; 
				
				$url = substr($url, 0, -1);
			}
						
			// Expedia 1 Query-Per-Second Rule
			$time = microtime();
			$microSeconds = $time - $lastRequest;
			if($microSeconds < 1000000 && $microSeconds > 0)
				usleep($microSeconds);
				
		
			// Begin executing CURL
			$curl_attempts = 0;					// Curl request counter
			$MAXIMUM_CURL_ATTEMPTS = $this->api_connection_retries;	// Max Curl attempts
			do{
				$curl = curl_init();
				curl_setopt($curl,CURLOPT_FORBID_REUSE, 1);
				curl_setopt($curl,CURLOPT_FRESH_CONNECT, 1);
				curl_setopt($curl,CURLOPT_HTTPAUTH, CURLAUTH_ANY);
				curl_setopt($curl,CURLOPT_HTTPHEADER, array('Content-Type: text/xml; charset=UTF-8','Accept: application/xml'));
				curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
				curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($curl,CURLOPT_TIMEOUT,30);
				curl_setopt($curl,CURLOPT_URL, $url);
				curl_setopt($curl,CURLOPT_VERBOSE,1);
				
				// If POSTing data, set appropriate curl options
				if(strtolower($method) == "post"){
					curl_setopt($curl,CURLOPT_POST, 1);
					curl_setopt($curl,CURLOPT_POSTFIELDS, $postData);
				}

				// Execute, capture, and close curl request
				$response = trim(curl_exec($curl));
				curl_close($curl);
			} while (strlen($response) == 0 && ++$curl_attempts < $MAXIMUM_CURL_ATTEMPTS);

			// Set the lastrequest time to our current time			
			$this->lastRequest = microtime();
			
			// Remove junk characters from response
			$response = str_replace("&amp;lt;","&lt;",$response);
			$response = str_replace("&amp;gt;","&gt;",$response);
			$response = str_replace("&amp;apos;","&apos;",$response);
			$response = str_replace("&amp;#x0A","",$response);
			$response = str_replace("&amp;#x0D","",$response);
			$response = str_replace("&#x0D","",$response);
			$response = str_replace("&#x0A","",$response);
			$response = str_replace("&amp;#x09","",$response);
			$response = str_replace("&amp;amp;amp;","&amp;",$response);
			$response = str_replace("&lt;br&gt;","<br />",$response);

			// load XML response as SimpleXML object
			$results = simplexml_load_string($response);
			
			// ERROR CATCH: timestamp / latency between expedia server
			if((string)$results->EanWsError->handling == 'RECOVERABLE' && (string)$results->EanWsError->category == 'AUTHENTICATION'){
				$newServerTime = $results->EanWsError->ServerInfo['timestamp'];
				return $this->make_xml_request($service, $xml, $method, $newServerTime);
			}
			
			// If we cannot connect to the EAN API
			if (strlen($response) == 0)
				$results['error'] = 'Error reaching XML gateway';
				
			return $results;
		}
		
	/**********************
	* Function: make_res_xml_request
	* Args: Same as regular make_xml_request function above
	*
	*	Returns: Object of class SimpleXMLElement containing XML data
	* NOTE: This function is only called from the getReservation function below
	*   this will be removed in v0.2. This is almost all repeated code.
	*/
		public function make_res_xml_request($xml){
			
			if(empty($timestamp))
				$timestamp = gmdate('U');
			
			
			// Set post-data array
			$postData = array(
				'minorRev' => $this->minorRev,
				'cid' => $this->cid,
				'apiKey' => urlencode($this->apiKey),
				'customerUserAgent' => urlencode($_SERVER['HTTP_USER_AGENT']),
				'customerIpAddress' => urlencode($_SERVER['REMOTE_ADDR']),
				'locale' => urlencode($this->countryLocale),
				'currencyCode' => urlencode($this->currencyCode),
				'_type' => urlencode($this->dataType),
				'xml' => urlencode($xml)
			);
			
			$url = "https://book.api.ean.com/ean-services/rs/hotel/v3/res";
			
			$postDataString = "";
			foreach($postData as $key => $value){ 
				$postDataString .= $key.'='.$value.'&'; 
				}
			$postDataString = substr($postDataString, 0, -1);
			
			// Expedia 1 Query-Per-Second Rule
			$time = microtime();
			$microSeconds = $time - $lastRequest;
			if($microSeconds < 1000000 && $microSeconds > 0)
				usleep($microSeconds);
				

			// Begin executing CURL
			$curl_attempts = 0;
			$MAXIMUM_CURL_ATTEMPTS = $this->api_connection_retries;
			do{
				$curl = curl_init();
				curl_setopt($curl,CURLOPT_URL, $url);
				curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
				curl_setopt($curl,CURLOPT_TIMEOUT,30);
				curl_setopt($curl,CURLOPT_VERBOSE,1);
				curl_setopt($curl,CURLOPT_FORBID_REUSE, 1);
				curl_setopt($curl,CURLOPT_FRESH_CONNECT, 1);
				curl_setopt($curl,CURLOPT_HTTPAUTH, CURLAUTH_ANY);
				curl_setopt($curl,CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded',
															'Accept: application/xml'
															));

				curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($curl,CURLOPT_POST, count($postData));
				curl_setopt($curl,CURLOPT_POSTFIELDS, $postDataString);

				$response = trim(curl_exec($curl));
				flush();
				ob_flush();
				curl_close($curl);
			} while (strlen($response) == 0 && ++$curl_attempts < $MAXIMUM_CURL_ATTEMPTS);
			$this->lastRequest = microtime();
			
			// Remove junk characters from response
			$response = str_replace("&amp;lt;","&lt;",$response);
			$response = str_replace("&amp;gt;","&gt;",$response);
			$response = str_replace("&amp;apos;","&apos;",$response);
			$response = str_replace("&amp;#x0A","",$response);
			$response = str_replace("&amp;#x0D","",$response);
			$response = str_replace("&#x0D","",$response);
			$response = str_replace("&#x0A","",$response);
			$response = str_replace("&amp;#x09","",$response);
			$response = str_replace("&amp;amp;amp;","&amp;",$response);
			$response = str_replace("&lt;br&gt;","<br />",$response);
			$xml_response = simplexml_load_string($response);
			
			if((string)$xml_response->EanWsError->handling == 'RECOVERABLE' && (string)$xml_response->EanWsError->category == 'AUTHENTICATION'){
				$newServerTime = $xml_response->EanWsError->ServerInfo['timestamp'];
				//return $this->make_res_xml_request($xml);
			}
			
			// If we cannot connect to the EAN API
			if (strlen($response) == 0)
				$response = '<response><error>Error reaching XML gateway. We\'re working on this problem and apologize for the inconvenience</error></response>';
			else{
				//if(isset($response['']
				//$_SESSION['customerSessionId'] = $response[]['customerSeassionId'];
			}
				
			return $response;
		}
		
	/**********************
	* Function: locationSearch
	* Args: destinationString -> any kind of destination string (city, state, etc)
	*
	*	Returns: Object of class SimpleXMLElement containing a list of valid 
	*						locations to use for searching
	*						depending on options passed
	*		Returned XML Object detailed here: 
	* 	http://developer.ean.com/docs/read/hotels/version_3/geofunctions/Response_Format
	*/
		public function simpleLocationSearch($destinationString, $type = "1"){
			$request = '<?xml version="1.0" encoding="utf-8" ?>';
			$request = '<LocationInfoRequest>';
			$request .= '<destinationString>';
			$request .= $destinationString;
			$request .= '</destinationString>';
			$request .= '<type>' . $type . '</type>';
		  $request .= '</LocationInfoRequest>';

		  return $this->make_xml_request('geoSearch', $request);
		}
		
/*************
* Function: STRING CLEAN UP
* Args: string -> string to clean up
* Returns: cleaned string
*/
		static public function string_clean_up($string){
			$ary1 = array('&amp;','&apos;');
			$ary2 = array('&','\'');
			
			return trim(str_replace($ary1, $ary2, htmlspecialchars_decode($string)));
		}
 
	/**********************
	* Function: getHotels 
	* Args: id -> ID number of the hotel returned from expedia
	*				infoArray -> array of strings (options) to pass to expedia
	*
	*	Returns: Object of class SimpleXMLElement containing Hotel Information 
	*						depending on options passed
	*		Returned XML Object detailed here: 
	* 	http://developer.ean.com/docs/read/hotels/version_3/request_hotel_information/Response_Format
	*/
	public function getHotels($infoArray){
	
		$destination = $infoArray['city'];
		$destinationID = $infoArray['desID'];
		$language = $infoArray['locale'];
		$check_in = $infoArray['checkIn'];
		$check_out = $infoArray['checkOut'];
		$rooms = $infoArray['numberOfRooms'];
		$children_breakdown = "";
		$property_types = $infoArray['propertyType'];
		$hotel_name = $infoArray['hotel_name'];
		$page = $infoArray['page'];
		$sort = $infoArray['sort'];
		$address = $infoArray['address'];
		$hotel_ids = $infoArray['hotel_ids'];
		$nearby_landmark = $infoArray['nearby_landmark'];
		$cacheKey = $infoArray['cacheKey'];
		$cacheLoc = $infoArray['cacheLocation'];
		
		// If the number of results requested is different than default
		if(($num_show_results = intval($infoArray['numberOfResults'])) <= 1)
			$num_show_results = $this->DEFAULT_SHOW_RESULTS;
	
		if ($page == 1)
			$_SESSION['EAN_hotel_results'] = array();
		
		$results = array();
		$destination_id = '';
		
			
		// Check if passed destination ID is valid
		if (preg_match('/([0-9A-F]{8})-([0-9A-F]{4})-([0-9A-F]{4})-([0-9A-F]{4})-([0-9A-F]{12})/',$destinationID))
			$destination_id = $destinationID;
			
		/* Start GEO-SEARCH
		* If no destination-ID or Hotel-ID was passed, OR if a new address WAS passed, perform
		*  a new geo-location search to get an appropriate location 
		*/
		if ((strlen($destination_id) == 0 && strlen($hotel_ids) == 0) || strlen($address) > 0){
		  // Determine destination string and perform geo-location search for a proper
		  $dest_string = strlen($address) > 0 ? $_GET['destination'] : $destination;
		  $possible_locations = $this->simpleLocationSearch($dest_string);
		  
			sleep(1); // Avoid 1-query-per-second rule

			$location_count = intval($possible_locations->LocationInfos['size']);
			
			if($location_count == 1){ // IF ONLY ONE LOCATION IS FOUND
				$destination_id = (string)$possible_locations->LocationInfos->LocationInfo->destinationId;
				$results['current_search']['city'] = (string)$possible_locations->LocationInfos->LocationInfo->city;
				$_SESSION['current_search']['destination'] = (string)$possible_locations->LocationInfos->LocationInfo->code;
			}else if($location_count > 1){ // IF MULTIPLE LOCATIONS ARE FOUND
				$results['locations'] = array();

        // Move all possible locations found into results var for further processing
				foreach ($possible_locations->LocationInfos->LocationInfo as $location){
					$results['locations'][] = array(
					'text' => str_replace(',,',',',(string)$location->code),
					'destination_id' => (string)$location->destinationId
					);
				}

        // Set the results[recoverable] var to an error message to display
				$results['recoverable'] = 'Multiple cities were found with that name';
				return $results;
				
			}else{  // NO LOCATIONS FOUND
				$results['locations'] = array();
				// Add returned XML object to results[error] var for debugging
				$results['error'] = $possible_locations;  

				return $results;
			}
		}
		// END GEO SEARCH
		
		$results['current_search']['check_in'] = $check_in;
		$results['current_search']['check_out'] = $check_out;
	
		//Start XML Search Request
		$xml = "<HotelListRequest>";
		if(isset($_SESSION['customerSessionId'])) 
			$xml .= "<customerSessionId>" . $_SESSION['customerSessionId'] . "</customerSessionId>";

		$xml .= "<arrivalDate>" . $check_in . "</arrivalDate>";
		$xml .= "<departureDate>" . $check_out . "</departureDate>";
		$xml .= "<numberOfResults>" . $num_show_results . "</numberOfResults>";
		$xml .= "<destinationId>" . $destination_id . "</destinationId>";
		
		if(!empty($infoArray['hotelName']))
			$xml .= "<propertyName>" . $infoArray['hotelName'] . "</propertyName>";
		
		// If property types were passed, set them
		if(is_array($infoArray['propertyTypes'])){
			$count = 0;
			$xml .= "<propertyCategory>";
			foreach($infoArray['propertyTypes'] as $key => $value)
				if(++$count == 1)
					$xml .= $value;
				else
					$xml .= ',' . $value;
			$xml .= "</propertyCategory>";
		}
		
		// If required amenities were passed
		if(is_array($infoArray['amenities'])){
			$count = 0;
			$xml .= "<amenities>";
			foreach($infoArray['amenities'] as $key => $value)
				if(++$count == 1)
					$xml .= $value;
				else
					$xml .= ',' . $value;
			$xml .= "</amenities>";
		}
		
		// If a minimum star rating is passed
		if(!empty($infoArray['minStarRating']))
			$xml .= "<minStarRating>" . $infoArray['minStarRating'] . "</minStarRating>";

    // If a hotel sorting type is set
		if(!empty($infoArray['sort']))
			$xml .= "<sort>" . $infoArray['sort'] . "</sort>";
		
		// Set Up Room object
		$xml .= "<RoomGroup>";
		for($i=0;$i<$rooms;$i++){
			$xml .= "<Room>";
			$xml .= "<numberOfAdults>" . $infoArray['room-'.$i.'-adult-total'] . "</numberOfAdults>";
			if(intval($infoArray['room-'.$i.'-child-total']) > 0){
				$xml .= "<numberOfChildren>" . $infoArray['room-'.$i.'-child-total'] . "</numberOfChildren>";
				$xml .= "<childAges>";
				for($j=0;$j<$infoArray['room-'.$i.'-child-total'];$j++){
					if($j == 0)
						$childAgesStr = $infoArray['room-'.$i.'-child-'.$j.'-age'];
					else
						$childAgesStr .= ','. $infoArray['room-'.$i.'-child-'.$j.'-age'];
				}
				$xml .= $childAgesStr;
				$xml .= "</childAges>";
			}
			if($infoArray['room-'.$i.'-bedType'])
				$xml .= "<bedTypeId>".$infoArray['room-'.$i.'-bedType']."</bedTypeId>";
			if($infoArray['room-'.$i.'-smokingPreference'])
				$xml .= "<smokingPreference>".$infoArray['room-'.$i.'-smokingPreference']."</smokingPreference>";
				
			$xml .= "</Room>";
		}
			$xml .= "</RoomGroup>";
			$xml .= "</HotelListRequest>";
		
			// Make XML Request to Expedia Servers
			$search_results = $this->make_xml_request('list', $xml);
			
			// Check if a Recoverable error was returned from expedia
			if($search_results->EanWsError->handling == 'RECOVERABLE'){
					$results['recoverable'] = (string)$search_results->EanWsError->verboseMessage;
					return $results;
			}

			// Check if a unrecoverable error was returned from expedia
			if($search_results->EanWsError->handling == 'UNRECOVERABLE'){
					$results['error'] = (string)$search_results->EanWsError->verboseMessage;
					return $results;
			}
			
			// Store the CacheKey and cacheLocation returned by expedia
			if($search_results->cacheKey){
				$cacheKey = (string)$search_results->cacheKey;
				$cacheLoc = (string)$search_results->cacheLocation;
				$_SESSION['cacheKey'] = $cacheKey;
				$_SESSION['cacheLocation'] = $cacheLoc;
			}else{
				unset($_SESSION['cacheKey']);
				unset($_SESSION['cacheLocation']);
			}
			
			$_SESSION['current_search']['city'] = $city;
			$results['title'] = $_SESSION['current_search']['destination'];
			$_SESSION['current_search']['check_in'] = $check_in;
			$_SESSION['current_search']['check_out'] = $check_out;
			$results['hotels'] = $search_results;
			
			return $results;
	}
	

	/**********************
	* Function: getHotelDetails 
	* Args: id -> ID number of the hotel returned from expedia
	*				infoArray -> array of strings (options) to pass to expedia
	*
	*	Returns: Object of class SimpleXMLElement containing Hotel Information 
	*						depending on options passed
	*		Returned XML Object detailed here: 
	* 	http://developer.ean.com/docs/read/hotels/version_3/request_hotel_information/Response_Format
	*/
	public function getHotelDetails($id, $infoArray = "0"){
	
		$xml = "<HotelInformationRequest>";
		$xml .= "<hotelId>" . $id . "</hotelId>";
		$xml .= "<options>";
		if(is_array($infoArray)){             // If we're passed an array of options
			$options = "";
			foreach($infoArray as $opt)					// Construct options list
				$options .= $opt . ",";
			$options = substr($options, 0, -1);	// Remove last comma
			$xml .= $options;
		}else{
			$xml .= $infoArray;                 // If we're passed a single option
		} 
		$xml .= "</options>";
		$xml .= "</HotelInformationRequest>";
		
		$search_results = $this->make_xml_request('info', $xml);
		return $search_results;
	}
	
	/**********************
	* Function: getRateRules 
	* Args: infoArray -> key-value pairs to pass to expedia
	*
	*	Returns: Object of class SimpleXMLElement containing agency rates and rules 
	*		Returned XML Object detailed here: 
	* 	http://developer.ean.com/docs/read/hotels/version_3/request_rate_rules/Response_Format
	*/
	public function getRateRules($infoArray){
		$xml = "<HotelRateRulesRequest>";
		$xml .= "<hotelId>".$infoArray['hotel_id']."</hotelId>";
		$xml .= "<arrivalDate>".$infoArray['checkIn']."</arrivalDate>";
		$xml .= "<departureDate>".$infoArray['checkOut']."</departureDate>";
		$xml .= "<supplierType>E</supplierType>";
		$xml .= "<rateCode>".$infoArray['rateCode']."</rateCode>";
		$xml .= "<roomTypeCode>".$infoArray['roomTypeCode']."</roomTypeCode>";
		$xml .= "<RoomGroup>";

		// Set Up Room object
		$rooms = $infoArray['numberOfRooms'];
		for($i=0;$i<$rooms;++$i){
			$xml .= "<Room>";
			$xml .= "<numberOfAdults>" . $infoArray['room-'.$i.'-adult-total'] . "</numberOfAdults>";
			if(intval($infoArray['room-'.$i.'-child-total']) > 0){
				$xml .= "<numberOfChildren>" . $infoArray['room-'.$i.'-child-total'] . "</numberOfChildren>";
				$xml .= "<childAges>";
				for($j=0;$j<$infoArray['room-'.$i.'-child-total'];$j++){
					if($j == 0)
						$childAgesStr = $infoArray['room-'.$i.'-child-'.$j.'-age'];
					else
						$childAgesStr .= ','. $infoArray['room-'.$i.'-child-'.$j.'-age'];
				}
				$xml .= $childAgesStr;
				$xml .= "</childAges>";
			}
			if($infoArray['room-'.$i.'-bedType'])
				$xml .= "<bedTypeId>".$infoArray['room-'.$i.'-bedType']."</bedTypeId>";
			if($infoArray['room-'.$i.'-smokingPreference'])
				$xml .= "<smokingPreference>".$infoArray['room-'.$i.'-smokingPreference']."</smokingPreference>";
				
			$xml .= "</Room>";
		}
		$xml .= "</RoomGroup>";
		$xml .= "</HotelRateRulesRequest>";
		
		$results = $this->make_xml_request('rules', $xml);
		return $results;
	}
	
	/**********************
	* Function: cacheRequest 
	* Args: key -> cacheKey returned by an expedia search
  *       location -> cacheLocation returned by an expedia search
  *       page -> index value for $_SESSION var to record previous keys and caches
	*
	*	Returns: Object of class SimpleXMLElement containing a list of hotels
  *           SimpleXMLElement will contain the same values as a getHotelList call 
	*/
	public function cacheRequest($key, $location, $page = 0){
	
		$_SESSION['current_search'][$page]['key'] == $key;
		$_SESSION['current_search'][$page]['location'] == $location;
		
		$xml = "<HotelListRequest>";
		$xml .= "<cacheKey>" . $key . "</cacheKey>";
		$xml .= "<cacheLocation>" . $location . "</cacheLocation>";
		$xml .= "</HotelListRequest>";
		
		$results = $this->make_xml_request('list', $request);
		return $results;
	}
	
	/**********************
	* Function: getRooms 
	* Args: infoArray -> key-value pairs to pass to expedia
	*
	*	Returns: Object of class SimpleXMLElement containing all rooms available 
  *           for booking at the time of the request. Also contains certain 
  *           required values that must be used later
	*		Returned XML Object detailed here: 
	* 	http://developer.ean.com/docs/read/hotels/version_3/request_hotel_rooms/Response_Format
	*/
	public function getRooms($infoArray){
		$destination = $infoArray['city'];
		$check_in = $infoArray['checkIn'];
		$check_out = $infoArray['checkOut'];
		$rooms = $infoArray['numberOfRooms'];
		$hotel_id = $infoArray['hotel_id'];
	
    // Check if the checkin and checkout dates are valid dates
	  if(!preg_match('/^(0[1-9]|1[0-2])\/(0[1-9]|[1-2][0-9]|3[0-1])\/[0-9]{4}$/', $check_in) 
	  || !preg_match('/^(0[1-9]|1[0-2])\/(0[1-9]|[1-2][0-9]|3[0-1])\/[0-9]{4}$/', $check_out)){
		  $results['error'] = "Your dates could not be validated";
		  print "error 2";
		  return $results;
	  }
	
    // Begin defining the XML request
		$xml = '<HotelRoomAvailabilityRequest>';
		$xml .= '<hotelId>'. $hotel_id .'</hotelId>';
		$xml .= '<arrivalDate>'. $check_in .'</arrivalDate>';
		$xml .= '<departureDate>'. $check_out .'</departureDate>';
		$xml .= '<numberOfBedRooms>'. $rooms .'</numberOfBedRooms>';
		$xml .= '<supplierType>E</supplierType>';

		//if($infoArray['supplierType'])
		//	$xml .= '<supplierType>'.$infoArray['supplierType'].'</supplierType>';

    // If optional rateKey is passed in arg array
		if($infoArray['rateKey'])
			$xml .= '<rateKey>' . $infoArray['rateKey'] . '</rateKey>';
		
    // By default include hotel details
		$xml .= '<includeDetails>true</includeDetails>';
		
    // If optional rateCode or roomTypeCode is passed in arg array
		if($infoArray['rateCode'])
			$xml .= '<rateCode>'.$infoArray['rateCode'].'</rateCode>';
		if($infoArray['roomTypeCode'])
			$xml .= '<roomTypeCode>'.$infoArray['roomTypeCode'].'</roomTypeCode>';
		
		// Set Up Room object
		$xml .= '<RoomGroup>';
			for($i=0;$i<$rooms;$i++){
				$xml .= "<Room>";
				$xml .= "<numberOfAdults>" . $infoArray['room-'.$i.'-adult-total'] . "</numberOfAdults>";
				if(intval($infoArray['room-'.$i.'-child-total']) > 0){
					$xml .= "<numberOfChildren>" . $infoArray['room-'.$i.'-child-total'] . "</numberOfChildren>";
					$xml .= "<childAges>";
					for($j=0;$j<$infoArray['room-'.$i.'-child-total'];$j++){
						if($j == 0)
							$childAgesStr = $infoArray['room-'.$i.'-child-'.$j.'-age'];
						else
							$childAgesStr .= ','. $infoArray['room-'.$i.'-child-'.$j.'-age'];
					}
					$xml .= $childAgesStr;
					$xml .= "</childAges>";
				}
				if($infoArray['room-'.$i.'-bedType'])
					$xml .= "<bedTypeId>".$infoArray['room-'.$i.'-bedType']."</bedTypeId>";
				if($infoArray['room-'.$i.'-smokingPreference'])
					$xml .= "<smokingPreference>".$infoArray['room-'.$i.'-smokingPreference']."</smokingPreference>";
				
				$xml .= "</Room>";
			}
		$xml .= '</RoomGroup>';
		//-- Room Obj
		
		$xml .= '</HotelRoomAvailabilityRequest>';
	
		$results = $this->make_xml_request('avail', $xml);
		return $results;
	}
	
	/**********************
	* Function: getReservation 
	* Args: infoArray -> key-value pairs to pass to expedia
	*
	*	Returns: Object of class SimpleXMLElement containing itinerary and
  *         confirmation codes, amongst reservation other information
	*		Returned XML Object detailed here: 
	* 	http://developer.ean.com/docs/read/hotels/version_3/book_reservation/Response_Format
	*/
	public function getReservation($infoArray){
		$rooms = intval($infoArray['numberOfRooms']);
	
    // Begin defining XML request string
		$xml = "<HotelRoomReservationRequest>";
		$xml .= "<hotelId>" . $infoArray['hotel_id'] . "</hotelId>";
		$xml .= "<arrivalDate>". $infoArray['checkIn'] . "</arrivalDate>";
		$xml .= "<departureDate>" . $infoArray['checkOut'] . "</departureDate>";
		$xml .= "<supplierType>E</supplierType>";
		$xml .= "<rateKey>" . $infoArray['rateKey'] . "</rateKey>";
		$xml .= "<roomTypeCode>" . $infoArray['roomTypeCode'] . "</roomTypeCode>";
		$xml .= "<rateCode>" . $infoArray['rateCode'] . "</rateCode>";
		$xml .= "<chargeableRate>" . $infoArray['chargeableRate'] . "</chargeableRate>";
		
		// Set Up Room object
		$xml .= '<RoomGroup>';
			for($i=0;$i<$rooms;$i++){
				$xml .= "<Room>";
				$xml .= "<numberOfAdults>" . $infoArray['room-'.$i.'-adult-total'] . "</numberOfAdults>";

        // If children were passed, set up appropriate XML vars
				if(intval($infoArray['room-'.$i.'-child-total']) > 0){
					$xml .= "<numberOfChildren>" . $infoArray['room-'.$i.'-child-total'] . "</numberOfChildren>";
					$xml .= "<childAges>";
					for($j=0;$j<$infoArray['room-'.$i.'-child-total'];$j++){
						if($j == 0)
							$childAgesStr = $infoArray['room-'.$i.'-child-'.$j.'-age'];
						else
							$childAgesStr .= ','. $infoArray['room-'.$i.'-child-'.$j.'-age'];
					}
					$xml .= $childAgesStr;
					$xml .= "</childAges>";
				}else{
				  $xml .= "<numberOfChildren>0</numberOfChildren>";
				}
				
				$xml .= "<firstName>".$infoArray['room-'.$i.'-firstName']."</firstName>";
				$xml .= "<lastName>".$infoArray['room-'.$i.'-lastName']."</lastName>";
				$xml .= "<bedTypeId>".$infoArray['room-'.$i.'-bedType']."</bedTypeId>";
				
				// If a room smoking preference was passed
				if($infoArray['room-'.$i.'-smokingPreference'])
					$xml .= "<smokingPreference>".$infoArray['room-'.$i.'-smokingPreference']."</smokingPreference>";
				
				$xml .= "</Room>";
			}
		$xml .= '</RoomGroup>';
		//-- End Room Obj
		
		// Set up ReservationInfo Obj
		$xml .= "<ReservationInfo>";
		$xml .= "<email>".$infoArray['contact_email']."</email>";
		$xml .= "<firstName>".$infoArray['cardFirstName']."</firstName>";
		$xml .= "<lastName>".$infoArray['cardLastName']."</lastName>";
		$xml .= "<homePhone>".$infoArray['contact_phone']."</homePhone>";

    // If a work-phone was passed
		if(!empty($infoArray['contact_workPhone']))
			$xml .= "<workPhone>".$infoArray['contact_workPhone']."</workPhone>";
			
		$xml .= "<creditCardType>".$infoArray['cardType']."</creditCardType>";
		$xml .= "<creditCardNumber>".$infoArray['cardNumber']."</creditCardNumber>";
		$xml .= "<creditCardIdentifier>".$infoArray['cardSecurity']."</creditCardIdentifier>";
		$xml .= "<creditCardExpirationMonth>".$infoArray['cardExpMonth']."</creditCardExpirationMonth>";
		$xml .= "<creditCardExpirationYear>".$infoArray['cardExpYear']."</creditCardExpirationYear>";
		$xml .= "</ReservationInfo>";
		//-- End ResInfo Obj
		
		// Set up Address Info Obj
		$xml .= "<AddressInfo>";
		$xml .= "<address1>".$infoArray['billing_address']."</address1>";
		$xml .= "<city>".$infoArray['billing_city']."</city>";
		$xml .= "<stateProvinceCode>".$infoArray['billing_state']."</stateProvinceCode>";
		$xml .= "<countryCode>".$infoArray['billing_country']."</countryCode>";
		$xml .= "<postalCode>".$infoArray['billing_postalCode']."</postalCode>";
		$xml .= "</AddressInfo>";
		//-- End AddressInfo Obj

		$xml .= "</HotelRoomReservationRequest>";
			
		$results = $this->make_res_xml_request($xml);
		return $results;
	}
	
	/**********************
	* Function: getReservation 
	* Args: infoArray -> key-value pairs to pass to expedia
	*
	*	Returns: Object of class SimpleXMLElement containing all details about 
            individual itineraries or bookings. Also reveals the status of 
            a reservation
	*		Returned XML Object detailed here: 
	* 	http://developer.ean.com/docs/read/hotels/version_3/request_itinerary/Itinerary_Response
	*/
	public function getItinerary($infoArray){
		$xml = "<HotelItineraryRequest>";
		$xml .= "<itineraryId>" . $infoArray['itineraryID'] . "</itineraryId>";
		if(!empty($infoArray['email']))
			$xml .= "<email>" . $infoArray['email'] . "</email>";
		if(!empty($infoArray['lastName']))
			$xml .= "<lastName>" . $infoArray['lastName'] . "</lastName>";
		
		$xml .= "</HotelItineraryRequest>";
		
		$results = $this->make_xml_request('itin', $xml);
		return $results;
	} 
}
