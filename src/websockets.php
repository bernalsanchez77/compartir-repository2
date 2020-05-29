<?php
$host = '0.0.0.0';
$port = '60000';
$null = NULL;

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, 0, $port);
socket_listen($socket);
socket_set_nonblock ($socket);
$clients = array($socket);

while (true) {
	// sleep(2);
	$clients_copy = $clients;
	socket_select($clients_copy, $null, $null, 0);

	//removemos el socket del client_copy porque luego da problemas
	$socket_copy = array_search($socket, $clients_copy);
	unset($clients_copy[$socket_copy]);

	$nuevo_socket = socket_accept($socket);

	if ($nuevo_socket) {
	  $clients[] = $nuevo_socket;
	  print_r('socket anadido: '. $nuevo_socket);
	  echo "\r\n";
	  print_r('totales: '. count($clients));
		echo "\r\n";
		foreach($clients as $client) {
			printf($client);
			echo "\r\n";
	  }
		$header = socket_read($nuevo_socket, 1024);
		perform_handshaking($header, $nuevo_socket, $host, $port);
		socket_getpeername($nuevo_socket, $ip);
		$clientId = sprintf('%x', $nuevo_socket);
		$response = mask(json_encode(array('type'=>'system', 'message'=>'connected_register', 'clientId'=>$clientId)));
		send_message_one($response, $nuevo_socket);
		$response = mask(json_encode(array('type'=>'system', 'message'=>'connected', 'clientId'=>$clientId)));
		send_message_all($response);
	}

	foreach ($clients_copy as $connection) {
		if(socket_recv($connection, $buf, 1024, 0) >= 1) {
			$tst_msg = json_decode(unmask($buf), true);
			if ($tst_msg) {
		  	if (array_key_exists('videoId', $tst_msg)) {
        	print_r('se mando video');
	        echo "\r\n";
			  	$response_text = mask(json_encode(array('type'=>'usermsg', 'videoId'=>$tst_msg['videoId'])));
			  	send_message_all($response_text);
	  	  	} elseif (array_key_exists('videoReady', $tst_msg)) {
	  			array_push($usersReady,$tst_msg['name']);
        	print_r('users:');
        	print_r(count($clients));
	        echo "\r\n";
        	print_r('users ready:');
        	print_r(count($usersReady) + 1);
	        echo "\r\n";
			    if ((count($usersReady) + 1) == count($clients)) {
			  		$response_text = mask(json_encode(array('type'=>'usermsg', 'videoReady'=>$tst_msg['videoReady'])));
				  	send_message_all($response_text);
				  }
			  } elseif (array_key_exists('videoSearch', $tst_msg)) {
        	print_r('se pidio video');
	        echo "\r\n";
			  	$results = search_video($tst_msg['videoSearch']);
			  	$response_text = mask(json_encode($results));	
				  send_message_one($response_text, $connection);
			  } elseif (array_key_exists('videoClear', $tst_msg)) {
        	print_r('se mando a borrar el array');
	        echo "\r\n";
			    $usersReady = array();
			  } else {
				  $response_text = mask(json_encode(array('type'=>'usermsg', 'name'=>$tst_msg['name'], 'message'=>$tst_msg['message'])));
				  send_message_all($response_text);
			  }
		  }  
	 		//break 2;
		}
		
	 	$buf = @socket_read($connection, 1024, PHP_NORMAL_READ);
		 if ($buf === false) { // check disconnected client
			$found_socket = array_search($connection, $clients);
			print_r('se desconecto: ' . $clients[$found_socket]);
			echo "\r\n";
			$clientId = sprintf('%x', $clients[$found_socket]);
	 	  socket_getpeername($connection, $ip);
			unset($clients[$found_socket]);
	 	  $response = mask(json_encode(array('type'=>'system', 'message'=>'disconnected', 'clientId'=>$clientId)));
	 	  send_message_all($response);
		}
	}







}
//socket_close($socket);

