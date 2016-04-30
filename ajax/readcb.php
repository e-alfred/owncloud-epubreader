<?php

/**
* Comic book Reader class
*/
class ReadComicBook {

	var $debug = false;

	/**
	* Holds the ToC data
	* @var array Array with ToC items
	*/
	var $toc = null;
	/** 
	* is it a ZIP or a RAR ?
	*/
	var $isZip;
	/** 
	* objet zip conteneur
	*/
	var $archive = null;

	public function init($comicFile, $originalname = null) {
		if($originalname === null)
			$originalname = basename($comicFile);
			
		if($this->debug)
			error_log('==== Opening file ' . basename($comicFile));

		$ext = pathinfo($originalname, PATHINFO_EXTENSION);
		if($this->debug)
			error_log('        ext = ' . $ext);

		if($ext === 'cbz')
		{
			if($this->debug)
				error_log('        opening as a ZIP file');
			$this->isZip = true;
			$this->archive = new ZipArchive();
			$rt = $this->archive->open($comicFile);
			if($rt !== true)
				error_log('        error opening CBZ ' . basename($ebookDir) . ' : rt=' . $rt);
		}
		else {
			if($this->debug)
				error_log('        opening as a RAR file');
			$this->isZip = false;
			$this->archive = RarArchive::open($comicFile);
		}
	}

	public function close()
	{
		if($this->archive !== null)
			$this->archive->close();
	}
	
	/**
	* Retrieve the ToC
	* @return array Array with ToC Data
	*/
	public function getTOC() {
		if($this->toc === null) {
			$this->toc = array();
			
			if($this->isZip) {
				for($i = 0 ; $i < $this->archive->numFiles ; $i++) {
					$e = $this->archive->statIndex($i);
					if($e['size'] > 0)
						$this->toc[] = $e['name'];
				}
			}
			else {
				$entries = $this->archive->getEntries();
				if($entries === FALSE) {
					error_log("Impossible de lire l'archive");
				}
				else {
					foreach($entries as $e) {
						if($e->isDirectory())
							error_log('skiping directory ' . $e->getName());
						else
							$this->toc[] = $e->getName();
					}
				}
			}
			
			sort($this->toc,SORT_STRING | SORT_FLAG_CASE);
		}
		return $this->toc;
	}
	
	/**
	* Prints all contents of the class directly to the screen
	*/
	public function debug() {
		echo sprintf('<pre>%s</pre>', print_r($this, true));
	}

	/**
	* renvoie un pointeur de fichier sur l'image de couverture ou null si il n'y en a pas
	*/
	public function getCoverImageMime()
	{
		$toc = $this->getTOC();
		$cover = $toc[0];
		
		return $this->getImageMime($cover);
	}
	
	private function getImageMime($filename) {
		$mime_types = array(
			'png' => 'image/png',
			'jpe' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpg' => 'image/jpeg',
			'gif' => 'image/gif',
			'bmp' => 'image/bmp',
			'ico' => 'image/vnd.microsoft.icon',
			'tiff' => 'image/tiff',
			'tif' => 'image/tiff',
			'svg' => 'image/svg+xml',
			'svgz' => 'image/svg+xml');
		$ext = strtolower(array_pop(explode('.',$filename)));
		if (array_key_exists($ext, $mime_types))
			return $mime_types[$ext];
		else
			return "image/unknown";
	}

	/**
	* renvoie un pointeur de fichier sur l'image de couverture ou null si il n'y en a pas
	*/
	public function getCoverImageStream()
	{
		$toc = $this->getTOC();
		$cover = $toc[0];
		return $this->getStream($cover);
	}

	public function getStream($path)
	{
		if($this->isZip) {
			error_log("looking for " . $path);
			return $this->archive->getStream($path);
		}
		else {
			return $this->archive->getEntry($path)->getStream();
		}
	}
}
?>
