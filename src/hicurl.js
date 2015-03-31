function Hicurl(domElement) {
	domElement.className+="hicurl";
	$.getJSON("assets//getHistory.php",null,this._private_dataLoaded);
}
Hicurl.prototype._private_dataLoaded=function(data) {
	console.log(data);
}