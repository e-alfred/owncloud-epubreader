<?php
if( !function_exists('apache_request_headers') ) {
    function apache_request_headers() {
        $arh = array();
        $rx_http = '/\AHTTP_/';

        foreach($_SERVER as $key => $val) {
            if( preg_match($rx_http, $key) ) {
                $arh_key = preg_replace($rx_http, '', $key);
                $rx_matches = array();
           // do some nasty string manipulations to restore the original letter case
           // this should work in most cases
                $rx_matches = explode('_', $arh_key);

                if( count($rx_matches) > 0 and strlen($arh_key) > 2 ) {
                    foreach($rx_matches as $ak_key => $ak_val) {
                        $rx_matches[$ak_key] = ucfirst($ak_val);
                    }

                    $arh_key = implode('-', $rx_matches);
                }

                $arh[$arh_key] = $val;
            }
        }

        return( $arh );
    }
}

	require_once('readepub.php');
	require_once('readcb.php');
	
	global $forceMySql;
	$forceMySql = (\OCP\Config::getSystemValue('dbtype', 'sqlite') === 'mysql');

	function dbExecute($sql, $values) {
		global $forceMySql;
		try {
			if($forceMySql)
				$sql = str_replace('"','`',$sql); // mysql compatibility
				
			$stmt = OCP\DB::prepare($sql);
			$res = $stmt->execute($values);
			if($res === false) {
				error_log('error while executing ' . $sql . ' (' . print_r($values) . ')');
				return false;
			}
			return $res;
		}
		catch(Exception $e) {
			if($forceMySql)
				throw $e;
				
			$forceMySql = true;
			$sql = str_replace('"','`',$sql); // mysql compatibility
			try {
				$stmt = OCP\DB::prepare($sql);
				$res = $stmt->execute($values);
				return $res;
			}
			catch(Exception $e2) {
				throw $e;
			}
		}
	}
	
	/* Cette fonction a pour but d'établir une table des manières complète du livre en se basant sur les titres */
	function parseTitleNodes(&$rootNode, &$titles) {
		try {
			$c0 = $rootNode->nodeName[0];
			$c1 = $rootNode->nodeName[1];
			if(($c0 == 'h' || $c0 == 'H') && ($c1 >= '0' && $c1 <= '9') && strlen($rootNode->nodeName) == 2) {
				$a = $rootNode->ownerDocument->createElement('a');
				$a->setAttribute('id', 'title'.count($titles));
				$rootNode->insertBefore($a);
				$titles[] = $rootNode->ownerDocument->saveXML($rootNode);
			}
			else {
				if($rootNode->firstChild !== NULL) 
					parseTitleNodes($rootNode->firstChild, $titles);
			}
			if($rootNode->nextSibling !== NULL) 
				parseTitleNodes($rootNode->nextSibling, $titles);
		}
		catch(Exception $e) {
			error_log(print_r($e, true));
		}
	}


	/* Cette fonction a pour but de remplacer les chemins d'acces aux ressources */
	function parseDataRefsNodes(&$rootNode, $rsPath) {
		try {
			if($rootNode->hasAttributes()) {
				for($i = 0 ; $i < $rootNode->attributes->length ; $i++) {
					$att = $rootNode->attributes->item($i);
					if($att->nodeName == 'src' || $att->nodeName == 'xlink:href') {
						$v = $rsPath . $att->nodeValue;
						$rootNode->setAttribute($att->nodeName, $v);
						break;
					}
					else if($att->nodeName == 'href') {
						$v = $att->nodeValue;
						$shidx = strpos($v,'#');
						if($shidx !== FALSE)
							$v = substr($v, $shidx);
						else
							$v = $rsPath . $att->nodeValue;
						$rootNode->setAttribute($att->nodeName, $v);
						break;
					}
				}
			}
			if($rootNode->firstChild !== NULL) 
				parseDataRefsNodes($rootNode->firstChild, $rsPath);
			if($rootNode->nextSibling !== NULL) 
				parseDataRefsNodes($rootNode->nextSibling, $rsPath);
		}
		catch(Exception $e) {
			error_log(print_r($e, true));
		}
	}

	function getDataFP($book, $data) {
		$data_ = $data;

		$tocelt = $book->getManifestElementAt(1);
		if($tocelt)
		{
			$data = dirname($tocelt['href']).'/'.$data;
		}

		$fp = $book->getZipStream($data);

		if( $fp === null || $fp === false)
			$fp = $book->getZipStream($data_);

		if( $fp === null || $fp === false)
			die('Data unavailable : ' . $data);

		return $fp;
	}

	/* Cette fonction a pour but de créer un document unique à partir de toutes les entrées du livre */
	function concatenateContent($book) {
		$fulldoc = NULL;
		$fdbody = NULL;
		$fdhead = NULL;
		$flinks = array();
		for($i = 0 ; $i < $book->getManifestSize() ; $i++)
		{
			$me = $book->getManifestElementAt($i);
			if(strpos($me['media-type'],'html') === false)
				continue;
			$mk = $book->getManifestKeyAt($i);

			$doc = new DOMDocument();
			$doc->loadHTML($book->getContent($mk));
			if($fulldoc === NULL) {
				$doc2 = new DOMDocument();
				$htmlNode = $doc->getElementsByTagName('html')->item(0);
				$tmpcontent = $doc->saveHTML($htmlNode);
				if($tmpcontent === FALSE || $tmpcontent == '')
					$tmpcontent = $doc->saveXML($htmlNode);
				$doc2->loadHTML('<!DOCTYPE html>' . "\n" . $tmpcontent);
				$doc = $doc2;
			}

			$head = $doc->getElementsByTagName('head');
			if($head->length != 1) {
				error_log('impossible de trouver le head dans ' . $mk);
				return fulldoc;
			}
			$head = $head->item(0);

			$body = $doc->getElementsByTagName('body');
			if($body->length != 1) {
				error_log('impossible de trouver le body dans ' . $mk);
				return fulldoc;
			}
			$body = $body->item(0);

			if($fulldoc === NULL) {
				$fulldoc = $doc;
				$fdhead = $head;
				for($j = 0 ; $j < $head->childNodes->length ; $j++) {
					$item = $head->childNodes->item($j);
					if($item === NULL)
						error_log($j . ' n existe pas !');
					else if($item->nodeName == 'style') {
						$head->removeChild($item);
						$j--;
					}
				}
				for($j = 0 ; $j < $head->childNodes->length ; $j++) {
					$item = $head->childNodes->item($j);
					if($item === NULL)
						error_log($j . ' n existe pas !');
					else if($item->nodeName == 'link') {
						$flinks[$item->getAttribute('href')] = true;
						$style = $fulldoc->createElement('style');
						$fp = getDataFP($book, $item->getAttribute('href'));
						$content = '';
						while(!feof($fp))
							$content .= fread($fp,4096);
						$style->nodeValue = $content;
						fclose($fp);
						$fdhead->appendChild($style);
						$head->removeChild($item);
						$j--;
					}
				}
				$fdbody = $body;
				continue;
			}

			for($j = 0 ; $j < $head->childNodes->length ; $j++) {
				$item = $head->childNodes->item($j);
				if($item === NULL)
					error_log($j . ' n existe pas !');
				else if($item->nodeName == 'link') {
					try {
						if(!array_key_exists($item->getAttribute('href'), $flinks)) {
							$flinks[$item->getAttribute('href')] = true;
							$style = $fulldoc->createElement('style');
							$fp = getDataFP($book, $item->getAttribute('href'));
							$content = '';
							while(!feof($fp))
								$content .= fread($fp,4096);
							$style->nodeValue = $content;
							fclose($fp);
							$fdhead->appendChild($style);
						}
					}
					catch(Exception $e){
						error_log($e->getMessage());
					}
				}
			}

			$toc = $fulldoc->createElement('a');
			$toc->setAttribute('id','toc'.$i);
			$fdbody->appendChild($toc);
			for($j = 0 ; $j < $body->childNodes->length ; $j++) {
				$item = $body->childNodes->item($j);
				if($item === NULL)
					error_log($j . ' n existe pas !');
				else {
					try {
						$fdbody->appendChild($fulldoc->importNode($item,true));
					}
					catch(Exception $e){
						error_log($e->getMessage());
					}
				}
			}
		}

		return $fulldoc;
	}
	
	function checkForModification($lastModified) {
		if($lastModified < filemtime(__FILE__))
			$lastModified = filemtime(__FILE__);
		if($lastModified < filectime(__FILE__))
			$lastModified = filectime(__FILE__);
		$headers = apache_request_headers();
		if (array_key_exists('If-Modified-Since', $headers) && (strtotime($headers['If-Modified-Since']) == $lastModified)) {
			// Client's cache IS current, so we just respond '304 Not Modified'.
			header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastModified).' GMT', true, 304);
			exit();
		}
		else {
			header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastModified).' GMT', true, 200);
			header('Cache-Control: private, must-revalidate');
		}
	}

	OCP\App::checkAppEnabled('files_epubviewer');

	$dir = array_key_exists('dir', $_GET) ? $_GET['dir'] : '';
	$file = array_key_exists('file', $_GET) ? $_GET['file'] : '';
	$share = array_key_exists('share', $_GET) ? $_GET['share'] : '';
	$ext = pathinfo($file, PATHINFO_EXTENSION);
	$function = array_key_exists('function', $_GET) ? $_GET['function'] : '';

	if( $function === '' || ($dir === '' && $file === '' && $share === '')) {
		error_log('Missing parameter : ' . print_r($_GET, true));
		die('Missing parameter');
	}
	
	$selfUrl = $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'];
	$idx = strpos($selfUrl, '&function=');
	$selfUrl = substr($selfUrl,0,$idx);

	if($share === '') {
		OCP\User::checkLoggedIn();
		$storage = OCP\Files::getStorage($share === '' ? 'files' : 'shares');
		if($storage === false) {
			error_log('Unable to get files storage');
			die('Unable to get files storage');
		}
		$path = $dir . '/' . $file;
		if (! $storage->file_exists($path)) {
			error_log('File does not exist : ' . $path);
			die('File does not exist : ' . $path);
		}
		$lastModified = $storage->filemtime($path);
		$path = $storage->toTmpFile($path);
	}
	else {
		// Partage public
		$linkedItem = \OCP\Share::getShareByToken($share);
		if($linkedItem === false || ($linkedItem['item_type'] !== 'file' && $linkedItem['item_type'] !== 'folder')) {
			error_log('Share does not exist : ' . $share);
			die('Share does not exist : ' . $share);
		}
		if(!isset($linkedItem['uid_owner']) || !isset($linkedItem['file_source'])) {
			error_log('Share is broken : ' . $share);
			die('Share is broken : ' . $share);
		}

		$rootLinkItem = OCP\Share::resolveReShare($linkedItem);
		$userId = $rootLinkItem['uid_owner'];

		OCP\JSON::checkUserExists($rootLinkItem['uid_owner']);
		\OC_Util::setupFS($userId);
		\OC\Files\Filesystem::initMountPoints($userId);
		$view = new \OC\Files\View('/' . $userId . '/files');

		if($linkedItem['item_type'] === 'folder') {
			$path = $linkedItem['file_target'] . '/' . $file;
			$isValid = \OC\Files\Filesystem::isValidPath($path);
			if(!$isValid) {
				error_log('File does not exist in share : ' . $path);
				die('File does not exist in share : ' . $path);
			}
			$path = \OC\Files\Filesystem::normalizePath($path);
		}
		else if($linkedItem['item_type'] === 'file') {
			$pathId = $linkedItem['file_source'];
			$path = $view->getPath($pathId);
		}
		$lastModified = $view->filemtime($path);
		$path = $view->toTmpFile($path);
	}

	$isComic = false;
	if($ext == 'cbr' || $ext == 'cbz') {
		$isComic = true;
		$book = new ReadComicBook();
	}
	else
		$book = new Readepub();

	if($function === 'getThumbImg')
	{
		checkForModification($lastModified);
		$book->init($path, $file);
		$mime = $book->getCoverImageMime();
		$fp = false;
		if($mime !== false && strlen($mime) > 6 && substr($mime, 0, 6) == 'image/')
			$fp = $book->getCoverImageStream();
		if( $mime === null || $fp === null || $fp === false) {
			$fp = fopen(dirname(__FILE__) .'/../img/epub/book_128.png', 'r');
			if($fp === false) {
				die('error opening default cover icon');
			}
			$mime = 'image/png';
		}
		
		{
			header('Content-Type: '.$mime);
			header('Content-Disposition: attachment; filename=cover');
			header('Content-Transfer-Encoding: binary');
			flush();
			while(!feof($fp))
				echo fread($fp,4096);
			fclose($fp);
		}
	}
	else if ($function === 'getTOC')
	{
		checkForModification($lastModified);
		$book->init($path, $file);
		header('Content-Type: text/xml');
		echo '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<result>';
		if($isComic) {
			$toc = $book->getTOC();
			foreach($toc as $k => $v) {
				echo '<Entry><Code>'.$v.'</Code><Title>'.($k+1).'</Title></Entry>';
			}
		}
		else {
			$contentDoc = concatenateContent($book);
			$toc = array();
			parseTitleNodes($contentDoc, $toc);
			if(count($toc) > 0) {
				for($i = 0 ; $i < count($toc) ; $i++) {
					echo '<Entry><Code>title'.$i.'</Code><Title>'.$toc[$i].'</Title></Entry>';
				}
			}
			else {
				$toc = array_values($book->getTOC());
				for($i = 0, $t = 0 ; $i < $book->getManifestSize() ; $i++)
				{
					$me = $book->getManifestElementAt($i);
					if(strpos($me['media-type'],'html') === false)
						continue;
					$mk = $book->getManifestKeyAt($i);
					$title = "[$i]";
					if($toc !== false)
					{
						$te = $toc[$t];
						if( $te['src'] === $me['href'] )
						{
							$title = $te['naam'];
							$t++;
							if($t === count($toc))
								$toc = false;
						}
					}
					echo '<Entry><Code>toc'.$i.'</Code><Title>'.$title.'</Title></Entry>';
				}
			}
		}

		echo '</result>';
	}
	else if ($function === 'getLastPos')
	{
		if($share !== '')
			echo "";
		else {
			$res = dbExecute('SELECT * FROM *PREFIX*book_location loc WHERE loc."Owner" = ? AND loc."File" = ?',
					array(OCP\USER::getUser(), $dir.'/'.$file));
			if($row = $res->fetchRow())
				echo $row['LastTOC'] . '@' . $row['LastPara'];
			else
				echo "";
		}
	}
	else if ($function === 'setLastPos')
	{
		if($share === '') {
			$paraId = array_key_exists('paraId', $_GET) ? $_GET['paraId'] : '';
			if( $paraId === '')
				die('Missing parameter');

			dbExecute('DELETE FROM *PREFIX*book_location WHERE "Owner" = ? AND "File" = ?',
				array(OCP\USER::getUser(), $dir.'/'.$file));
			dbExecute('INSERT INTO *PREFIX*book_location VALUES (?, ?, ?, ?)',
				array(OCP\USER::getUser(), $dir.'/'.$file, '', $paraId));
		}
	}
	else if ($function === 'setBookmark')
	{
		if($share === '') {
			$loc = array_key_exists('loc', $_GET) ? $_GET['loc'] : '';
			if( $loc === '')
				die('Missing parameter');

			dbExecute('DELETE FROM *PREFIX*book_bookmark WHERE "Owner" = ? AND "File" = ?',
				array(OCP\USER::getUser(), $dir.'/'.$file));
			dbExecute('INSERT INTO *PREFIX*book_bookmark VALUES (?, ?, ?)',
				array(OCP\USER::getUser(), $dir.'/'.$file, $loc));
		}
	}
	else if ($function === 'setTextScale')
	{
		if($share === '') {
			$scale = array_key_exists('scale', $_GET) ? $_GET['scale'] : '';
			if( $scale === '')
				die('Missing parameter');

			dbExecute('DELETE FROM *PREFIX*book_scales WHERE "Owner" = ? AND "File" = ?',
				array(OCP\USER::getUser(), $dir.'/'.$file));
			dbExecute('INSERT INTO *PREFIX*book_scales VALUES (?, ?, ?)',
				array(OCP\USER::getUser(), $dir.'/'.$file, $scale));
		}
	}
	else if ($function === 'getContent')
	{
		if($isComic) {
			$contentDoc = new DOMDocument();
			$contentDoc->loadHTML('<html><head></head><body id="comicBody"><img id="pageImg" src="' . \OCP\Util::linkTo('files_epubviewer', 'img/epub/loading-icon.gif') . '"/></body></html>');
			
		}
		else {
			$book->init($path, $file);
			$contentDoc = concatenateContent($book);
			$toc = array();
			parseTitleNodes($contentDoc, $toc);
			$rsPath = $selfUrl.'&function=getDataFile&data=';
			parseDataRefsNodes($contentDoc, $rsPath);
		}

		$head = $contentDoc->getElementsByTagName('head')->item(0);
		$link = $contentDoc->createElement('link');
		$link->setAttribute('href', \OCP\Util::linkTo('files_epubviewer', 'img/epub/book.png').'?mod='.filemtime('../css/epub/content.css'));
		$link->setAttribute('rel', 'shortcut icon');
		$head->appendChild($link);
		$script = $contentDoc->createElement('script');
		$script->setAttribute('src', \OCP\Util::linkTo('files_epubviewer', $isComic ? 'js/epub/content_cb.js' : 'js/epub/content.js').'?mod='.filemtime('../js/epub/content.js'));
		$script->setAttribute('type', 'text/javascript');
		$head->appendChild($script);
		$link = $contentDoc->createElement('link');
		$link->setAttribute('href', \OCP\Util::linkTo('files_epubviewer', 'css/epub/content.css').'?mod='.filemtime('../css/epub/content.css'));
		$link->setAttribute('rel', 'stylesheet');
		$head->appendChild($link);

		$body = $contentDoc->getElementsByTagName('body')->item(0);
		$body->setAttribute('style','background-color: transparent; font-size: 12pt; font-family: Sans !important;');

		if($share === '') {
			$res = dbExecute('SELECT "LastPara" FROM *PREFIX*book_location loc WHERE loc."Owner" = ? AND loc."File" = ?',
					array(OCP\USER::getUser(), $dir.'/'.$file));
			if($row = $res->fetchRow()) {
				$pos = $contentDoc->createElement('input');
				$pos->setAttribute('type', 'hidden');
				$pos->setAttribute('id', 'initialPosition');
				$pos->setAttribute('value', $row['LastPara']);
				$body->appendChild($pos);
			}

			$res = dbExecute('SELECT "Scale" FROM *PREFIX*book_scales loc WHERE loc."Owner" = ? AND loc."File" = ?',
					array(OCP\USER::getUser(), $dir.'/'.$file));
			if($row = $res->fetchRow()) {
				$pos = $contentDoc->createElement('input');
				$pos->setAttribute('type', 'hidden');
				$pos->setAttribute('id', 'textScale');
				$pos->setAttribute('value', $row['Scale']);
				$body->appendChild($pos);
			}

			$res = dbExecute('SELECT "Location" FROM *PREFIX*book_bookmark loc WHERE loc."Owner" = ? AND loc."File" = ?',
					array(OCP\USER::getUser(), $dir.'/'.$file));
			if($row = $res->fetchRow()) {
				$pos = $contentDoc->createElement('input');
				$pos->setAttribute('type', 'hidden');
				$pos->setAttribute('id', 'bookmark');
				$pos->setAttribute('value', $row['Location']);
				$body->appendChild($pos);
			}
		}

		echo $contentDoc->saveHTML();
	}
	else if ($function === 'getDataFile')
	{
		checkForModification($lastModified);
		$book->init($path, $file);
		$data = array_key_exists('data', $_GET) ? $_GET['data'] : '';
		if( $data === '' )
			die('Missing parameter');

		if( $isComic ) {
			header('Content-Type: ' . $book->getCoverImageMime());
			$fp = $book->getStream($data);
		}
		else {
			$fp = getDataFP($book, $data);

			$tocelt = $book->getManifestByHref($data, true);
			if($tocelt !== false)
				header('Content-Type: ' . $tocelt['media-type']);
		}
		while(!feof($fp))
			echo fread($fp,4096);
		fclose($fp);
	}
	else if ($function === 'download')
	{
		$finfo = finfo_open(FILEINFO_MIME_TYPE); // Retourne le type mime à la extension mimetype
		$mimetype = finfo_file($finfo, $path);
		finfo_close($finfo);
		header('Content-Type: ' . $mimetype);
		header('Content-Disposition: attachment; filename="'.$file.'"');
		readfile($path);
		flush();
	}
	else if ($function === 'debug')
	{
		$book->init($path, $file);
		$book->debug();
	}
	else
	{
		echo '<result><error>unsupported operation : ' . $function . '.</error></result>';
	}

	$book->close();
?>
