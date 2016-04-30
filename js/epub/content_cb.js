 
'use strict';

var currentAnchor = '';
var bookmarkLocation = 0;
var loadingAnim = '';

function getScrollPC() {
	return getScrollPC(false)
}

function getScrollPC(updateDisplay) {
	var index = window.parent.EPubView.epubDocument.getIndexEntries();
	var pos = 0;
	for(var i = 0 ; i < index.length ; i++) {
		if(index[i].getCode() == currentAnchor) {
			pos = i+1;
			break;
		}
	}

	if(updateDisplay && window.parent != null) {
		var nbPages = index.length;
		var pageNumber = window.parent.document.getElementById('pageNumber');
		if(pageNumber != null)
			pageNumber.innerHTML = pos + ' / ' + nbPages;
	}

	return pos;
}

function setTextScale(size) {
	var imgPage = document.getElementById('pageImg');
	imgPage.style.maxWidth = (100 + ((size-12)*10)) + '%';
	window.parent.EPubView.epubDocument.storeTextScale(size);
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
	var pos = getScrollPC(true);
	window.parent.EPubView.epubDocument.markLocation(pos);

	var index = window.parent.EPubView.epubDocument.getIndexEntries();
	window.parent.EPubView.setCurrentElement(index[pos-1].getCode());
}

function scrollToPosition(pos)
{
	var index = window.parent.EPubView.epubDocument.getIndexEntries();
	scrollToAnchor(index[pos-1].getCode());
}

function scrollToAnchor(anchorName)
{
	currentAnchor = anchorName;
	var imgPage = document.getElementById('pageImg');
	imgPage.src = loadingAnim;
	window.setTimeout(startLoading, 200);
	window.scrollTo(0,0);
	storeScrollPosition();
}

function startLoading()  {
	var imgPage = document.getElementById('pageImg');
	imgPage.src = window.parent.EPubView.epubDocument.getDataFile(currentAnchor);
}

function afterLoad()
{
	var imgPage = document.getElementById('pageImg');
	if(!imgPage.complete) {
		window.setTimeout(afterLoad, 100);
		return;
	}
	loadingAnim = imgPage.src;
	
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

	if(textScale != null) {
		if(window.parent != null)
			window.parent.EPubView.setScale(textScale.value);
		else
			setTextScale(textScale.value);
	}
	
	if(initPos != null)
		scrollToPosition(initPos.value);
	else
		scrollToPosition(1);
	
	window.parent.EPubView.progress(1.0);
}

document.addEventListener('DOMContentLoaded', afterLoad);
window.parent.EPubView.progress(0.3);

