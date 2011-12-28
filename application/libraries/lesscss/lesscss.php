<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Lesscss
{

	protected $initDone = false;
	protected $relativePathToLessCss = null;
	protected $lessFolderRelativeToIndexDotPhp = null; //relative base path for .less folder
	protected $cssFolderRelativeToIndexDotPhp = null;  //relative base path for .css folder
	public $verboseErrorReporting = false;
	public $inputFileExtension = '.css'; 	//in order to avoid unexpected results, 
											//set this manually if want different extension
										
	public $outputFileExtension = '.css'; 	//in order to avoid unexpected results, 
											//set this manually if want different extension
	protected $ci = null;	
			
			
			
				
  	public function __construct()
  	{ 
  		$this->ci =& get_instance();
  		$less = $this->ci->config->item('less_css_dir_dot_less');
		$css = $this->ci->config->item('less_css_dir_dot_css');
  		
		//load config if set
		if( $less && $css)
		{
			$this->init("$less", "$css");
			
		}
  	}
  	
  		
   	public function getCssDir()
   	{ 
   		return $this->cssFolderRelativeToIndexDotPhp;
   	}
  	
  	public function getLessDir()
   	{ 
   		return $this->lessFolderRelativeToIndexDotPhp;
   	}
  	


  	public function init($lessFolderRelativeToIndexDotPhp = null, $cssFolderRelativeToIndexDotPhp = null)
  	{ 
  		$this->initDone = true;
  	

  		$this->lessFolderRelativeToIndexDotPhp = is_null($lessFolderRelativeToIndexDotPhp) 
									? "../less/"
									: $lessFolderRelativeToIndexDotPhp  ; 
									
					
		$this->cssFolderRelativeToIndexDotPhp = is_null($cssFolderRelativeToIndexDotPhp) 
									? "../"
									: $cssFolderRelativeToIndexDotPhp; 
  	}		

 	public function compile($fileDotLess)
 	{ 
 	
 		if( ! $this->initDone) {
  			$this->exitWithError('LessCss error: you forgot to call one of the init functions (and there were no settings in the config file either.)');
  		}
 	
 	
		$minusInputExtensionLength = (-1) * strlen($this->inputFileExtension);
		
		if( substr($fileDotLess,  $minusInputExtensionLength) === $this->inputFileExtension)
		{
			//strip out old file extension, and put in new one
			$fileDotCss = substr($fileDotLess, 0, (int) $minusInputExtensionLength) . $this->outputFileExtension;

			//before it was like this
			//$fileDotCss = substr($fileDotLess, 0, -5) .'.css'; // parameter -5 means remove last 5 letters
						
			$in = rtrim($this->lessFolderRelativeToIndexDotPhp , '/'). "/$fileDotLess";
			$out = rtrim($this->cssFolderRelativeToIndexDotPhp, '/') . "/$fileDotCss";
			//var_dump($in, $out);
			
			//copy pasted this from less.inc.php
			try {
			
				//if out.css file doesnt exist, or if it exists but the input.Less file was modified after it was modified
				if ( ! is_file($out) || filemtime($in) > filemtime($out)) {
					require_once dirname(__FILE__).DS.'leafo-lessphp/lessc.inc.php'; //only include when needed
					$less = new lessc($in);
					file_put_contents($out, $less->parse());
				}
				
				return $fileDotCss;


			} catch (exception $ex) {							

				$this->exitWithError('lessc fatal error:<br />'.$ex->getMessage());
				return '';
			}

			return $fileDotCss;
			
		}
		else
		{	
			$this->exitWithError('LessCss error: file name does not end with ".less" .<br />');
			return '';
		}
		
	}
	/**
	* Exit with error 
	* 
	* @param string $str
	* 
	*/

  	function exitWithError($str = '')
  	{ 
  		if($this->verboseErrorReporting)
			trigger_error($str);  
			die($str);
  	}
  	
  	
  	
  	
}


?>