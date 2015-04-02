function Hicurl(domElement,dataUrl) {
	this._domElement=domElement;
	domElement.className+=" hicurl easyui-layout";
	$.ajax({
        url : dataUrl,
        dataType : 'json',
        context : this,
        complete : this._historyLoadedHandler
    });
	
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
	var contentPanel=component(centerLayout,"region:'south',title:'',split:true",{height:"50%"});
	var contentTabs=this._contentTabs=component(contentPanel,null,{width:"100%"},"easyui-tabs content");
	component(contentTabs,"selected: false").title="Content";
	$.parser.parse();
	$(contentTabs).tabs('disableTab', 0);
		
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

Hicurl.prototype._historyLoadedHandler=function(event){
	var pages=(this._data=event.responseJSON).pages;
	var treeData=[];
	for (var i=0; i<pages.length; i++) {
		var treeItem=treeData[i]={};
		var page=pages[i];
		treeItem.text=page.name||"Page "+(i+1);
		treeItem.index=i;
	}
	$(this._pageTree).tree({
		data:treeData,
		onClick:$.proxy(this._pageClick,this)
	});
	
	//this has to be called otherwise the layout will "sometimes" be a little messed up after the writing to the
	//pageTree. calling it without the setTimeout doesn't fix it either, at least not always.
	//But like this it looks like it never gets messed up.
	setTimeout($.parser.parse);
};
Hicurl.prototype._pageClick=function(node) {
	var pageClone=JSON.parse(JSON.stringify(this._data.pages[node.index]));
	for (var i=0; i<pageClone.exchanges.length; i++) {
		pageClone.exchanges[i].content="&lt;content&gt;";
	}
	var opts={indent_char:"  "};//tabs don't work in textareas with nowrap-setting
$(this._jsonPanel).jstree({
	core : {
		data:Hicurl._jstreeFormat(pageClone,node.text)
	} });
	var exchanges=this._data.pages[node.index].exchanges;
	for (var i=0; i<exchanges.length; i++) {
		var textArea=tt=document.createElement("textArea");
		textArea.value=html_beautify(exchanges[i].content,opts);
		textArea.readOnly = true;
		textArea.className="pageContent";
		$(this._contentTabs).tabs('add',{
			title: !exchanges[i].error?"Success":exchanges[i].error,
			selected: true,
			content:textArea,
		});
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