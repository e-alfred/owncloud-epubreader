<!DOCTYPE html>
<html dir="ltr">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
		<title><?php echo htmlentities($_['file']);?></title>
		<link rel="shortcut icon" href="<?php print_unescaped(\OCP\Util::linkTo('files_epubviewer', 'img/epub/book.png')); ?>"/>
		<link rel="apple-touch-icon-precomposed" href="<?php print_unescaped(\OCP\Util::linkTo('files_epubviewer', 'ajax/epubhandler.php'));?>?dir=<?php print_unescaped(urlencode($_['dir']));?>&file=<?php print_unescaped(urlencode($_['file']));?>&function=getThumbImg"/>

		<link rel="stylesheet" href="<?php print_unescaped(\OCP\Util::linkTo('files_epubviewer', 'css/epub/viewer.css')); ?>"/>

		<!-- script type="text/javascript" src="<?php print_unescaped(\OCP\Util::linkTo('files_epubviewer', 'js/epub/compatibility.js')); ?>"></script -->


		<link rel="resource" type="application/l10n" href="<?php echo print_unescaped(\OCP\Util::linkTo('files_epubviewer', 'misc/epub/locale.properties')); ?>"/>
		<script type="text/javascript" src="<?php print_unescaped(\OCP\Util::linkTo('files_epubviewer', 'js/epub/jquery.js')); ?>"></script>
		<script type="text/javascript" src="<?php print_unescaped(\OCP\Util::linkTo('files_epubviewer', 'js/epub/l10n.js')); ?>"></script>
		<script type="text/javascript" src="<?php print_unescaped(\OCP\Util::linkTo('files_epubviewer', 'js/epub/epubdoc.js')); ?>"></script>
		<script type="text/javascript" src="<?php print_unescaped(\OCP\Util::linkTo('files_epubviewer', 'js/epub/viewer.js')); ?>"></script>
	</head>

	<body>

		<input type="hidden" id="ajaxUrl" value="<?php print_unescaped(\OCP\Util::linkTo('files_epubviewer', 'ajax/epubhandler.php'));?>?dir=<?php print_unescaped(urlencode($_['dir']));?>&file=<?php print_unescaped(urlencode($_['file']));?>&share=<?php print_unescaped(urlencode($_['share']));?>">
		<input type="hidden" id="oc_webroot" value="<?php echo OC::$WEBROOT; ?>">
		<input type="hidden" id="window_dir" value=<?php echo json_encode($_['dir']); ?>>
		<input type="hidden" id="window_file" value=<?php echo json_encode($_['file']); ?>>
		<input type="hidden" id="window_share" value=<?php echo json_encode($_['share']); ?>>

		<div id="outerContainer">

			<aside id="sidebarContainer">
				<div id="sidebarContent">
					<div id="thumbnailView">
					</div>
					<ul id="outlineView">
					</ul>
				</div>
				<div id="sidebarSizer"></div>
			</aside>
			<!-- sidebarContainer -->

			<div id="mainContainer">
				<div class="toolbar">
					<div id="toolbarContainer">

						<div id="toolbarViewer">
							<div id="toolbarViewerLeft">
								<button id="sidebarToggle" class="toolbarButton" title="Toggle Sidebar" tabindex="4"
										data-l10n-id="toggle_slider">
									<span data-l10n-id="toggle_slider_label">Toggle Sidebar</span>
								</button>
								<button id="bookmarkSave" class="toolbarButton bookmarkSave" title="Bookmark location" tabindex="5" data-l10n-id="bookmark_save">
									<span data-l10n-id="bookmark_save_label">Bookmark location</span>
								</button>
								<button id="bookmarkGo" class="toolbarButton bookmarkGo" title="Go to bookmarked location" tabindex="6" data-l10n-id="bookmark_go">
									<span data-l10n-id="bookmark_go_label">Go to bookmarked location</span>
								</button>
							</div>
							<div id="toolbarViewerRight">
								<button id="fullscreen" class="toolbarButton fullscreen" title="Fullscreen" tabindex="11"
										data-l10n-id="fullscreen">
									<span data-l10n-id="fullscreen_label">Fullscreen</span>
								</button>

								<button id="download" class="toolbarButton download" title="Download"
										tabindex="14" data-l10n-id="download">
									<span data-l10n-id="download_label">Download</span>
								</button>
								<!-- <div class="toolbarButtonSpacer"></div> -->
								<button id="close" class="toolbarButton close" 
								title="Close" tabindex="15" data-l10n-id="close"><span
									data-l10n-id="close_label">Close</span></button>
							</div>
							<div class="outerCenter">
								<div class="innerCenter" id="toolbarViewerMiddle">
									<div class="splitToolbarButton">
										<button class="toolbarButton zoomOut" title="Zoom Out" tabindex="8" data-l10n-id="zoom_out" id="zoom_out">
											<span data-l10n-id="zoom_out_label">Zoom Out</span>
										</button>
										<div class="splitToolbarButtonSeparator"></div>
										<button class="toolbarButton zoomIn" title="Zoom In" tabindex="9" data-l10n-id="zoom_in" id="zoom_in">
											<span data-l10n-id="zoom_in_label">Zoom In</span>
										</button>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div id="viewerContainer">
					<div id="viewer">
						<div id="viewerTop">
							<button id="previous" class="toolbarButton previousTOC" title="Previous" tabindex="20"
									data-l10n-id="previous">
								<span data-l10n-id="previous_label">Previous</span>
							</button>
							<div id="pageNumber"></div>
						</div>
						<div id="viewerFrame">
						</div>
						<div id="viewerBottom">
							<div id="progression" class="progression"> </div>
							<button id="next" class="toolbarButton nextTOC" title="Next" tabindex="21"
									data-l10n-id="next">
								<span data-l10n-id="next_label">Next</span>
							</button>
						</div>
					</div>
				</div>

				<div id="loadingBox">
					<div id="loading"></div>
					<div id="loadingBar">
						<div class="progress"></div>
					</div>
				</div>

				<div id="errorWrapper" hidden='true'>
					<div id="errorMessageLeft">
						<span id="errorMessage"></span>
<!--
						<button id="errorShowMore" data-l10n-id="error_more_info">
							More Information
						</button>
						<button id="errorShowLess" data-l10n-id="error_less_info"
								hidden='true'>
							Less Information
						</button>
-->
					</div>
					<div id="errorMessageRight">
						<button id="errorClose" data-l10n-id="error_close">
							Close
						</button>
					</div>
					<div class="clearBoth"></div>
					<textarea id="errorMoreInfo" hidden='true' readonly="readonly"></textarea>
				</div>
			</div>
			<!-- mainContainer -->

		</div>
		<!-- outerContainer -->

	</body>
</html>
