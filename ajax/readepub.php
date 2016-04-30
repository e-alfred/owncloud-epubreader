<?php

/**
* ePub Reader class
*
* @package	CodeIgniter
* @subpackage	Libraries
* @author      Tristan Siebers (main work), Thierry CHARLES (zip support)
* @link	n/a
* @license     LGPL
* @version     0.1.0
*/
class Readepub {
	var $debug = false;

	/**
	* Contains the path to the dir with the ePub files
	* @var string Path to the extracted ePub files
	*/
	var $ebookDir;
	/**
	* Holds the (relative to $ebookDir) path to the OPF file
	* @var string Location + name of OPF file
	*/
	var $opfFile;
	/**
	* Relative (to $ebookDir) OPF (ePub files) dir
	* @var type Files dir
	*/
	var $opfDir;
	/**
	* Holds all the found DC elements in the OPF file
	* @var array All found DC elements in the OPF file
	*/
	var $dcElements;
	/**
	* Holds all the manifest items of the OPF file
	* @var array All manifest items
	*/
	var $manifest;
	/**
	* Holds all the spin data
	* @var array Spine data
	*/
	var $spine;
	/**
	* Holds the ToC data
	* @var array Array with ToC items
	*/
	var $toc;
	/** 
	* is it a zip directory ?
	*/
	var $isZip;
	/** 
	* objet zip conteneur
	*/
	var $zip = null;

	public function init($ebookDir, $originalname = null) {
		if($originalname === null)
			$originalname = basename($ebookDir);
		$this->ebookDir = $ebookDir;
		if($this->debug)
			error_log('==== Opening file ' . basename($ebookDir));

		$ext = pathinfo($originalname, PATHINFO_EXTENSION);
		if($this->debug) {
			error_log('        ext = ' . $ext);
			if(is_file($this->ebookDir))
				error_log('        mime = ' . mime_content_type($this->ebookDir));
		}

		if(is_file($this->ebookDir) && ($ext === 'epub' || mime_content_type($this->ebookDir) === 'application/epub+zip'))
		{
			if($this->debug)
				error_log('        opening as a zip file');

			$this->isZip = true;
			$this->zip = new ZipArchive();
			$rt = $this->zip->open($this->ebookDir);
			if($rt !== true)
				error_log('        error opening epub ' . basename($ebookDir) . ' : rt=' . $rt);
		}
		else {
			if($this->debug)
				error_log('        opening as a directory');
		
			$this->isZip = false;
		}
		
		$this->_getOPF();

		$this->_getDcData();
		$this->_getManifest();
		$this->_getSpine();
		$this->_getTOC();
		
		if($this->debug)
			$this->debug();
	}

	public function close()
	{
		if($this->isZip && $this->zip !== null)
			$this->zip->close();
	}
	
	/**
	* Get the specified DC item
	* @param string $item The DC Item key
	* @return string/boolean String when DC item exists, otherwise false
	*/
	public function getDcItem($item) {
		if(key_exists($item, $this->dcElements)) {
			return $this->dcElements[$item];
		} else {
			return false;
		}
	}

	public function getManifestSize()
	{
		return count($this->spine);
	}
	
	public function getManifestElementAt($idx)
	{
		if($idx >= count($this->spine))
			return false;
		else
			return $this->getManifest($this->spine[$idx]);
	}

	public function getManifestKeyAt($idx)
	{
		if($idx >= count($this->spine))
			return false;
		else
			return $this->spine[$idx];
	}

	/**
	* Get the specified manifest item
	* @param string $item The manifest ID
	* @return string/boolean String when manifest item exists, otherwise false
	*/
	public function getManifest($item) {
		if(key_exists($item, $this->manifest)) {
			return $this->manifest[$item];
		} else {
			return false;
		}
	}
	
	/**
	* Get the specified manifest by type
	* @param string $type The manifest type
	* @return string/boolean String when manifest item exists, otherwise false
	*/
	public function getManifestByType($type) {
		foreach($this->manifest AS $manifestID => $manifest) {
			if($manifest['media-type'] == $type) {
				$return[$manifestID]['href'] = $manifest['href'];
				$return[$manifestID]['media-type'] = $manifest['media-type'];
			}
		}
		
		return (count($return) == 0) ? false : $return;
	}
	
