<?php

header('Content-Type: text/html; charset=utf-8');

?>

<form method="post" action="">
  <input type="submit" value="Neue Kommentare verarbeiten abrufen">
</form>

<?php

if	( $_SERVER['REQUEST_METHOD'] == 'POST' )
{

	if ($dh = opendir('./profiles'))
	{
		while (($file = readdir($dh)) !== false)
		{
			if	( substr($file,-4) == '.ini' )
			{
				$config = parse_ini_file('./profiles/'.$file,true);
				
				if	( !$config['enabled'] )
						continue;
				
				$blogger = new Blogger();
				if	( $config['debug'] ) echo "<h1>Profile: $file</h1>";
				
				$blogger->config = $config;
				$blogger->debug = $config['debug'];
				
				echo "<h2>Step 1: Pulling</h2>";
				$blogger->pull();
				flush();
				echo "<h2>Step 2: CMS</h2>";
				$blogger->pushToCMS();
				flush();
			}
		}
		closedir($dh);
	}
}


class Blogger {

	public $debug = true;
	public $config;
	
	private $blogs = array(); 
	
	public function pull()
	{
		if ($dh = opendir('./source'))
		{
			while (($file = readdir($dh)) !== false)
			{
				if	( substr($file,-4) == '.php' )
				{
					require_once('./source/'.$file);
					$className = substr($file,0,strlen($file)-10);

					if	( $this->debug )
						echo "<h3>Source-Plugin: ".$className.'</h3>';

					if	( isset($this->config[strtolower($className)] ))
					{
						$source = new $className;
		
						$source->config = $this->config[strtolower($className)];
						$source->debug    = $this->debug;

						foreach( $source->pull() as $blog )
						{
							$this->blogs[] = $blog;
						}
					}
				}
			}
			closedir($dh);
			
			if	( $this->debug )
			{
				echo "<h3>Blogs</h3>";
				echo '<pre>';
				print_r($this->blogs);
				echo '</pre>';
			}
		}
	
	}
	
	public function pushToCMS()
	{
		if ($dh = opendir('./cms'))
		{
			while (($file = readdir($dh)) !== false)
			{
				if	( substr($file,-4) == '.php' )
				{
					require_once('./cms/'.$file);
					$className = substr($file,0,strlen($file)-10);
						
					if	( $this->debug )
						echo "<h3>CMS-Plugin: ".$className.'</h3>';
						
					$cms = new $className;
		
					if	( isset($this->config[strtolower($className)] ))
					{
						$cms->config = $this->config[strtolower($className)];
						
						foreach( $this->blogs as $blog )
						{
							
							$cms->text      = $blog['text'     ];
							$cms->subject   = $blog['subject'  ];
							$cms->name      = $blog['name'     ];
							$cms->pageId    = $blog['pageid'   ];
							$cms->timestamp = $blog['timestamp'];
							$cms->debug     = $this->debug;
							$cms->push();
						}
					}
				}
			}
			closedir($dh);
		}
		
	}
}

?>