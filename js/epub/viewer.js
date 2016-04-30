/* -*- Mode: Java; tab-width: 2; indent-tabs-mode: nil; c-basic-offset: 2 -*- */
/* vim: set shiftwidth=2 tabstop=2 autoindent cindent expandtab: */

'use strict';

var kDefaultURL = 'compressed.tracemonkey-pldi-09.epub';
var kDefaultScale = 'auto';
var kDefaultScaleDelta = 1.1;
var kUnknownScale = 0;
var kCacheSize = 20;
var kCssUnits = 96.0 / 72.0;
var kScrollbarPadding = 40;
var kMinScale = 1;
var kMaxScale = 50;
var kImageDirectory = './images/';
var kSettingsMemory = 20;


var mozL10n = document.mozL10n || document.webL10n;

var Cache = function cacheCache(size) {
  var data = [];
  this.push = function cachePush(view) {
    var i = data.indexOf(view);
    if (i >= 0)
      data.splice(i);
    data.push(view);
    if (data.length > size)
      data.shift().destroy();
  };
};

var ProgressBar = (function ProgressBarClosure() {

  function clamp(v, min, max) {
    return Math.min(Math.max(v, min), max);
  }

  function ProgressBar(id, opts) {
    this._percent = 0;

    // Fetch the sub-elements for later
    this.div = document.querySelector(id + ' .progress');

    // Get options, with sensible defaults
    this.height = opts.height || 100;
    this.width = opts.width || 100;
    this.units = opts.units || '%';

    // Initialize heights
    this.div.style.height = this.height + this.units;
  }

  ProgressBar.prototype = {

    updateBar: function ProgressBar_updateBar() {
      if (this._indeterminate) {
        this.div.classList.add('indeterminate');
        return;
      }

      var progressSize = this.width * this._percent / 100;

      if (this._percent > 95)
        this.div.classList.add('full');
      else
        this.div.classList.remove('full');
      this.div.classList.remove('indeterminate');

      this.div.style.width = progressSize + this.units;
    },

    get percent() {
      return this._percent;
    },

    set percent(val) {
      this._indeterminate = isNaN(val);
      this._percent = clamp(val, 0, 100);
      this.updateBar();
    }
  };

  return ProgressBar;
})();


// Settings Manager - This is a utility for saving settings
// First we see if localStorage is available
// If not, we use FUEL in FF
var Settings = (function SettingsClosure() {
  var isLocalStorageEnabled = (function localStorageEnabledTest() {
    // Feature test as per http://diveintohtml5.info/storage.html
    // The additional localStorage call is to get around a FF quirk, see
    // bug #495747 in bugzilla
    try {
      return 'localStorage' in window && window['localStorage'] !== null &&
          localStorage;
    } catch (e) {
      return false;
    }
  })();

  function Settings(fingerprint) {
    var database = null;
    var index;
    if (isLocalStorageEnabled)
      database = localStorage.getItem('database') || '{}';
    else
      return;

    database = JSON.parse(database);
    if (!('files' in database))
      database.files = [];
    if (database.files.length >= kSettingsMemory)
      database.files.shift();
    for (var i = 0, length = database.files.length; i < length; i++) {
      var branch = database.files[i];
      if (branch.fingerprint == fingerprint) {
        index = i;
        break;
      }
    }
    if (typeof index != 'number')
      index = database.files.push({fingerprint: fingerprint}) - 1;
    this.file = database.files[index];
    this.database = database;
  }

  Settings.prototype = {
    set: function settingsSet(name, val) {
      if (!('file' in this))
        return false;

      var file = this.file;
      file[name] = val;
      var database = JSON.stringify(this.database);
      if (isLocalStorageEnabled)
        localStorage.setItem('database', database);
    },

    get: function settingsGet(name, defaultValue) {
      if (!('file' in this))
        return defaultValue;

      return this.file[name] || defaultValue;
    }
  };

  return Settings;
})();

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

var cache = new Cache(kCacheSize);
var currentPageNumber = 1;

