/* Additional custom functions for NeSentry NMS */

/* add a loader, then use jquery to dynamically load the content */
function loadPage(linkID, containerID, link, useLoader){
    /* Set the right menu item active */
    if (linkID){ $('.sidebar-menu li').removeClass("active"); $(linkID).addClass("active"); }
    
    /* add a custom loader gif */
    if (typeof useLoader === 'undefined') { useLoader = true; }
    if (useLoader===true){
        var loaders = ["ani_come_with_me.gif","ani_dancing_dalek.gif","ani_death_ray.gif","ani_edbull_glomp.gif","ani_excited.gif","ani_free_pacman.gif","ani_headbang.gif","ani_little_boxes.gif","ani_ninja_vs_pirate.gif","ani_pump_it_up.gif","ani_read_me.gif","ani_rescue_mission.gif","ani_run_away.gif","ani_see_saw.gif","ani_shuffelin.gif","ani_silent_ninja_run_of_awesomness.gif","ani_slinky.gif","ani_walking_llama.gif"];
        var randomNum = Math.floor(Math.random()*loaders.length);
        var loaderHTML = "<center><br><br><br><img src=img/" + loaders[randomNum] + " title=\"Loading Content\" /><br><small>Loading Content, Please Wait...</small></center>";
    }

    /* grab the document and put it in its place: $("#app-content").load("dashboard.html");*/
    $(containerID).html(loaderHTML);
    $(containerID).load(link, function(responseTxt, statusTxt, xhr){
        if(statusTxt === "success") console.log(linkID + " loaded successfully!");
        if(statusTxt === "error") console.log(linkID + " Error: " + xhr.status + ": " + xhr.statusText);
    });
}
/* Flatiron director routing */
var router = Router({
    '': function(){ loadPage("#dashboard", "#app-content", "dashboard.html");},
    '/dashboard': function(){ loadPage("#dashboard", "#app-content", "dashboard.html");},
    '/settings': function(){ loadPage("#settings", "#app-content", "settings.html");},
    '/logs': function(){ loadPage("#logs", "#app-content", "logs.html");},
    '/devices': {
        '/:id': { on: function(id){ loadPage("#devices", "#app-content", "devices.php?name=" + id ); }},
        on: function(){ loadPage("#devices", "#app-content", "devices.php");}
    }
}).configure();
/*var allCalls = function(){console.log("do something every call");};
router.configure({on: allCalls});*/

$( document ).ready(function() {
    if(!window.location.hash) window.location.hash = "#/dashboard"; /* set the default location */
    router.init();
});

/* End Routing */



//for testing go to http://jsfiddle.net/rasvM/
//allows loading of content on the fly
function ajaxRequest() {
	try { var request = new XMLHttpRequest(); }
	catch(e1) {
		try { request = new ActiveXObject("Msxml2.XMLHTTP"); }
		catch(e2) {
			try { request = new ActiveXObject("Microsoft.XMLHTTP"); }
			catch(e3) { request = false; }
		}
	}
	return request;
}

//Ajax to pull down external content in the background
//params(ex. id=myid&view=top)
function getContent(page,id,params) {
	if (params===null) params="";
	var load_id = id;
	var loaders = ["ani_come_with_me.gif","ani_dancing_dalek.gif","ani_death_ray.gif","ani_edbull_glomp.gif","ani_excited.gif","ani_free_pacman.gif","ani_headbang.gif","ani_little_boxes.gif","ani_ninja_vs_pirate.gif","ani_pump_it_up.gif","ani_read_me.gif","ani_rescue_mission.gif","ani_run_away.gif","ani_see_saw.gif","ani_shuffelin.gif","ani_silent_ninja_run_of_awesomness.gif","ani_slinky.gif","ani_walking_llama.gif"];
	var randomNum = Math.floor(Math.random()*loaders.length);
	
	if (document.getElementById(id + "_loadarea")!==null) load_id = id + "_loadarea";
	
	document.getElementById(load_id).innerHTML = "<center><img src=images/" + loaders[randomNum] + " title=\"Loading Content\" /><br><i>Loading Content, Please Wait...</i></center>";
	
	
	request = new ajaxRequest();
	request.open("POST", page, true);
	request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	//request.setRequestHeader("Content-length", params.length);
	//request.setRequestHeader("Connection", "close");
	
	request.onreadystatechange = function() {
		var toID = id;
		if (this.readyState === 4) {
			if (this.status === 200) {
				if (this.responseText !== null) {
					//populate the data
					var s = this.responseText.replace(/^\s+|\s+$/g, '');
					document.getElementById(toID).innerHTML = s;
					parseScript(s);
					
				} else document.getElementById(toID).innerHTML = ""; //alert("Ajax error: No data received")
			} else document.getElementById(toID).innerHTML = "Error: " + this.statusText; //alert("Ajax error: " + this.statusText)
		}
	};
	request.send(params);
}

//if the ajax returns javascript, lets run it.
function parseScript(_source) {
	var source = _source;
	
	// find script and evaluate it
	while(source.indexOf("<script") > -1 || source.indexOf("</script") > -1) {
		var s = source.indexOf("<script");	var s_e = source.indexOf(">", s);
		var e = source.indexOf("</script", s);	var e_e = source.indexOf(">", e);
		
		// Add to scripts array
		if (source.substring(s_e+1, e) !== "") {
			eval(source.substring(s_e+1, e));
		}
		source = source.substring(0, s) + source.substring(e_e+1);
	}
}
/*
function toggle(id) {
	var e = document.getElementById(id);
	e.style.visibility = (e.is(':visible') ? 'hidden' :  'visible');
	this.text = "clicked " + (e.is(':visible') ? 'hide' :  'show');
} */