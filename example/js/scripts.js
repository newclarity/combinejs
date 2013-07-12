(function() {
app = {};
app._transformify=function(s,re,rp){
	var x;
	while (s!=x) {
		x = s;
		s = x.toLowerCase().replace(re,rp);
	}
	return s.replace(/[^\w\s]/gi, '');
};
app.dashify=function(s){
	return app._transformify(s,/( |_|--)/g,'-');
};
app.underscorify=function(s){
	return app._transformify(s,/( |-|__)/g,'_');
};
/*
 * Assign a greeting
 */
app.greeting = app.dashify('Hello CombineJS!');
/*
 * Assign greeting
 */
app.farewell = app.underscorify('Goodbye now.');
/**
 * Execute the code now.
 */
app.execute = function() {
	document.body.innerHTML += app.greeting + " " + app.farewell;
};
app.execute();
})();
//@ sourceMappingURL=scripts.js.map