var EPubView = {
  currentScale: 12,
  container: null,
  initialized: false,
  epubDocument: null,
  sidebarOpen: false,
  isFullscreen: false,
  previousScale: null,
  currentElement: null,

  // called once when the document is loaded
  initialize: function epubViewInitialize() {
    this.initialized = true;
  },

  setScale: function epubViewSetScale(val) {
    if (val == this.currentScale)
      return;

    this.currentScale = parseInt(val);
	
    var iframe = document.getElementById('contentFrame');
    iframe.contentWindow.setTextScale(val);
  },

  zoomIn: function epubViewZoomIn() {
    var newScale = this.currentScale + 1;
    newScale = Math.min(kMaxScale, newScale);
    this.setScale(newScale);
  },

  zoomOut: function epubViewZoomOut() {
    var newScale = this.currentScale - 1;
    newScale = Math.max(kMinScale, newScale);
    this.setScale(newScale);
  },

  goToBookmark: function epubGoToBookmark() {
    var iframe = document.getElementById('contentFrame');
    iframe.contentWindow.goToBookmark();
  },

  saveBookmark: function epubSaveBookmark() {
    var iframe = document.getElementById('contentFrame');
    iframe.contentWindow.saveBookmark();
  },

  get supportsFullscreen() {
    var doc = document.documentElement;
    var support = doc.requestFullScreen || doc.mozRequestFullScreen ||
                  doc.webkitRequestFullScreen;
    Object.defineProperty(this, 'supportsFullScreen', { value: support,
                                                        enumerable: true,
                                                        configurable: true,
                                                        writable: false });
    return support;
  },

  open: function epubViewOpen(docPath) {

	if (!EPubView.loadingBar) {
		EPubView.loadingBar = new ProgressBar('#loadingBar', {});
	}

    this.epubDocument = null;
    this.loading = true;
	
	if (!docPath)
	{
		error('Invalid document path');
		return;
	}
	
	var self = this;
	var newDoc = new EPubDocument(docPath);
	newDoc.initContent(
		function (doc) {
			self.load(doc);
		}, 
		function (msg, detail) {
			self.error(msg, detail);
		}, 
		function (lvl) {
			//self.progress(lvl);
		});
  },

  download: function epubViewDownload() {
    var url = EPubJS.ajaxUrl;
    url += '&function=download';
    window.open(url, '_parent');
  },

  /**
   * Show the error box.
   * @param {String} message A message that is human readable.
   * @param {Object} moreInfo (optional) Further information about the error
   *                            that is more technical.  Should have a 'message'
   *                            and optionally a 'stack' property.
   */
  error: function epubViewError(message, moreInfo) {
    var moreInfoText = mozL10n.get('error_build', {build: EPubJS.build},
      'EPub.JS Build: {{build}}') + '\n';
    if (moreInfo) {
      moreInfoText +=
        mozL10n.get('error_message', {message: moreInfo.message},
        'Message: {{message}}');
      if (moreInfo.stack) {
        moreInfoText += '\n' +
          mozL10n.get('error_stack', {stack: moreInfo.stack},
          'Stack: {{stack}}');
      } else {
        if (moreInfo.filename) {
          moreInfoText += '\n' +
            mozL10n.get('error_file', {file: moreInfo.filename},
            'File: {{file}}');
        }
        if (moreInfo.lineNumber) {
          moreInfoText += '\n' +
            mozL10n.get('error_line', {line: moreInfo.lineNumber},
            'Line: {{line}}');
        }
      }
    }

    var loadingBox = document.getElementById('loadingBox');
    loadingBox.setAttribute('hidden', 'true');

    var errorWrapper = document.getElementById('errorWrapper');
    errorWrapper.removeAttribute('hidden');

    var errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = message;

    var closeButton = document.getElementById('errorClose');
    closeButton.onclick = function() {
      errorWrapper.setAttribute('hidden', 'true');
    };

    var errorMoreInfo = document.getElementById('errorMoreInfo');
    var moreInfoButton = document.getElementById('errorShowMore');
    var lessInfoButton = document.getElementById('errorShowLess');
    moreInfoButton.onclick = function() {
      errorMoreInfo.removeAttribute('hidden');
      moreInfoButton.setAttribute('hidden', 'true');
      lessInfoButton.removeAttribute('hidden');
    };
    lessInfoButton.onclick = function() {
      errorMoreInfo.setAttribute('hidden', 'true');
      moreInfoButton.removeAttribute('hidden');
      lessInfoButton.setAttribute('hidden', 'true');
    };
    moreInfoButton.removeAttribute('hidden');
    lessInfoButton.setAttribute('hidden', 'true');
    errorMoreInfo.value = moreInfoText;

    errorMoreInfo.rows = moreInfoText.split('\n').length - 1;
  },

  progress: function epubViewProgress(level) {
    var percent = Math.round(level * 100);
    EPubView.loadingBar.percent += percent;

    if(EPubView.loadingBar.percent >= 100) {
    	var loadingBox = document.getElementById('loadingBox');
    	loadingBox.setAttribute('hidden', 'true');
    	var loadingIndicator = document.getElementById('loading');
    	loadingIndicator.textContent = '';
    }
  },

  load: function epubViewLoad(newDoc) {
    this.epubDocument = newDoc;
	this.loading = false;

    var errorWrapper = document.getElementById('errorWrapper');
    errorWrapper.setAttribute('hidden', 'true');

    var thumbsView = document.getElementById('thumbnailView');
    thumbsView.parentNode.scrollTop = 0;
	
    while (thumbsView.hasChildNodes())
      thumbsView.removeChild(thumbsView.lastChild);
	
	var page = document.getElementById('viewerFrame');
	while (page.hasChildNodes())
		page.removeChild(page.lastChild);

	this.currentElement = null;
	var text = this.epubDocument.getContent();
	page.innerHTML = text;

	var thumbImg = document.createElement('img');
	thumbImg.src = this.epubDocument.getThumbnailImage();
	thumbImg.classList.add('thumbnail');
	thumbsView.appendChild(thumbImg);

    var outlineView = document.getElementById('outlineView');
	
    while (outlineView.hasChildNodes())
      outlineView.removeChild(outlineView.lastChild);
	
	var idxEntries = this.epubDocument.getIndexEntries();
	for( var i = 0 ; i < idxEntries.length ; i++ )
	{
		var entry = document.createElement('li');
		entry.setAttribute('id','TOC_link_'+idxEntries[i].getCode());
		entry.classList.add('outlineItem');
		var link = document.createElement('a');
		link.appendChild(document.createTextNode(idxEntries[i].getTitle()));
		link.href='#';
		link.addEventListener('click', function(e) { 
			EPubView.showIndexElement( this.parentElement.id.replace('TOC_link_', '') ); 
			return false;
		} );
		entry.appendChild(link);
		outlineView.appendChild(entry);
	}
	
	if(idxEntries.length > 0)
	{
		if(!isMobile.any())
			document.getElementById('sidebarToggle').click();
	}

	this.progress(0.3);
  },

  switchSidebarView: function epubViewSwitchSidebarView() {
    var view = document.getElementById('sidebarContent');
	if (outlineButton.getAttribute('disabled'))
	{
		view.classList.remove('toggled');
		view.classList.add('hidden');
	}
	else
	{
		view.classList.remove('hidden');
		view.classList.add('toggled');
	}
  },

  fullscreen: function epubViewFullscreen() {
    var isFullscreen = document.fullscreen || document.mozFullScreen ||
        document.webkitIsFullScreen;

    if (isFullscreen) {
      return false;
    }

    var wrapper = document.getElementById('viewerContainer');
    if (document.documentElement.requestFullScreen) {
      wrapper.requestFullScreen();
    } else if (document.documentElement.mozRequestFullScreen) {
      wrapper.mozRequestFullScreen();
    } else if (document.documentElement.webkitRequestFullScreen) {
      wrapper.webkitRequestFullScreen(Element.ALLOW_KEYBOARD_INPUT);
    } else {
      return false;
    }

    this.isFullscreen = true;

    return true;
  },

  exitFullscreen: function epubViewExitFullscreen() {
    this.isFullscreen = false;
  },
  
  setCurrentElement: function epubViewSetCurrentElement(anchor) {
	this.currentElement = anchor;
	var idxEntries = this.epubDocument.getIndexEntries();
	for( var i = 0 ; i < idxEntries.length ; i++ )
	{
		var entry = document.getElementById('TOC_link_'+idxEntries[i].getCode());
		entry.classList.remove('selected');
	}

	if(anchor != null) {
		var entry = document.getElementById('TOC_link_'+anchor);
		if(entry != null)
		{
			entry.classList.add('selected');
			
			var view = document.getElementById('outlineView');
			view.scrollTop = entry.offsetTop - (view.clientHeight * 3 / 7);
		}
	}
  },
  
  showIndexElement: function epubViewShowIndexElement(anchor) {
	var iframe = document.getElementById('contentFrame');
	iframe.contentWindow.scrollToAnchor(anchor);
  },
  
  showPreviousElement: function epubViewShowPreviousElement() {
	if(this.currentElement == null)
		return;
		
	var idxEntries = this.epubDocument.getIndexEntries();
	var previous = null;
	for(var i = 0 ; i < idxEntries.length ; i++)
	{
		if(idxEntries[i].getCode() == this.currentElement)
			break;
		previous = idxEntries[i].getCode();
	}
	if(previous == null)
		return;
	this.showIndexElement(previous);
  },
  
  showNextElement: function epubViewShowNextElement() {
	if(this.currentElement == null)
		return;
		
	var idxEntries = this.epubDocument.getIndexEntries();
	var next = null;
	for(var i = idxEntries.length-1 ; i >= 0 ; i--)
	{
		if(idxEntries[i].getCode() == this.currentElement)
			break;
		next = idxEntries[i].getCode();
	}
	if(next == null)
		return;
	this.showIndexElement(next);
  }
  
};


