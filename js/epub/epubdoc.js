'use strict';
var EPubJS = {
	build: '2014.02.27',
        ajaxUrl: ''
};

var IndexEntry = (function IndexEntryClosure() {
	function IndexEntry(code,title)
	{
		this.code = code;
		this.title = title;
	}
	
	IndexEntry.prototype = {
		getCode: function () {
			return this.code;
		},
		
		getTitle: function () {
			return this.title;
		}
	};
	
	return IndexEntry;
})();

var EPubDocument = (function EPubDocumentClosure() {
	
	function EPubDocument(docPath) {
		this.docPath = docPath;
	}

	EPubDocument.prototype = {
		
		getDocPath: function () {
			return this.docPath;
		},
		
		initContent: function (callback, errorhandler, progress) {
			if(this.isInitialized)
				return;
			this.isInitialized = true;
			
			this.loadCallback = callback;
			this.errorHandler = errorhandler;
			this.progressHandler = progress;
			
			this.index = null;
			
			var self = this;
			$.ajax({
				url:EPubJS.ajaxUrl + '&function=getTOC',
				type:"GET",
				mimeType:'text/xml',
				async:true,
				success:function(data, textStatus, xmlhttp){
					self.initIndex(xmlhttp.responseXML.documentElement);
				},
				error:function() {
					self.errorHandler('Failed to get table of content');
				}
			});
		},
		
		initIndex: function(idxXmlContent) {
			this.progressHandler(0.5);
			this.index = new Array();
			var errors = idxXmlContent.getElementsByTagName('Error');
			for(var i = 0 ; i < errors.length ; i++)
			{
				console.log(errors[i].nodeValue);
				this.errorhandler(errors[i].nodeValue);
				return;
			}
			var entries = idxXmlContent.getElementsByTagName('Entry');
			for (var i = 0 ; i < entries.length ; i++)
			{
				var code = entries[i].getElementsByTagName('Code')[0].textContent;
				var title = entries[i].getElementsByTagName('Title')[0].textContent;
				this.index[i] = new IndexEntry(code,title);
				this.progressHandler(0.5 + (i*0.25/entries.length));
			}
			
			this.loadCallback(this);
		},
		
		getThumbnailImage: function () {
			return EPubJS.ajaxUrl + '&function=getThumbImg';
		},
		
		getDataFile: function (dataFileId) {
			return EPubJS.ajaxUrl + '&function=getDataFile&data=' + dataFileId;
		},
		
		getIndexEntries: function() {
			return this.index;
		},
		
		getContent: function () {
			var url = EPubJS.ajaxUrl + '&function=getContent';
			return '<iframe id="contentFrame" src="' + url + '" />';
		},
		
		markLocation: function (posPC) {
			var self = this;
			$.ajax({
				url:EPubJS.ajaxUrl + '&function=setLastPos&paraId=' + posPC,
				type:"GET",
				async:true,
				error:function() {
					self.errorHandler('Failed to set mark location');
				}
			});
		},
		
		bookmarkLocation: function (posPC) {
			var self = this;
			$.ajax({
				url:EPubJS.ajaxUrl + '&function=setBookmark&loc=' + posPC,
				type:"GET",
				async:true,
				error:function() {
					self.errorHandler('Failed to set bookmark location');
				}
			});
		},
		
		storeTextScale: function (scale) {
			var self = this;
			$.ajax({
				url:EPubJS.ajaxUrl + '&function=setTextScale&scale=' + scale,
				type:"GET",
				async:true,
				error:function() {
					self.errorHandler('Failed to set scale');
				}
			});
		}
	};
	
	return EPubDocument;
})();