	/**
	* Get the specified manifest by Href
	* @param string $href the file href to find
	* @param boolean $bFirst only the first result
	* @return array/boolean Array when manifest item exists, otherwise false
	*/
	public function getManifestByHref($href, $bFirst) {
		foreach($this->manifest AS $manifestID => $manifest) {
			if($manifest['href'] == $href) {
				if ($bFirst)
				{
					$return['href'] = $manifest['href'];
					$return['media-type'] = $manifest['media-type'];
					break;
				}
				$return[$manifestID]['href'] = $manifest['href'];
				$return[$manifestID]['media-type'] = $manifest['media-type'];
			}
		}
		
		return (count($return) == 0) ? false : $return;
	}
	
	/**
	* Retrieve the ToC
	* @return array Array with ToC Data
	*/
	public function getTOC() {
		return $this->toc;
	}
	
	/**
	* Returns the OPF/Data dir
	* @return string The OPF/data dir
	*/
	public function getOPFDir() {
		return $this->opfDir;
	}

	/**
	* Prints all contents of the class directly to the screen
	*/
	public function debug() {
		error_log('        content='. print_r($this, true));
	}

	// Private functions

	/**
	* Get the path to the OPF file from the META-INF/container.xml file
	* @return string Relative path to the OPF file
	*/
	private function _getOPF() {
		$path = '/META-INF/container.xml';
		if($this->debug)
			error_log('        reading ' . $path);
		if($this->isZip)
			$opfContents = simplexml_load_string($this->readZipContent($path));
		else
			$opfContents = simplexml_load_file($this->ebookDir . $path);
		$opfAttributes = $opfContents->rootfiles->rootfile->attributes();
		$this->opfFile = (string) $opfAttributes['full-path']; // Typecasting to string to get rid of the XML object
		
		// Set also the dir to the OPF (and ePub files)
		$opfDirParts = explode('/',$this->opfFile);
		unset($opfDirParts[(count($opfDirParts)-1)]); // remove the last part (it's the .opf file itself)
		$this->opfDir = implode('/',$opfDirParts);
		
		return $this->opfFile;
	}

	/**
	* Read the metadata DC details (title, author, etc.) from the OPF file
	*/
	private function _getDcData() {
		if($this->debug)
			error_log('        reading DcData : ' . $this->opfFile);
		if($this->isZip)
			$opfContents = simplexml_load_string($this->readZipContent($this->opfFile));
		else
			$opfContents = simplexml_load_file($this->ebookDir . '/' . $this->opfFile);
		$this->dcElements = (array) $opfContents->metadata->children('dc', true);
	}

	/**
	* Gets the manifest data from the OPF file
	*/
	private function _getManifest() {
		if($this->debug)
			error_log('        reading Manifest : ' . $this->opfFile);
		if($this->isZip)
			$opfContents = simplexml_load_string(str_replace('opf:','',$this->readZipContent($this->opfFile)));
		else
			$opfContents = simplexml_load_file($this->ebookDir . '/' . $this->opfFile);

		$iManifest = 0;
		foreach ($opfContents->manifest->item AS $item) {
			$attr = $item->attributes();
			$id = (string) $attr->id;
			$this->manifest[$id]['href'] = (string) $attr->href;
			$this->manifest[$id]['media-type'] = (string) $attr->{'media-type'};
			$iManifest++;
		}
		
	}