// optimised CSS custom property getter/setter
var CustomStyle = (function CustomStyleClosure() {

  // As noted on: http://www.zachstronaut.com/posts/2009/02/17/
  //              animate-css-transforms-firefox-webkit.html
  // in some versions of IE9 it is critical that ms appear in this list
  // before Moz
  var prefixes = ['ms', 'Moz', 'Webkit', 'O'];
  var _cache = { };

  function CustomStyle() {
  }

  CustomStyle.getProp = function get(propName, element) {
    // check cache only when no element is given
    if (arguments.length == 1 && typeof _cache[propName] == 'string') {
      return _cache[propName];
    }

    element = element || document.documentElement;
    var style = element.style, prefixed, uPropName;

    // test standard property first
    if (typeof style[propName] == 'string') {
      return (_cache[propName] = propName);
    }

    // capitalize
    uPropName = propName.charAt(0).toUpperCase() + propName.slice(1);

    // test vendor specific properties
    for (var i = 0, l = prefixes.length; i < l; i++) {
      prefixed = prefixes[i] + uPropName;
      if (typeof style[prefixed] == 'string') {
        return (_cache[propName] = prefixed);
      }
    }

    //if all fails then set to undefined
    return (_cache[propName] = 'undefined');
  };

  CustomStyle.setProp = function set(propName, element, str) {
    var prop = this.getProp(propName);
    if (prop != 'undefined')
      element.style[prop] = str;
  };

  return CustomStyle;
})();

