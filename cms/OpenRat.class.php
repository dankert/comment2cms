<?php


class OpenRat {

	public $text = '';
	public $subject;
	public $pageId;
	public $name;
	public $debug = true;
	public $timestamp;
	public $config;
	
	private $client;
	
	private function request( $method,$parameter )
	{
		 
		$this->client->parameter = $parameter;
		$this->client->method    = $method;
		$this->client->request();
		
		if	( $this->client->status != '200' || $this->debug)
		{
			echo '<span style="background-color:'.($this->client->status=='200'?'green':'red').'">HTTP-Status '.$this->client->status.'</span>';
			echo "<h4>".$parameter['action'].'/'.$parameter['subaction'].'</h4>';
			?><pre><?php print_r(""); ?></pre><pre><?php print_r($this->client->response); ?></pre><?php
		}
				
		$response = json_decode($this->client->response,true);
		
		if	( $response == null )
		{
			echo '<span style="background-color:red">Kein JSON: <pre>'.htmlentities($this->client->response).'</pre></span>';
			exit;
		}
			
		return $response;
	}
	
	
	public function push()
	{
		$filesToPublish   = array();
		require_once('./cms/openrat/OpenratClient.php');
		$this->client = new OpenratClient();

		$this->client->host   = $this->config['host'];
		$this->client->port   = $this->config['port'];
		$this->client->path   = $this->config['path'];
		$this->client->type ="application/json";
		
		
		$response = $this->request( 'GET',
			array('action'   =>'login',
			      'subaction'=>'login') );
			
		$token = $response['session']['token'];
		$this->client->cookie =$response['session']['name'].'='.$response['session']['id'];
		
		
		$response = $this->request( 'POST', array(
			'action'        => 'login',
			'subaction'     => 'login',
			'token'         => $token,
			'dbid'          => $this->config['database'],
			'login_name'    => $this->config['user'    ],
			'login_password'=> $this->config['password'] ) );
	
		$this->client->cookie =$response['session']['name'].'='.$response['session']['id'];
		$token = $response['session']['token'];
	
		
		// Projekt auswählen
		$response = $this->request( 'POST', array(
				'action'        => 'start',
				'subaction'     => 'projectmenu',
				'token'         => $token,
				'id'            => $this->config['projectid']) );
		
		// Seite laden.
		$responsePage = $this->request( 'GET', array
			(
					'action'        => 'page',
					'subaction'     => 'info',
					'id'            => $this->pageId,
					'token'         => $token
			) );
		
		// Inhalt laden und nachschauen, ob es schon einen Kommentare-Ordner gibt.
		$responseLink = $this->request( 'GET', array
				(
						'action'        => 'pageelement',
						'subaction'     => 'edit',
						'id'            => $this->pageId.'_'.$this->config['page_elementid_comments'],
						'token'         => $token
				) );
		
		$commentFolderId = $responseLink['output']['linkobjectid'];
		
		if	( empty($commentFolderId)) {

			// Der Kommentarordner existiert noch nicht, also müssen wir diesen anlegen.
			
			// Wo kommen die Kommentarordner rein?
			// Kann konfiguriert werden. Falls nicht, dann in den Ordner, der die Seite enthält.
			$commentContainerFolderId = intval($this->config['comment_folder_id']);
			if	( $commentContainerFolderId == 0 )
				$commentContainerFolderId = $responsePage['output']['parentid'];
			
			$responseCreate = $this->request( 'POST', array
					(
							'action'        => 'folder',
							'subaction'     => 'createfolder',
							'id'            => $commentContainerFolderId,
							'token'         => $token,
							'name'          => 'comment-'.$this->pageId
					) );
			$commentFolderId = $responseCreate['output']['objectid'];
			
			// Ordner für die Kommentare in der Seite speichern
			$response = $this->request( 'POST', array
					(
							'action'        => 'pageelement',
							'subaction'     => 'edit',
							'id'            => $this->pageId,
							'elementid'     => $this->config['page_elementid_comments'],
							'token'         => $token,
							'release'       => '1',
							'linkobjectid'  => $commentFolderId
					) );
				
		}

		
		// Seite für den Kommentar anlegen.
		$response = $this->request( 'POST', array
		(
			'action'        => 'folder',
			'subaction'     => 'createpage',
			'id'            => $commentFolderId,
			'templateid'    => $this->config['comment_templateid'],
			'token'         => $token,
			'name'          => 'Kommentar: '.$this->subject,
			'filename'      => 'comment-'.$this->subject,
		) );
		
		$commentpageobjectid = $response['output']['objectid'];

		// Text speichern anlegen.
		$response = $this->request( 'POST', array
		(
			'action'        => 'pageelement',
			'subaction'     => 'edit',
			'id'            => $commentpageobjectid,
			'elementid'     => $this->config['elementid_text'],
			'token'         => $token,
			'release'       => '1',
			'text'          => $this->text
		) );

		// Betreff speichern anlegen.
		$response = $this->request( 'POST', array
		(
				'action'        => 'pageelement',
				'subaction'     => 'edit',
				'id'            => $commentpageobjectid,
				'elementid'     => $this->config['elementid_subject'],
				'token'         => $token,
				'release'       => '1',
				'text'          => $this->subject
		) );

		// Name speichern anlegen.
		$response = $this->request( 'POST', array
		(
				'action'        => 'pageelement',
				'subaction'     => 'edit',
				'id'            => $commentpageobjectid,
				'elementid'     => $this->config['elementid_name'],
				'token'         => $token,
				'release'       => '1',
				'text'          => $this->name
		) );


		// Veröffentlichen der Seiten, welche jetzt den Kommentar enthält.
		$response = $this->request( 'POST', array
		(
			'action'    => 'page',
			'subaction' => 'pub',
			'id'        => $this->pageId,
			'token'     => $token
		) );


		mail('jan.2015@jandankert.de','Neuer Kommentar von '.$this->name.': '.$this->subject,'Text:\n\n'.$this->text);
	}

	
}

?>