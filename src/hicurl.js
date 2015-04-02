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
	var contentTabs=component(contentPanel,null,{width:"100%"},"easyui-tabs content");
	component(contentTabs).title="Content";
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
	setTimeout($.parser.parse);
};
Hicurl.prototype._pageClick=function(node) {
	var pageClone=JSON.parse(JSON.stringify(this._data.pages[node.index]))
	for (var i=0; i<pageClone.exchanges.length; i++) {
		pageClone.exchanges[i].content="&lt;content&gt;";
	}
$(this._jsonPanel).jstree({
	core : {
		data:Hicurl._jstreeFormat(pageClone,node.text)
	} });
	
};


Hicurl._jstreeFormat=function(value,key) {
	var type=$.type(value);
	var result={text:"<span class='propertyKey'>"+key+"</span> ",icon:type};
	if (value=="&lt;content&gt;")
		result.icon="html";
	if (type=="object"||type=="array") {
		var children=result.children=[];
			for (var key in value)
				if (value.hasOwnProperty(key))
					children.push(Hicurl._jstreeFormat(value[key],key));
	} else {
		result.text+=String(value);
	}
	return result;
}