document.addEventListener('DOMContentLoaded', function webViewerLoad(evt) {
    EPubJS.ajaxUrl = document.getElementById('ajaxUrl').value;
    oc_webroot = document.getElementById('oc_webroot').value;
    window.dir = document.getElementById('window_dir').value;
    window.file = document.getElementById('window_file').value;
    
    var sizer = document.getElementById('sidebarSizer');
    sizer.addEventListener('mousedown', function(e) { startDragging(e); });
    sizer.addEventListener('mousemove', function(e) { doDragging(e); });
    sizer.addEventListener('mouseup', function(e) { endDragging(e); });
    
	var btn = document.getElementById('fullscreen');
	btn.addEventListener('click', function(e) { EPubView.fullscreen(); } );
	
	btn = document.getElementById('close');
	btn.addEventListener('click', function(e) { closeEbook(); } );
	
	btn = document.getElementById('previous');
	btn.addEventListener('click', function(e) { EPubView.showPreviousElement(); } );
	
	btn = document.getElementById('next');
	btn.addEventListener('click', function(e) { EPubView.showNextElement(); } );
	
	btn = document.getElementById('download');
	btn.addEventListener('click', function(e) { EPubView.download(); } );

	btn = document.getElementById('zoom_in');
	btn.addEventListener('click', function(e) { EPubView.zoomIn(); } );

	btn = document.getElementById('zoom_out');
	btn.addEventListener('click', function(e) { EPubView.zoomOut(); } );
	
  EPubView.initialize();

  if (!EPubView.supportsFullscreen) {
    document.getElementById('fullscreen').classList.add('hidden');
  }


  var mainContainer = document.getElementById('mainContainer');
  var outerContainer = document.getElementById('outerContainer');
  mainContainer.addEventListener('transitionend', function(e) {
    if (e.target == mainContainer) {
      var event = document.createEvent('UIEvents');
      event.initUIEvent('resize', false, false, window, 0);
      window.dispatchEvent(event);
      outerContainer.classList.remove('sidebarMoving');
    }
  }, true);

  document.getElementById('sidebarToggle').addEventListener('click',
    function() {
		this.classList.toggle('toggled');
		outerContainer.classList.add('sidebarMoving');
		outerContainer.classList.toggle('sidebarOpen');
		EPubView.sidebarOpen = outerContainer.classList.contains('sidebarOpen');
		
		var sidecontainer = document.getElementById('sidebarContainer');
		var maincontainer = document.getElementById('mainContainer');
		if(sidecontainer.style.width == 'undefined')
			sidecontainer.style.width = sidecontainer.clientWidth + 'px';
		if(sidecontainer.style.width == '')
			sidecontainer.style.width = sidecontainer.clientWidth + 'px';
		if(outerContainer.classList.contains('sidebarOpen'))
		{
			sidecontainer.style.left = '0px';
			if(!isMobile.any())
				maincontainer.style.left = sidecontainer.style.width;
			else
				maincontainer.style.left = '0px';
		}
		else
		{
			sidecontainer.style.left = '-' + sidecontainer.style.width;
			maincontainer.style.left = '0px';
		}
    });

  document.getElementById('bookmarkGo').addEventListener('click',
    function() {
    	EPubView.goToBookmark();
    });
  document.getElementById('bookmarkSave').addEventListener('click',
    function() {
    	EPubView.saveBookmark();
    });

    hideUnrequiredButtons();

    EPubView.open(window.dir+"/"+window.file);
}, true);

