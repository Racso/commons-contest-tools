<?php

class RacsoBot2{

	private $snoopy;
	private $api_url;
	private $editToken = false;
	private $blockToken= false;
	private $deleteToken = false;
        private $isBot = 1;

	function __construct() {
		require_once( 'Snoopy.class.php' );
		$this->snoopy = new Snoopy;
	}

	function login($user, $pass, $lang = 'es', $project = 'wikipedia'){
		$this->api_url = "https://$lang.$project.org/w/api.php";
		$request_vars = array( 'action' => 'login', 'lgname' => $user, 'lgpassword' => $pass, 'format' => 'php' );
		if ( !$this->snoopy->submit( $this->api_url, $request_vars ) ) die( "Snoopy error1: ".$this->snoopy->error);

		$this->snoopy->setcookies();
		$array_ = unserialize( $this->snoopy->results );
		if ($array_['login']['result'] == "Success") {
			//Servidor sin parche. Ya est� listo el login (ver bug #23076, abril de 2010).
			return true;
		}
		elseif ($array_['login']['result'] == "NeedToken") {
			//Servidor con parche. Obtener token y usarlo para el login.

			$request_vars['lgtoken'] = $array_['login']['token'];
			if ( !$this->snoopy->submit( $this->api_url, $request_vars ) ) die( "Snoopy error2: ".$this->snoopy->error);
			$this->snoopy->setcookies();
			$array_ = unserialize( $this->snoopy->results );
			if ($array_['login']['result'] == "Success") {
				return true;
			}
			else {
return false;
				/* //Esperar e intentar de nuevo.
				sleep(10);
				$this->login($user, $pass, $lang, $project); */
			}
		}
		else {
			if (isset($array_['login']['wait']) || (isset($array_['error']['code']) && $array_['error']['code'] == "maxlag")) {
				/* //Esperar e intentar de nuevo.
				sleep(10);
				$this->login($user, $pass, $lang, $project); */
return false;
			}
			else {
return false;
				/* die("Login failed: " . $response . "\r<br />\n");*/
			}
		}
	}

        function setBot($val){
            if ($val) $this->isBot=1; else $this->isBot=0;
        }
        
	function get($page){
		$request_vars = array('action'=>'query','prop'=>'revisions','titles'=>$page,'format'=>'php','rvprop'=>'content');
		$this->snoopy->submit( $this->api_url, $request_vars );
		$array_ = unserialize( $this->snoopy->results );

		if ( $array_==false ){
			//Error al solicitar la p�gina.
			die("Error al intentar cargar la p�gina ".$page. " con el API ".$this->api_url);
		}
		if (isset( $array_["query"]["pages"][ - 1]["missing"] )){
			//La p�gina no existe.
			return false;
		}
		elseif (isset( $array_["query"]["pages"][ - 1]["invalid"] )){
			//La p�gina no existe.
			return false;
		}

		return $array_["query"]["pages"][key( $array_["query"]["pages"] )]["revisions"][0]["*"];
	}

	function put($page, $text, $summary, $minor = 0){
		if ($this->editToken==false) {
			$request_vars = array('action' => 'query', 'prop' => 'info', 'intoken' => 'edit','titles' => $page,'format' => 'php');
			if(!$this->snoopy->submit($this->api_url, $request_vars)) die("Snoopy error: ".$this->snoopy->error);
			$array_ = unserialize($this->snoopy->results);
			$this->editToken = $array_["query"]["pages"][key($array_["query"]["pages"])]["edittoken"];
		}

		$request_vars = array('token'=> $this->editToken, 'format' => 'php', 'action' => 'edit',
			'minor' => $minor, 'bot' => $this->isBot, 'title' => $page, 'summary' => $summary, 'text' => $text );

		if(!$this->snoopy->submit($this->api_url,$request_vars))
		{
			die("Snoopy error: ".$this->snoopy->error);

		}
		$array_ = unserialize($this->snoopy->results);

		if($array_["edit"]["result"]=="Success")
			return true;
		else
			return false;
	}

	function block($user, $reason = "", $duration = "31 hours"){
		if ($this->blockToken==false) {
			$request_vars = array('action' => 'query', 'prop' => 'info', 'intoken' => 'block','titles' => "User:$user",'format' => 'php');
			if(!$this->snoopy->submit($this->api_url, $request_vars)) die("Snoopy error: ".$this->snoopy->error);
			$array_ = unserialize($this->snoopy->results);
			$this->blockToken = $array_["query"]["pages"][key($array_["query"]["pages"])]["blocktoken"];
		}

		$request_vars = array('token'=> $this->blockToken, 'format' => 'php', 'action' => 'block',
			'user' => $user, 'reason' => $reason, 'expiry' => $duration, 'nocreate' => '', 'autoblock' => '', 'bot' => $this->isBot);

		if(!$this->snoopy->submit($this->api_url,$request_vars))
		{
			die("Snoopy error: ".$this->snoopy->error);

		}
		$array_ = unserialize($this->snoopy->results);

		return $array_;
	}

	function getUserContribs($user){
		$request_vars = array('format' => 'php', 'action' => 'query',
			'list' => 'usercontribs', 'ucuser' => $user, 'uclimit' => 5000);

		if(!$this->snoopy->submit($this->api_url,$request_vars))
		{
			die("Snoopy error: ".$this->snoopy->error);
		}

		return unserialize($this->snoopy->results);

	}

	function delete($page, $reason){
		if ($this->deleteToken==false) {
			$request_vars = array('action' => 'query', 'prop' => 'info', 'intoken' => 'delete','titles' => $page,'format' => 'php');
			if(!$this->snoopy->submit($this->api_url, $request_vars)) die("Snoopy error: ".$this->snoopy->error);
			$array_ = unserialize($this->snoopy->results);
			$this->deleteToken = $array_["query"]["pages"][key($array_["query"]["pages"])]["deletetoken"];
		}

		$request_vars = array('token'=> $this->deleteToken, 'format' => 'php', 'action' => 'delete',
		'title' => $page, 'reason' => $reason, 'bot' => $this->isBot);

		if(!$this->snoopy->submit($this->api_url,$request_vars))
		{
			die("Snoopy error: ".$this->snoopy->error);

		}
		$array_ = unserialize($this->snoopy->results);
		return $array_;
	}

        function callAPI($request_vars){
                $request_vars['format'] = 'json';
		$this->snoopy->submit( $this->api_url, $request_vars );

		$array_ = json_decode ( $this->snoopy->results, true );
                return $array_;
	}
        

}

?>