<?php

class Mailbox
{
	public $debug = false;
	public $config = array();
	
	private $text;
	private $html;
	
	public function pull()
	{

		$entrys = array();
		
		/* connect to gmail */
		$hostname = $this->config['host'];
		$username = $this->config['username'];
		$password = $this->config['password'];
		
		/* try to connect */
		$inbox = imap_open($hostname,$username,$password) or die('Cannot connect to IMAP server: ' . imap_last_error());
		
		/* grab emails */
		$emails = imap_search($inbox,'ALL UNDELETED UNSEEN');
		
		print_r($emails);
		
		/* if emails are returned, cycle through each... */
		if($emails) {
		
			/* put the newest emails on top */
			rsort($emails);
		
			/* for every email... */
			foreach($emails as $email_number) {
		
				/* get information specific to this email */
				// 		$overview  = imap_fetch_overview($inbox,$email_number,0);
				$headers = imap_headerinfo($inbox,$email_number);
				$structure = imap_fetchstructure($inbox,$email_number);
				// 		$message = imap_fetchbody($inbox,$email_number);
		
				// 		echo "\nOverview:";
				//  		print_r($overview);
				if	( $this->debug ) { echo '<pre>'; print_r($headers); echo '</pre>'; }
		
				// Initalize
				$this->filenames = array();
				$this->text      = '';
				$this->html      = '';
				$subject   = iconv_mime_decode($headers->subject,0,'UTF-8');
		
				$s = imap_fetchstructure($inbox,$email_number);
				
				// Hier gibt es keinen MIME-Messages, es reicht also der Body.
				$this->text = imap_body($inbox,$email_number);
		
				if	( $this->debug ) echo "\n\nBetreff: ".$subject;
				if	( $this->debug ) echo "\n\nText: ";
				if	( $this->debug ) print_r($this->text);
		
				
				$header_string =  imap_fetchheader($inbox, $email_number);
				preg_match_all('/([^: ]+): (.+?(?:\r\n\s(?:.+?))*)\r\n/m',$header_string, $matches);
				$allHeaders = array_combine($matches[1], $matches[2]);

				if	( $this->debug ) echo "\n\nAlle Mailheader: ";
				if	( $this->debug ) echo "<pre>$header_string</pre>";
				if	( $this->debug ) {echo "<pre>"; print_r($allHeaders); echo "</pre>";}
				
				$entrys[] = array(
					'timestamp' => strtotime($headers->date),
					'subject'  => $subject,
					'text'     => $this->text,
					'name'     => $allHeaders['X-Name'],
					'pageid'   => $allHeaders['X-Page-Id']
				);
		
				// Aufräumen:
				// - Mail als gelesen markieren und in das Archiv verschieben.
				if	( $this->config['dry'] )
					;
				else
				{
					imap_setflag_full($inbox,$email_number,'\\SEEN',0);
					
					if	(isset($this->config['archive_folder']))
					{
						imap_mail_move($inbox,$email_number,$this->config['archive_folder']) or die("IMAP: Move did not suceed: "+imap_last_error() );
				
						imap_expunge($inbox);
					}
				}
			}
		}
		
		/* close the connection */
		imap_close($inbox);
		
		return $entrys;
	}
	
}

?>