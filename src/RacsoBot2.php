<?php

class RacsoBot2{

	private $snoopy;
	private $api_url;

	function __construct()
	{
		require_once('Snoopy.class.php');
        $this->snoopy = new Snoopy();
	}

	public function callAPI($vars)
	{
		$vars['format'] = 'php';
		$this->snoopy->submit($this->api_url, $vars);
		return unserialize($this->snoopy->results);
	}

	function setCookies()
	{
		$this->snoopy->setcookies();
	}

	function login($user, $pass, $lang = 'es', $project = 'wikipedia')
	{
		$this->api_url = "https://$lang.$project.org/w/api.php";

		$request_vars = array('action'=>'query', 'meta'=>'tokens', 'type'=>'login');
		$result = $this->callAPI($request_vars);

		$this->setCookies();
		$loginToken = $result['query']['tokens']['logintoken'];

		$request_vars = array('action'=>'login', 'lgname'=>$user, 'lgpassword'=>$pass, 'lgtoken'=>$loginToken);
		$result = $this->callAPI($request_vars);
		return $result['login']['result'] == "Success";
	}

	function get($page)
	{
		$request_vars = array('action'=>'query', 'prop'=>'revisions', 'titles'=>$page, 'format'=>'php', 'rvprop'=>'content');
		$results = $this->callAPI($request_vars);

		if ($results==false)
		{
			exit("Error al intentar cargar la página $page con el API $this->api_url");
		}
		if (isset($results["query"]["pages"][-1]["missing"]) || isset($results["query"]["pages"][-1]["invalid"]))
		{
			return false;
		}

		return $results["query"]["pages"][key( $results["query"]["pages"] )]["revisions"][0]["*"];
	}

}

?>