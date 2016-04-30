<?php

OCP\App::checkAppEnabled('files_epubviewer');

$dir = isset($_GET['dir']) ? $_GET['dir'] : '';
$file = isset($_GET['file']) ? $_GET['file'] : '';
$share = isset($_GET['share']) ? $_GET['share'] : '';

// TODO: add mime type detection and load the template
$mime = "application/zip+epub";

$page = new OCP\Template( 'files_epubviewer', 'epub');
$page->assign('dir', $dir);
$page->assign('file', $file);
$page->assign('share', $share);
$page->printPage();