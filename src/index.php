<!DOCTYPE html>
<html>
  <body>
    <div id="player"></div>
    <div>
      <input type="text" id="videoInput"/>
      <button id="send-video">Video</button>
    </div>
    <div>
      <input type="text" id="visssssdeoSearch"/>
      <button id="send-search">Search</button>
    </div>
    <div class="id-box"></div>
    <div id="message-box"></div>
    <div>
      <input type="text" id="name"/>
      <input type="text" id="message"/>
      <button id="send-message">Send</button>
    </div>
		<div>
      <ul id="results-list">
			</ul>
		</div>

<script>
	var msgBox = document.querySelector('#message-box');
	var wsUri = "ws://34.70.115.44:60000/websockets.php"; 	
	var websocket = new WebSocket(wsUri); 
	var pausa = false;
  var msgVideo = {};
  var tag = document.createElement('script');
  tag.src = "http://www.youtube.com/iframe_api";
  var firstScriptTag = document.getElementsByTagName('script')[0];
  firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
  var player;
  var clientId;
	websocket.onopen = function(ev) {
	}

	websocket.onmessage = function(ev) {
		var response 		= JSON.parse(ev.data);

		switch(response.type){
			case 'usermsg':
			  if (response.videoId) {
				  load_video(response.videoId);
				} else if (response.videoReady) {
					play_video();
				} else if (response.videoResults) {
					document.querySelector('#results-list').innerHTML = response.videoResults;
			  } else {
			    // player.cueVideoById(response.message);
				  show_in_chat(response.name, response.message);
				}
				break;
			case 'system':
			  if (response.message == 'connected') {
			    register_user(response.clientId);
				 include_user(response.clientId);
				} else if (response.message == 'disconnected') {
			    unregister_user(response.clientId);
					exclude_user(response.clientId);
				}
				break;
		}
	};
	
	// websocket.onerror	= function(ev){ msgBox.append('<div>Error Occurred - ' + ev.data + '</div>'); }; 
	// websocket.onclose 	= function(ev){ msgBox.append('<div>Connection Closed</div>'); }; 

	document.querySelector('#send-message').addEventListener("click", send_message);

	document.querySelector('#send-video').addEventListener("click", send_video);

	document.querySelector('#send-search').addEventListener("click", send_search);

	document.addEventListener("click", function(e){
    if(e.target && e.target.className == 'video-result-all'){
			pausa = true;
			send_video(e.target.dataset.id);
    }
	});
	
	//Functions
	function onYouTubeIframeAPIReady() {
    player = new YT.Player('player', {height: '360', width: '640', videoId: 'wkLsFY-zCRI', events: {'onReady': onPlayerReady,'onStateChange': onPlayerStateChange}});
  }

  function onPlayerReady(event) {event.target.playVideo();}

  function onPlayerStateChange(e) {
    if (pausa && e.data == 1) {
			player.pauseVideo();
		  setTimeout(() => {
				websocket.send(JSON.stringify({videoReady: true, name: document.querySelector('#name').value}));		
			}, Math.floor(Math.random() * 1000));
			pausa = false;
		}
  }

	function send_message(){	
		websocket.send(JSON.stringify({message: document.querySelector('#message').value, name: document.querySelector('#name').value}));	
		document.querySelector('#message').value = '';
	}

	function send_video(video){
		clear_users_ready();
	  if (!video) {
			video = document.querySelector('#videoInput').value;
		}
		setTimeout(() => {
			websocket.send(JSON.stringify({videoId: video}));	
		}, 1000);
		document.querySelector('#videoInput').value = '';
	}

	function send_search(){
		websocket.send(JSON.stringify({videoSearch: document.querySelector('#videoSearch').value}));	
		document.querySelector('#videoSearch').value = '';
	}

	function load_video(video){
		pausa = true;
		player.loadVideoById(video);
	}

	function play_video(){
		pausa = false;
		setTimeout(() => {
			player.playVideo();
		}, 2000);
	}

	function show_in_chat(name, message){
		var text = document.createTextNode(response.name + ': ' + response.message);
    var divEl = document.createElement("DIV");
    divEl.appendChild(text);  
		msgBox.appendChild(divEl);
	}

	function register_user(id){
		clientId = id;
	}

	function include_user(id){
		var text = document.createTextNode('usuario: ' + id + ' conectado');
    var divEl = document.createElement("DIV");
    divEl.appendChild(text);
    document.querySelector('.id-box').appendChild(divEl); 
	}

	function unregister_user(id){
		//borrar usuario
	}

	function exclude_user(id){
		var text = document.createTextNode('usuario: ' + id + ' desconectado');
    var divEl = document.createElement("DIV");
    divEl.appendChild(text);
    document.querySelector('.id-box').appendChild(divEl); 
	}

	function clear_users_ready(){
		websocket.send(JSON.stringify({videoClear: true}));
	}
</script>

</body>
</html>
