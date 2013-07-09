app._transformify=function(s,re,rp){
	var x;
	while (s!=x) {
		x = s;
		s = x.toLowerCase().replace(re,rp);
	}
	return s;
};
app.dashify=function(s){
	return app._transformify(s,/( |_|--)/g,'-');
};
app.underscorify=function(s){
	return app._transformify(s,/( |-|__)/g,'_');
};