function search_video($video_query) {
	if ($video_query) {
    $apikey = 'AIzaSyDsJ7PrDsb3RTLPj8660jplhZ7vDCPEWcY';
		try {
			$googleApiUrl = 'https://www.googleapis.com/youtube/v3/search?part=snippet&q=' . $video_query . '&maxResults=' . '20' . '&key=' . $apikey;
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_URL, $googleApiUrl);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_VERBOSE, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			$response = curl_exec($ch);
			curl_close($ch);
			$data = json_decode($response);
			$value = json_decode(json_encode($data), true);
			$videos = '';
			$channels = '';
	
			foreach ($value['items'] as $searchResult) {
				switch ($searchResult['id']['kind']) {
					case 'youtube#video':
						$videos .= sprintf(
							'
							  <li>
							    <span>%s</span>
							    <span style="font-size:12px">(%s)</span>
									<div>
									  <span class="video-result-all" style="color:blue; cursor: pointer; padding-right: 30px" data-id="%s">Reproducir video para todos</span>
										<span class="video-result-one" style="color:blue; cursor: pointer;" data-id="%s">Reproducir video para mi</span>
									</div>
							  </li>
							',
							 $searchResult['snippet']['title'],
							 $searchResult['snippet']['description'],
							 $searchResult['id']['videoId'],
							 $searchResult['id']['videoId']
						);
						break;
					case 'youtube#channel':
						$channels .= sprintf('<li>%s (%s)</li>', $searchResult['snippet']['title'],
							$searchResult['id']['channelId']);
						break;
				 }
			}
	
		 } catch (Google_ServiceException $e) {
			$htmlBody .= sprintf('<p>A service error occurred: <code>%s</code></p>',
				htmlspecialchars($e->getMessage()));
		} catch (Google_Exception $e) {
			$htmlBody .= sprintf('<p>An client error occurred: <code>%s</code></p>',
				htmlspecialchars($e->getMessage()));
		}
	}
	return array('type'=>'usermsg', 'videoResults'=>$videos);
}

function send_message_all($msg) {
	global $clients;
	foreach($clients as $client)
	{
		@socket_write($client,$msg,strlen($msg));
	}
	return true;
}

function send_message_one($msg, $client) {
	@socket_write($client,$msg,strlen($msg));
	return true;
}

function unmask($text) {
	$length = ord($text[1]) & 127;
	if($length == 126) {
		$masks = substr($text, 4, 4);
		$data = substr($text, 8);
	}
	elseif($length == 127) {
		$masks = substr($text, 10, 4);
		$data = substr($text, 14);
	}
	else {
		$masks = substr($text, 2, 4);
		$data = substr($text, 6);
	}
	$text = "";
	for ($i = 0; $i < strlen($data); ++$i) {
		$text .= $data[$i] ^ $masks[$i%4];
	}
	return $text;
}

function mask($text) {
	$b1 = 0x80 | (0x1 & 0x0f);
	$length = strlen($text);
	
	if($length <= 125)
		$header = pack('CC', $b1, $length);
	elseif($length > 125 && $length < 65536)
		$header = pack('CCn', $b1, 126, $length);
	elseif($length >= 65536)
		$header = pack('CCNN', $b1, 127, $length);
	return $header.$text;
}

function perform_handshaking($receved_header,$client_conn, $host, $port) {
	$headers = array();
	$lines = preg_split("/\r\n/", $receved_header);
	foreach($lines as $line)
	{
		$line = chop($line);
		if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
		{
			$headers[$matches[1]] = $matches[2];
		}
	}

	$secKey = $headers['Sec-WebSocket-Key'];
	$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
	//hand shaking header
	$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
	"Upgrade: websocket\r\n" .
	"Connection: Upgrade\r\n" .
	"WebSocket-Origin: $host\r\n" .
	"WebSocket-Location: ws://$host:$port/demo/shout.php\r\n".
	"Sec-WebSocket-Accept:$secAccept\r\n\r\n";
	socket_write($client_conn,$upgrade,strlen($upgrade));
}
?>
