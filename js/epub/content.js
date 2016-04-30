 
'use strict';

var bookmarkLocation = 0;

var storeScrollTimeout = null;
var storeScrollWaitStart = null;
var restoreScrollPosition = null;
var restoreScrollTimeout = null;

function getScrollPC() {
	return getScrollPC(false)
}

function getScrollPC(updateDisplay) {
	var scrollPos = window.pageYOffset;
	var scrollMax = document.body.scrollHeight;

	if(updateDisplay && window.parent != null) {
		var nbPages = Math.floor(scrollMax / window.innerHeight);
		var numPage = Math.floor(scrollPos / window.innerHeight) + 1;
		var pageNumber = window.parent.document.getElementById('pageNumber');
		if(pageNumber != null)
			pageNumber.innerHTML = numPage + ' / ' + nbPages;
	}

	var pc = Math.floor(scrollPos * 100000 / scrollMax);
	return pc;
}

function setTextScale(size) {
	if(restoreScrollPosition == null)
		restoreScrollPosition = getScrollPC();
	var body = document.getElementsByTagName('body')[0];
	body.style.fontSize = size +'pt';
	if(restoreScrollTimeout != null)
		window.clearTimeout(restoreScrollTimeout);
	restoreScrollTimeout = window.setTimeout(restoreScrollPos, 1000);
	window.parent.EPubView.epubDocument.storeTextScale(size);
}

function restoreScrollPos() {
	restoreScrollTimeout = null;
	scrollToPosition(restoreScrollPosition);
	restoreScrollPosition = null;
}

function saveBookmark() {
	if(bookmarkLocation > 0) {
		if(!confirm('Replace bookmark ?'))
			return;
	}
	bookmarkLocation = getScrollPC();
	window.parent.EPubView.epubDocument.bookmarkLocation(bookmarkLocation);
	var bks = window.parent.document.getElementById('bookmarkSave');
	bks.classList.add('toggled');
	var bkg = window.parent.document.getElementById('bookmarkGo');
	bkg.disabled = false;
}

function goToBookmark() {
	if(bookmarkLocation == 0) {
		alert('No location bookmarked');
		return;
	}
	scrollToPosition(bookmarkLocation);
}

function storeScrollPosition()
{
	window.parent.EPubView.epubDocument.markLocation(getScrollPC(true));
	storeScrollWaitStart = null;

	// rechercher quel title est juste avant cette position
	var scrollPos = window.pageYOffset;
        var idxEntries = window.parent.EPubView.epubDocument.getIndexEntries();
	if(idxEntries.length > 0) {
		var bestTitle = idxEntries[0].getCode();
	        for( var i = 1 ; i < idxEntries.length ; i++ )
	        {
	                var title = document.getElementById(idxEntries[i].getCode());
			if (title == null)
				break;

			if(title.offsetTop - title.offsetHeight <= scrollPos)
				bestTitle = idxEntries[i].getCode();
		}
		window.parent.EPubView.setCurrentElement(bestTitle);
	}
}

function scrollToPosition(posPC)
{
	var scrollMax = document.body.scrollHeight;
	var scrollPos = scrollMax * posPC / 100000;
	window.scrollTo(0,scrollPos);
}

function scrollToAnchor(anchorName)
{
	var anchor = document.getElementById(anchorName);
	window.scrollTo(0,anchor.offsetTop);
}

var lastDocHeight = 0;
function afterLoad()
{
	if(document.body.scrollHeight != lastDocHeight)
	{
		// On attend la fin du rendu autrement la hauteur du document continuera de varier et le placement sera faussé
		lastDocHeight = document.body.scrollHeight;
		window.setTimeout(afterLoad, 100);
		return;
	}
	lastDocHeight = document.body.scrollHeight;
	
	window.addEventListener('scroll', function(evt) {
		if(storeScrollTimeout != null)
		{
			var doClear = true;
			if(storeScrollWaitStart != null && (new Date().getTime() - storeScrollWaitStart) > 20000)
				doClear = false;
			if(doClear)
				window.clearTimeout(storeScrollTimeout);
		}
		storeScrollTimeout = window.setTimeout(storeScrollPosition,1000);
		if(storeScrollWaitStart == null)
			storeScrollWaitStart = new Date().getTime();

		var scrollPos = window.pageYOffset;
		var scrollMax = document.body.scrollHeight;
		var prog = Math.floor(scrollPos*100 / scrollMax);
		var progPanel = window.parent.document.getElementById('progression');
		progPanel.style.width = prog + '%';
	}, true);

	var bookmarkLoc = document.getElementById('bookmark');
	if (bookmarkLoc != null) {
		bookmarkLocation = bookmarkLoc.value;
		var bks = window.parent.document.getElementById('bookmarkSave');
		bks.classList.add('toggled');
		var bkg = window.parent.document.getElementById('bookmarkGo');
		bkg.disabled = false;
	}
	else {
		var bkg = window.parent.document.getElementById('bookmarkGo');
		bkg.disabled = true;
	}
	
	var initPos = document.getElementById('initialPosition');
	var textScale = document.getElementById('textScale');

	if(textScale != null)
	{
		if(window.parent != null)
			window.parent.EPubView.setScale(textScale.value);
		else
			setTextScale(textScale.value);
		if(initPos != null)
			restoreScrollPosition = initPos.value;
	}
	
	if(initPos != null)
	{
		scrollToPosition(initPos.value);
	}
	
	window.parent.EPubView.progress(1.0);
}

document.addEventListener('DOMContentLoaded', afterLoad);
window.parent.EPubView.progress(0.3);

