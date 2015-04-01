function Hicurl(domElement) {
	this._domElement=domElement;
	domElement.className+=" hicurl";
	$.getScript("https://code.jquery.com/ui/1.11.4/jquery-ui.min.js").done($.proxy(this._uiLoaded, this));
	$('head').append( $('<link rel="stylesheet" type="text/css" />')
			.attr('href', 'https://code.jquery.com/ui/1.11.4/themes/redmond/jquery-ui.css') );
	$.getJSON("assets//getHistory.php",this._historyLoadedHandler);
}
Hicurl.prototype._uiLoaded=function() {
	var els=[];
	for(var i=0;i<4;i++)
		els[i]=this[["_pagesView","_jsonView","_filterView","_htmlView"][i]]=document.createElement("div");
	$(els).addClass("ui-widget-content").appendTo(this._domElement)
	.each(function(i,el){$(el).addClass(["pagesView","jsonView","filterView","htmlView"][i])})
	.resizable({
      containment:this._domElement,
	  resize:this._viewResizeHandler
    });
};
Hicurl.prototype._viewResizeHandler=function(e,ui) {
	console.log(this);
	switch(e.target) {
		//case 
	}
}
Hicurl.prototype._historyLoadedHandler=function(data) {
	this.historyData=data;
};
