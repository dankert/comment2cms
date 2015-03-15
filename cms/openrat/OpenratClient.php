<?php

class OpenratClient
{
	public $error  = '';
	public $status = '';

	public $host;
	public $port;
	public $path;
	public $method = 'GET';
	public $response;
	public $parameter = array();
	public $type = "text/json";
	public $cookie = "";
	
	public function request()
	{
		$errno  = 0;
		$errstr = '';

		// Die Funktion fsockopen() erwartet eine Protokollangabe (bei TCP optional, bei SSL notwendig).
		if	( $this->port == '443' )
			$prx_proto = 'ssl://'; // SSL
		else
			$prx_proto = 'tcp://'; // Default
			
		$fp = fsockopen($prx_proto.$this->host,$this->port, $errno, $errstr, 30);

		if	( !$fp || !is_resource($fp) )
		{
			$this->error = "Connection refused: '".$prx_proto.$host.':'.$port." - $errstr ($errno)";
		}
		else
		{
			$lb = "\r\n";
			$http_get = $this->path;

			$parameterString = '';

			foreach( $this->parameter as $pkey=>$pvalue)
			{
				if	( strlen($parameterString) > 0)
					$parameterString .= '&';
					
				$parameterString .= urlencode($pkey) . '=' .urlencode($pvalue);
			}
			//print_r($parameterString);
			
			if	( $this->method == 'GET')
				if	( !empty($parameterString) )
					$http_get .= '?'.$parameterString;

			if	( $this->method == 'POST' )
			{
				$header[] = 'Content-Type: application/x-www-form-urlencoded';
				$header[] = 'Content-Length: '.strlen($parameterString);
			}
					
			$header[] = 'Host: '.$this->host;
			$header[] = 'Accept: '.$this->type;
			$header[] = 'Cookie: '.$this->cookie;
			$request_header = array_merge(array( $this->method.' '.$http_get.' HTTP/1.0'),$header);
				//print_r($request_header);
			$http_request = implode($lb,$request_header).$lb.$lb;
			
			
			if	( $this->method == 'POST' )
				$http_request .= $parameterString;
				
			//echo "<textarea>".htmlentities($http_request)."</textarea>";

			if (!is_resource($fp)) {
				$error = 'Connection lost after connect: '.$prx_proto.$host.':'.$port;
				return false;
			}
			fputs($fp, $http_request); // Die HTTP-Anfrage zum Server senden.

			// Jetzt erfolgt das Auslesen der HTTP-Antwort.
			$isHeader = true;

			// RFC 1945 (Section 6.1) schreibt als Statuszeile folgendes Format vor
			// "HTTP/" 1*DIGIT "." 1*DIGIT SP 3DIGIT SP
			if (!is_resource($fp)) {
				echo 'Connection lost during transfer: '.$host.':'.$port;
			}
			elseif (!feof($fp)) {
				$line = fgets($fp,1028);
				$this->status = substr($line,9,3);
			}
			else
			{
				echo 'Unexpected EOF while reading HTTP-Response';
			}
			
			$body = '';
			
			while (!feof($fp)) {
				$line = fgets($fp,1028);
				if	( $isHeader && trim($line)=='' ) // Leerzeile nach Header.
				{
					$isHeader = false;
				}
				elseif( $isHeader )
				{
					list($headerName,$headerValue) = explode(': ',$line) + array(1=>'');
					//if	( $headerName == 'Set-Cookie' )
					//	$this->cookie = $headerValue;
					$responseHeader[$headerName] = trim($headerValue);
				}
				else
				{
					$body .= $line;
				}
			}
			fclose($fp); // Verbindung brav schliessen.
			$this->response = $body;
		}
	}
}

?>