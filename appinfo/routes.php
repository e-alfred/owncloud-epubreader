<?php

$this->create('files_epubviewer_index', '/')->actionInclude('files_epubviewer/viewer.php');

$this->create('files_epubviewer_viewer', 'viewer.php')->actionInclude('files_epubviewer/viewer.php');

$this->create('files_epubviewer_ajax_epubhandler', 'ajax/epubhandler.php')->actionInclude('files_epubviewer/ajax/epubhandler.php');

$this->create('files_epubviewer_ajax_readcb', 'ajax/readcb.php')->actionInclude('files_epubviewer/ajax/readcb.php');

$this->create('files_epubviewer_ajax_readepub', 'ajax/readepub.php')->actionInclude('files_epubviewer/ajax/readepub.php');

?>