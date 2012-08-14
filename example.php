<?php
	require_once('ean.class.new.php');

	$search = new EAN_API();
	
  	$infoArray['city'] = "San Diego";
    $infoArray['checkIn'] = "05/08/2013";
    $infoArray['checkOut'] = "05/13/2013";
	$infoArray['numberOfRooms'] = 1;
	$infoArray['room-0-adult-total'] = 2;
	$infoArray['room-0-child-total'] = 0;
		
	$result = $search->getHotels($infoArray);
	print_r($result);
?>
