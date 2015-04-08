function Hicurl(domElement,dataUrl) {
	this._domElement=domElement;
	domElement.className+=" hicurl easyui-layout";
	$.ajax({
        url : dataUrl,
        dataType : 'json',
        context : this
    }).done(this._historyLoadedHandler);
	
	//load jstree for viewing json with
	$.getScript("https://cdnjs.cloudflare.com/ajax/libs/jstree/3.1.0/jstree.min.js");
	$('head').append( $('<link rel="stylesheet" type="text/css" />')
			.attr('href', 'https://cdnjs.cloudflare.com/ajax/libs/jstree/3.1.0/themes/default/style.min.css') );
	
	var west=component(domElement,"region:'west',split:true",{width:"225px"},null,"Pages");
	var pageAccordion=component(west,"fit:true,border:false",null,"easyui-accordion");
	(this._pageTree=component(pageAccordion)).title="Tree View";
	var center=component(domElement,"region:'center',title:'',iconCls:'icon-ok'");
	var centerLayout=component(center,"fit:true",null,"easyui-layout");
	this._jsonPanel=component(centerLayout,"region:'center',title:'JSON'",{height:"50%"});
	var contentPanel=component(centerLayout,"region:'south',title:'',split:true",{height:"50%",overflow:"hidden"},"contentPanel");
	this._contentTabs=component(contentPanel,"fit:true",null,"easyui-tabs content");
	component(this._contentTabs,"selected: false").title="Content";
	$.parser.parse();
	$(this._contentTabs).tabs('disableTab', 0);
		
	function component(parent,options,style,className,title) {
		var component=document.createElement("div");
		if (options)
			component.dataset.options=options;
		component.className=className;
		$.extend(component.style, style);
		component.title=title;
		return parent.appendChild(component);
	}
}

Hicurl.prototype._historyLoadedHandler=function(data){
	var pages=(this._data=data).pages;
	var treeData=[{text:"Root",children:[]}];
	for (var i=0; i<pages.length; i++) {
		var treeItem=treeData[0].children[i]={};
		var page=pages[i];
		treeItem.text=page.name||"Page "+(i+1);
		treeItem.index=i;
	}
	$(this._pageTree).tree({
		lines:true,
		data:treeData,
		onClick:$.proxy(this._pageClick,this)
	});
	
	
	var dataClone=JSON.parse(JSON.stringify(this._data));
	var numPages=dataClone.pages.length;
	for (var i=0; i<numPages; i++) {
		var pageCloneExchanges=dataClone.pages[i].exchanges;
		for (var j=0; j<pageCloneExchanges.length; j++) {
			pageCloneExchanges[j].content="&lt;content&gt;";
		}
	}
	dataClone=Hicurl._jstreeFormat(dataClone,"Root");
	for (var i=0; i<numPages; i++) {
		dataClone.children[0].children[i].id="hicurl_jstree_page_"+i;
	}
	dataClone.id="hicurl_jstree_page_root";
	$(this._jsonPanel).jstree({
	core : {
		data:dataClone
	} });
	
	//this has to be called otherwise the layout will "sometimes" be a little messed up after the writing to the
	//pageTree. calling it without the setTimeout doesn't fix it either, at least not always.
	//But like this it looks like it never gets messed up.
	setTimeout($.parser.parse);
};
Hicurl.prototype._pageClick=function(node) {
	var rootNode=$(this._jsonPanel).jstree("select_node", 1); 
	
	$(this._jsonPanel).jstree("deselect_all");
	//remove all content-tabs(except from the very left one with the title of "Content", which actuallt is a tab too
	while ($(this._contentTabs).tabs('getTab',1))
		$(this._contentTabs).tabs('close',1);
	
	if (node.index==null) {
		$(this._jsonPanel).jstree("select_node","hicurl_jstree_page_root"); 
	} else {
		//tabs don't work in textareas with nowrap-setting(whitespace in general is limited)
		var opts={indent_char:" "};//...so using non-breaking-space instead
		$(this._jsonPanel).jstree("select_node","hicurl_jstree_page_"+node.index); 
		var exchanges=this._data.pages[node.index].exchanges;
		for (var i=0; i<exchanges.length; i++) {
			var textArea=document.createElement("textArea");
			textArea.value=exchanges[i].content,opts;//textArea.value=html_beautify(exchanges[i].content,opts);
			textArea.readOnly = true;
			$(this._contentTabs).tabs('add',{
				title: !exchanges[i].error?"Success":exchanges[i].error.split("\n")[0],
				selected: true,
				content:textArea,
			});
		}
	}
};


Hicurl._jstreeFormat=function(value,key) {
	var type=$.type(value);
	var result={text:"<span class='propertyKey'>"+key+"</span> ",icon:type};
	if (value==="&lt;content&gt;")
		result.icon="html";
	if (type==="object"||type==="array") {
		var children=result.children=[];
			for (var key in value)
				if (value.hasOwnProperty(key))
					children.push(Hicurl._jstreeFormat(value[key],key));
	} else {
		result.text+=String(value);
	}
	return result;
};