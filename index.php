<?php
	
	header('Content-Type: application/rss+xml; charset=utf-8'); // See: https://www.carronmedia.com/create-an-rss-feed-with-php/#Creating-The-Feed
	
	/* 
	
	Brightcove decided to discontinue an RSS API via the Media API
	that powered video in our apps. This is an attempt to re-create that, based off of: 
	https://docs.brightcove.com/en/video-cloud/cms-api/samples/mrss-generator.html
	
	Basically, I took what they did in JavaScript and made it in PHP so the app could get at it.
	It was not fun.
	
	This code is somewhat complex because Brightcove's new API requires
	you to go get an access token via a proxy and then go out and make
	your query, each time you want to hit the API.
	
	1) Go to proxy, get last x videos (this is determined by URL param,
		defaults to 10, could go to 25)
	2) Loop over those videos and go get the source url
	3) Put all that data into a handy array
	4) Do the RSS and loop over the handy array
	
	*/
	
	// Function to clean URLs so that it'll validate
	// https://validator.w3.org/feed/check.cgi
	function clean($link){
		$link = str_replace("&","%26",$link);
		$link = str_replace("=","%3D",$link);
		return $link;
	}
	
	// Set up vars
	$now = gmdate("D, d M Y H:i:s O"); // Current time for feed pub time, see: http://php.net/manual/en/function.gmdate.php, see: http://php.net/manual/en/class.datetime.php
	$myVids = array(); // For creating an array of my own data to loop over in the template below
	$proxy = 'URL/TO/YOUR/proxy.php';
	$count = !empty($_GET['count']) && is_string($_GET['count']) ? $_GET['count'] : '10'; // Default to 10
	$tags = $_GET['tags'];
	if(!empty($tags)){
		$tags = '&q=%2Btags%3D' . $tags; // Proper formatting, encoded this: q=+tags="oregon","football","sports"
	} else { $tags = ''; }
	$urlBase = 'https://cms.api.brightcove.com/v1/accounts/[YOUR ACCOUNT ID]/videos';
	// This API is read-only so I just put these in here.
	// Very not secure but I don't see an alternative right now.
	$clientId = '[YOUR CLIENT ID]';
	$clientSec = '[YOUR CLIENT SECRET]';
	
	// This build the request body for the proxy to send to Brightcove
	$data = http_build_query(
		array(
			'url' => $urlBase . '?sort=-updated_at&limit=' . $count . '&offset=0' . $tags,
			'requestType' => 'GET',
			'client_id' => $clientId,
			'client_secret' => $clientSec
		)
	);
	
	$options = array(
		'http' => array(
			'header'  => 'Content-type: application/x-www-form-urlencoded',
			'method' => 'POST',
			'content' => $data
		)
	);
	
	$context = stream_context_create($options);
	
	try {
		$result = file_get_contents($proxy, false, $context);
		if ($result === false){
			echo("false");
		}
	} catch (Exception $e) {
		echo($e);
	}
	//echo $result;
	
	$parsedData = json_decode($result, true);
	//var_dump($parsedData);
	
	$loop = 0;
	$myVids = array();
	
	foreach($parsedData as $key => $value){
		
		if ($loop < $count) {
			
			// Set up all vars in new, handly $single var (except src, that comes later)
			$single = array();
			$single['title'] = $value['name'];
			$single['desc'] = $value['description'];
			$single['guid'] = $value['id'];
			$blah = strtotime($value['published_at']); // See: http://php.net/manual/en/function.strcmp.php
			$blah2 = gmdate("D, d M Y H:i:s O", $blah); // See: http://php.net/manual/en/function.gmdate.php, see: http://php.net/manual/en/class.datetime.php
			$single['pubDate'] = $blah2;
			$single['dur'] = $value['duration'];
			$single['src'] = ""; // Get this below
			$single['thumb'] = clean($value['images']['thumbnail']['src']);
			$single['poster'] = clean($value['images']['poster']['src']);
			
			// ---
			
			// Get src
			$urlVid = $urlBase . "/" . $value['id']. "/sources";
			
			$dataVid = http_build_query(
				array(
					'url' => $urlVid,
					'requestType' => 'GET',
					'client_id' => $clientId,
					'client_secret' => $clientSec
				)
			);
			
			$optionsVid = array(
				'http' => array(
					'header'  => 'Content-type: application/x-www-form-urlencoded',
					'method' => 'POST',
					'content' => $dataVid
				)
			);
			
			$contextVid = stream_context_create($optionsVid);
			
			try {
				$resultVid = file_get_contents($proxy, false, $contextVid);
				if ($resultVid === false){
					echo("vid false");
				}
			} catch (Exception $e) {
				echo($e);
			}
			
			$parsedVid = json_decode($resultVid, true);
			
			// ---
			
			// Get largest video possible, see test3.php for more
			// Set up new array to store video source urls, then sort array and get first one, which should be the biggest (the key is equal to the width of the video)
			$sizes = array();
			foreach ($parsedVid as $source) {
				// Only want videos of this kind
				if (($source['container'] === 'MP4') && (strpos($source['src'], 'brightcove.vo.llnwd.net') == true)) {
					$sizes[$source['width']] = $source['src'];
				}
			}
			// See: http://php.net/manual/en/function.krsort.php
			krsort($sizes); 
			// See: http://stackoverflow.com/a/1028677
			$single['src'] = clean(reset($sizes));
			
			// ---
			
			// Add to $myVids array, see: http://php.net/manual/en/function.array-push.php
			array_push($myVids, $single);
			
		}
		
		$loop++;
		
	}
	
?>
<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/" xmlns:dcterms="http://purl.org/dc/terms/">
	<channel>
		<title>[TITLE]</title>
		<link>[LINK TO YOUR INDEX.PHP]</link>
		<description/>
		<lastBuildDate><?=$now?></lastBuildDate>
		<language>en-us</language>
<?php // See: http://www.php.net/manual/en/control-structures.alternative-syntax.php ?>
<?php foreach($myVids as $vid): ?>
		<item>
			<title><?=$vid['title']?></title>
			<link><?=$vid['src']?></link>
			<description><?=$vid['desc']?></description>
			<guid><?=$vid['src']?></guid>
			<pubDate><?=$vid['pubDate']?></pubDate>
			<media:content duration="<?=$vid['dur']?>" medium="video" type="video/mp4" url="<?=$vid['src']?>"/>
			<media:keywords/>
			<media:thumbnail height="90" url="<?=$vid['thumb']?>" width="120"/>
			<media:thumbnail height="360" url="<?=$vid['poster']?>" width="480"/>
		</item>
<?php endforeach; ?>
	</channel>
</rss>