window.addEventListener('localized', function localized(evt) {
  document.getElementsByTagName('html')[0].dir = mozL10n.language.direction;
}, true);


(function fullscreenClosure() {
  function fullscreenChange(e) {
    var isFullscreen = document.fullscreen || document.mozFullScreen ||
        document.webkitIsFullScreen;

    if (!isFullscreen) {
      EPubView.exitFullscreen();
    }
  }

  window.addEventListener('fullscreenchange', fullscreenChange, false);
  window.addEventListener('mozfullscreenchange', fullscreenChange, false);
  window.addEventListener('webkitfullscreenchange', fullscreenChange, false);
})();

var drag = {
	enabled: false,
	startX: 0
};

function startDragging(evt)
{
	if(evt == null)
		evt = window.event;
	
	if(evt.button != 0)
		return true;
	
	drag.enabled = true;
	drag.startX = evt.clientX;
	var sizer = document.getElementById('sidebarSizer');
	sizer.style.backgroundcolor = 'Green';
	
	return false;
}

function doDragging(evt)
{
	if(!drag.enabled)
		return;
	
	if(evt == null)
		evt = window.event;
	
	var sizer = document.getElementById('sidebarSizer');
	var pos = ((evt.clientX - drag.startX) + drag.startX);
	sizer.style.left = pos + 'px';
	drag.startX = evt.clientX;
	var sidecontainer = document.getElementById('sidebarContainer');
	sidecontainer.style.width = (pos + 5) + 'px';
	var maincontainer = document.getElementById('mainContainer');
	maincontainer.style.left = (pos + 5) + 'px';
	var outerContainer = document.getElementById('outerContainer');
	outerContainer.classList.remove('sidebarMoving');
}

function endDragging(evt)
{
	if(evt == null)
		evt = window.event;
	
	drag.enabled = false;
	var sizer = document.getElementById('sidebarSizer');
	sizer.style.backgroundcolor = 'Red';
}


var oc_webroot = '';
window.dir = '';
window.file = '';

function closeEbook()
{
        if(window.parent && window.parent.hideEPubviewer)
                window.parent.location.reload();
        else
                window.close();
}

function hideUnrequiredButtons()
{
        if(window.top == window)
        {
                var tb = document.getElementById('close');
                tb.style.display = 'none';
        }
}

