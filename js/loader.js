var isMobile = {
    Android: function() {
        return navigator.userAgent.match(/Android/i);
    },
    BlackBerry: function() {
        return navigator.userAgent.match(/BlackBerry/i);
    },
    iOS: function() {
        return navigator.userAgent.match(/iPhone|iPad|iPod/i);
    },
    Opera: function() {
        return navigator.userAgent.match(/Opera Mini/i);
    },
    Windows: function() {
        return navigator.userAgent.match(/IEMobile/i);
    },
    any: function() {
        return (isMobile.Android() || isMobile.BlackBerry() || isMobile.iOS() || isMobile.Opera() || isMobile.Windows());
    }
};

var sharingToken = null;

function hideEPubviewer() {
	$('#content table').show();
    $("#controls").show();
    $("#editor").show();
	$('iframe').remove();
    $('a.action').remove();
}

function showEPubviewer(dir,filename,share){
	if(!showEPubviewer.shown){
		if(share === 'undefined')
			share = '';
		var viewer = OC.linkTo('files_epubviewer','viewer.php')+'?dir='+encodeURIComponent(dir).replace(/%2F/g, '/')+'&file='+encodeURIComponent(filename.replace('&', '%26')) + '&share=' + encodeURIComponent(share);
		if(isMobile.any())
			window.open(viewer, dir + '/' + filename);
		else
		{
			$iframe = '<iframe style="width:100%;height:100%;display:block;position:absolute;top:0;" src="'+viewer+'" allowfullscreen="true" webkitallowfullscreen="true" mozallowfullscreen="true"  sandbox="allow-scripts allow-same-origin"/>';
			if ($('#isPublic').val()) {
				// force the preview to adjust its height
				$('#preview').append($iframe).css({height: '100%'});
				$('body').css({height: '100%'});
				$('footer').addClass('hidden');
				$('#imgframe').addClass('hidden');
				$('.directLink').addClass('hidden');
				$('.directDownload').addClass('hidden');
				$('#controls').addClass('hidden');
			} else {
				FileList.setViewerMode(true);
				$('#app-content').append($iframe);
			}

			// replace the controls with our own
			$('#app-content #controls').addClass('hidden');
		}
	}
}

function openEpub(filename) {
	if($('#isPublic').val()) {
		showEPubviewer(FileList.getCurrentDirectory(), filename, sharingToken);
	} else {
		showEPubviewer(FileList.getCurrentDirectory(), filename, '');
	}
}

$(document).ready(function(){
	if(!$.browser.msie){//doesn't work on IE
		sharingToken = $('#sharingToken').val();
		
		// Logged view
		if($('#filesApp').val() && typeof FileActions !== 'undefined') {
			FileActions.register('application/epub+zip','Edit', OC.PERMISSION_READ, '', openEpub);
			FileActions.setDefault('application/epub+zip','Edit');
			FileActions.register('application/x-cbr','Edit', OC.PERMISSION_READ, '', openEpub);
			FileActions.setDefault('application/x-cbr','Edit');
			FileActions.register('application/x-cbz','Edit', OC.PERMISSION_READ, '', openEpub);
			FileActions.setDefault('application/x-cbz','Edit');
		}
		
		// Publicly shared view
		if ($('#isPublic').val()) {
			if( $('#mimetype').val() === 'application/epub+zip' ||  $('#mimetype').val() === 'application/x-cbr' ||  $('#mimetype').val() === 'application/x-cbz' ) {
				showEPubviewer('', '', sharingToken);
			}
		}
	}
});