	/**
	* Get the spine data from the OPF file
	*/
	private function _getSpine() {
		if($this->debug)
			error_log('        reading spine : ' . $this->opfFile);
		if($this->isZip)
			$opfContents = simplexml_load_string($this->readZipContent($this->opfFile));
		else
			$opfContents = simplexml_load_file($this->ebookDir . '/' . $this->opfFile);

		foreach ($opfContents->spine->itemref AS $item) {
			$attr = $item->attributes();
			$this->spine[] = (string) $attr->idref;
		}
	}
	
	
	/**
	* Build an array with the TOC
	*/
	private function _getTOC() {
		$tocFile = $this->getManifest('ncx');
		if($tocFile == '')
		{
			$this->toc = array();
			return;
		}
		if($this->debug)
			error_log('        reading Table of Content : ' . $this->opfDir.'/'.$tocFile['href']);
		if($this->isZip)
			$tocContents = simplexml_load_string($this->readZipContent($this->opfDir.'/'.$tocFile['href']));
		else
			$tocContents = simplexml_load_file($this->ebookDir.'/'.$this->opfDir.'/'.$tocFile['href']);
		
		$toc = array();
		for($i = 0 ; $i < count($tocContents->navMap) ; $i++)
		{
			foreach($tocContents->navMap[$i]->navPoint AS $navPoint) {
				$navPointData = $navPoint->attributes();
				$toc[(string)$navPointData['playOrder']]['id'] = (string)$navPointData['id'];
				$toc[(string)$navPointData['playOrder']]['naam'] = (string)$navPoint->navLabel->text;
				$toc[(string)$navPointData['playOrder']]['src'] = (string)$navPoint->content->attributes();
			}
		}

		if( count($toc) > 0 )
		{
			$first = array_values($toc);
			$first = $first[0]['src'];
			if(strpos($first, '%') !== false)
			{
				$decode = true;
				foreach($this->manifest as $me)
				{
					if($me['href'] === $first)
					{
						$decode = false;
						break;
					}
				}

				if($decode)
				{
					foreach($toc as $k => $te)
					{
						$te['src'] = urldecode($te['src']);
						$toc[$k] = $te;
					}
				}
			}
		}
	
		$this->toc = $toc;
	}

	/**
	* renvoie un pointeur de fichier sur l'image de couverture ou null si il n'y en a pas
	*/
	public function getCoverImageMime()
	{
		$cover = $this->getManifest('cover');
		if(!$cover)
			$cover = $this->getManifest('cover1');
		if(!$cover)
		{
			$covers = $this->getManifestByType('image/jpeg');
			if($covers !== false)
			{
				foreach($covers as $cov)
				{
					$cover = $cov;
					break;
				}
			}
		}
		if(!$cover)
			return null;
		return $cover['media-type'];
	}

	/**
	* renvoie un pointeur de fichier sur l'image de couverture ou null si il n'y en a pas
	*/
	public function getCoverImageStream()
	{
		$cover = $this->getManifest('cover');
		if(!$cover)
			$cover = $this->getManifest('cover1');
		if(!$cover)
		{
			$covers = $this->getManifestByType('image/jpeg');
			if($covers !== false)
			{
				foreach($covers as $cov)
				{
					$cover = $cov;
					break;
				}
			}
		}
		if(!$cover)
			return null;
		return $this->getZipStream($cover['href']);
	}

	public function getZipStream($path)
	{
		$path = $this->resolvePath($path);

		while($path[0] == '.' || $path[0] == '/')
		{
			if($path[0] == '/')
				$path = substr($path,1);
			else if($path[0] == '.' && $path[1] == '/')
				$path = substr($path,2);
			else
				break;
		}
		if($this->debug)
			error_log('        getZipStream: ' . $path);
		$fp = $this->zip->getStream($path);
		if($this->debug && $fp === false)
			error_log('        getZipStream: not found : ' . $path);
		if($fp === false && $this->opfDir != '')
		{
			$path = $this->opfDir.'/'.$path;
			while($path[0] == '/')
				$path = substr($path,1);
			if($this->debug)
				error_log('        getZipStream fallback: ' . $path);
			$fp = $this->zip->getStream($path);
			if($this->debug && $fp === false)
				error_log('        getZipStream: not found : ' . $path);
		}
		return $fp;
	}

	private function readZipContent($path)
	{
		$content = '';
		$fp = $this->getZipStream($path);
		if($fp === false)
		{
			error_log('could not load '.$path.' from zip');
			return null;
		}

		while (!feof($fp)) {
			$content .= fread($fp, 2);
		}

		fclose($fp);

		return $content;
	}

	public function getContent($contentId)
	{
		$tocelt = $this->getManifest($contentId);
		if(!$tocelt)
			return null;

		$src = $tocelt['href'];
		return $this->readZipContent($this->opfDir.'/'.$src);
	}

	public function resolvePath($p)
	{
		$pinit = $p;
		$len = strlen($p);
		$p = preg_replace('/[^\/]*[a-zA-Z0-9][^\/]*\/\.\.\//','',$p);
		$len2 = strlen($p);
		if($len > $len2)
			$p = $this->resolvePath($p);
		return $p;
	}
}
?>
