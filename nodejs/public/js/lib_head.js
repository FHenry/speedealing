// Copyright (C) 2005-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
// Copyright (C) 2005-2013 Regis Houssin        <regis.houssin@capnetworks.com>
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program. If not, see <http://www.gnu.org/licenses/>.
// or see http://www.gnu.org/

//
// \file       htdocs/core/js/lib_head.js
// \brief      File that include javascript functions (included if option use_javascript activated)
//

// Returns an object given an id
function getObjectFromID(id){
	var theObject;
	if(document.getElementById)
		theObject=document.getElementById(id);
	else
		theObject=document.all[id];
	return theObject;
}

// This Function returns the top position of an object
function getTop(theitem){
	var offsetTrail = theitem;
	var offsetTop = 0;
	while (offsetTrail) {
		offsetTop += offsetTrail.offsetTop;
		offsetTrail = offsetTrail.offsetParent;
	}
	if (navigator.userAgent.indexOf("Mac") != -1 && typeof document.body.leftMargin != "undefined") 
		offsetLeft += document.body.TopMargin;
	return offsetTop;
}

// This Function returns the left position of an object
function getLeft(theitem){
	var offsetTrail = theitem;
	var offsetLeft = 0;
	while (offsetTrail) {
		offsetLeft += offsetTrail.offsetLeft;
		offsetTrail = offsetTrail.offsetParent;
	}
	if (navigator.userAgent.indexOf("Mac") != -1 && typeof document.body.leftMargin != "undefined") 
		offsetLeft += document.body.leftMargin;
	return offsetLeft;
}


// Create XMLHttpRequest object and load url
// Used by calendar or other ajax processes
// Return req built or false if error
function loadXMLDoc(url,readyStateFunction,async) 
{
	// req must be defined by caller with
	// var req = false;
 
	// branch for native XMLHttpRequest object (Mozilla, Safari...)
	if (window.XMLHttpRequest)
	{
		req = new XMLHttpRequest();
		
	// if (req.overrideMimeType) {
	// req.overrideMimeType('text/xml');
	// }
	}
	// branch for IE/Windows ActiveX version
	else if (window.ActiveXObject)
	{
		try
		{
			req = new ActiveXObject("Msxml2.XMLHTTP");
		}
		catch (e)
		{
			try {
				req = new ActiveXObject("Microsoft.XMLHTTP");
			} catch (e) {}
		} 
	}

	// If XMLHttpRequestObject req is ok, call URL
	if (! req)
	{
		alert('Cannot create XMLHTTP instance');
		return false;
	}

	if (readyStateFunction) req.onreadystatechange = readyStateFunction;
	// Exemple of function for readyStateFuncyion:
	// function ()
	// {
	// if ( (req.readyState == 4) && (req.status == 200) ) {
	// if (req.responseText == 1) { newStatus = 'AAA'; }
	// if (req.responseText == 0) { newStatus = 'BBB'; }
	// if (currentStatus != newStatus) {
	// if (newStatus == "AAA") { obj.innerHTML = 'AAA'; }
	// else { obj.innerHTML = 'BBB'; }
	// currentStatus = newStatus;
	// }
	// }
	// }
	req.open("GET", url, async);
	req.send(null);
	return req;
}

// To hide/show select Boxes with IE6 (and only IE6 because IE6 has a bug and
// not put popup completely on the front)
function hideSelectBoxes() {
	var brsVersion = parseInt(window.navigator.appVersion.charAt(0), 10);
	if (brsVersion <= 6 && window.navigator.userAgent.indexOf("MSIE 6") > -1) 
	{  
		for(var i = 0; i < document.all.length; i++) 
		{
			if(document.all[i].tagName)
				if(document.all[i].tagName == "SELECT")
					document.all[i].style.visibility="hidden";
		}
	}
}
function displaySelectBoxes() {
	var brsVersion = parseInt(window.navigator.appVersion.charAt(0), 10);
	if (brsVersion <= 6 && window.navigator.userAgent.indexOf("MSIE 6") > -1) 
	{  
		for(var i = 0; i < document.all.length; i++) 
		{
			if(document.all[i].tagName)
				if(document.all[i].tagName == "SELECT")
					document.all[i].style.visibility="visible";
		}
	}
}



/*
 * ================================================================= 
 * Function:
 * formatDate (javascript object Date(), format) Purpose: Returns a date in the
 * output format specified. The format string can use the following tags: Field |
 * Tags -------------+------------------------------- Year | yyyy (4 digits), yy
 * (2 digits) Month | MM (2 digits) Day of Month | dd (2 digits) Hour (1-12) |
 * hh (2 digits) Hour (0-23) | HH (2 digits) Minute | mm (2 digits) Second | ss
 * (2 digits) Author: Laurent Destailleur Author: Matelli (see
 * http://matelli.fr/showcases/patchs-dolibarr/update-date-input-in-action-form.html)
 * Licence: GPL
 * ==================================================================
 */
function formatDate(date,format)
{
	// alert('formatDate date='+date+' format='+format);
	
	// Force parametres en chaine
	format=format+"";
	
	var result="";

	var year=date.getYear()+"";
	if (year.length < 4) {
		year=""+(year-0+1900);
	}
	var month=date.getMonth()+1;
	var day=date.getDate();
	var hour=date.getHours();
	var minute=date.getMinutes();
	var seconde=date.getSeconds();

	var i=0;
	while (i < format.length)
	{
		c=format.charAt(i);	// Recupere char du format
		substr="";
		j=i;
		while ((format.charAt(j)==c) && (j < format.length))	// Recupere char
		// successif
		// identiques
		{
			substr += format.charAt(j++);
		}

		// alert('substr='+substr);
		if (substr == 'yyyy')      {
			result=result+year;
		}
		else if (substr == 'yy')   {
			result=result+year.substring(2,4);
		}
		else if (substr == 'M')    {
			result=result+month;
		}
		else if (substr == 'MM')   {
			result=result+(month<1||month>9?"":"0")+month;
		}
		else if (substr == 'd')    {
			result=result+day;
		}
		else if (substr == 'dd')   {
			result=result+(day<1||day>9?"":"0")+day;
		}
		else if (substr == 'hh')   {
			if (hour > 12) hour-=12;
			result=result+(hour<0||hour>9?"":"0")+hour;
		}
		else if (substr == 'HH')   {
			result=result+(hour<0||hour>9?"":"0")+hour;
		}
		else if (substr == 'mm')   {
			result=result+(minute<0||minute>9?"":"0")+minute;
		}
		else if (substr == 'ss')   {
			result=result+(seconde<0||seconde>9?"":"0")+seconde;
		}
		else {
			result=result+substr;
		}
		
		i+=substr.length;
	}

	// alert(result);
	return result;
}


/*
 * ================================================================= 
 * Function:
 * getDateFromFormat(date_string, format_string) Purpose: This function takes a
 * date string and a format string. It parses the date string with format and it
 * returns the date as a javascript Date() object. If date does not match
 * format, it returns 0. The format string can use the following tags: 
 * Field        | Tags
 * -------------+-----------------------------------
 * Year         | yyyy (4 digits), yy (2 digits) 
 * Month        | MM (2 digits) 
 * Day of Month | dd (2 digits) 
 * Hour (1-12)  | hh (2 digits) 
 * Hour (0-23)  | HH (2 digits) 
 * Minute       | mm (2 digits) 
 * Second       | ss (2 digits)
 * Author: Laurent Destailleur 
 * Licence: GPL
 * ==================================================================
 */
function getDateFromFormat(val,format)
{
	// alert('getDateFromFormat val='+val+' format='+format);

	// Force parametres en chaine
	val=val+"";
	format=format+"";

	if (val == '') return 0;
	
	var now=new Date();
	var year=now.getYear();
	if (year.length < 4) {
		year=""+(year-0+1900);
	}
	var month=now.getMonth()+1;
	var day=now.getDate();
	var hour=now.getHours();
	var minute=now.getMinutes();
	var seconde=now.getSeconds();

	var i=0;
	var d=0;    // -d- follows the date string while -i- follows the format
	// string

	while (i < format.length)
	{
		c=format.charAt(i);	// Recupere char du format
		substr="";
		j=i;
		while ((format.charAt(j)==c) && (j < format.length))	// Recupere char
		// successif
		// identiques
		{
			substr += format.charAt(j++);
		}

		// alert('substr='+substr);
		if (substr == "yyyy") year=getIntegerInString(val,d,4,4); 
		if (substr == "yy")   year=""+(getIntegerInString(val,d,2,2)-0+1900); 
		if (substr == "MM" ||substr == "M") 
		{ 
			month=getIntegerInString(val,d,1,2); 
			d -= 2- month.length; 
		} 
		if (substr == "dd") 
		{ 
			day=getIntegerInString(val,d,1,2); 
			d -= 2- day.length; 
		} 
		if (substr == "HH" ||substr == "hh" ) 
		{ 
			hour=getIntegerInString(val,d,1,2); 
			d -= 2- hour.length; 
		} 
		if (substr == "mm"){ 
			minute=getIntegerInString(val,d,1,2); 
			d -= 2- minute.length; 
		} 
		if (substr == "ss") 
		{ 
			seconde=getIntegerInString(val,d,1,2); 
			d -= 2- seconde.length; 
		} 
	
		i+=substr.length;
		d+=substr.length;
	}
	
	// Check if format param are ok
	if (year==null||year<1) {
		return 0;
	}
	if (month==null||(month<1)||(month>12)) {
		return 0;
	}
	if (day==null||(day<1)||(day>31)) {
		return 0;
	}
	if (hour==null||(hour<0)||(hour>24)) {
		return 0;
	}
	if (minute==null||(minute<0)||(minute>60)) {
		return 0;
	}
	if (seconde==null||(seconde<0)||(seconde>60)) {
		return 0;
	}
		
	// alert(year+' '+month+' '+day+' '+hour+' '+minute+' '+seconde);
	var newdate=new Date(year,month-1,day,hour,minute,seconde);

	return newdate;
}

/*
 * ================================================================= 
 * Function:
 * stringIsInteger(string) 
 * Purpose: Return true if string is an integer
 * ==================================================================
 */
function stringIsInteger(str)
{
	var digits="1234567890";
	for (var i=0; i < str.length; i++)
	{
		if (digits.indexOf(str.charAt(i))==-1)
		{
			return false;
		}
	}
	return true;
}

/*
 * ================================================================= 
 * Function:
 * getIntegerInString(string,pos,minlength,maxlength) 
 * Purpose: Return part of string from position i that is integer
 * ==================================================================
 */
function getIntegerInString(str,i,minlength,maxlength)
{
	for (var x=maxlength; x>=minlength; x--)
	{
		var substr=str.substring(i,i+x);
		if (substr.length < minlength) {
			return null;
		}
		if (stringIsInteger(substr)) {
			return substr;
		}
	}
	return null;
}


/*
 * ================================================================= 
 * Purpose:
 * Clean string to have it url encoded 
 * Input: s 
 * Author: Laurent Destailleur
 * Licence: GPL
 * ==================================================================
 */
function urlencode(s) {
	news=s;
	news=news.replace(/\+/gi,'%2B');
	news=news.replace(/&/gi,'%26');
	return news;
}


/*
 * ================================================================= 
 * Purpose: Show a popup HTML page. 
 * Input:   url,title 
 * Author:  Laurent Destailleur 
 * Licence: GPL 
 * ==================================================================
 */
function newpopup(url,title) {
	var argv = newpopup.arguments;
	var argc = newpopup.arguments.length;
	tmp=url;
	var l = (argc > 2) ? argv[2] : 600;
	var h = (argc > 3) ? argv[3] : 400;
	var wfeatures="directories=0,menubar=0,status=0,resizable=0,scrollbars=1,toolbar=0,width="+l+",height="+h+",left=" + eval("(screen.width - l)/2") + ",top=" + eval("(screen.height - h)/2");
	fen=window.open(tmp,title,wfeatures);
	return false;
}


/*
 * ================================================================= 
 * Purpose:
 * Applique un delai avant execution. Used for autocompletion of companies.
 * Input:   funct, delay 
 * Author:  Regis Houssin 
 * Licence: GPL
 * ==================================================================
 */
function ac_delay(funct,delay) {
	// delay before start of action
	setTimeout(funct,delay);
}


/*
 * ================================================================= 
 * Purpose:
 * Clean values of a "Sortable.serialize". Used by drag and drop.
 * Input:   expr 
 * Author:  Regis Houssin 
 * Licence: GPL
 * ==================================================================
 */
function cleanSerialize(expr) {
	if (typeof(expr) != 'string') return '';
	var reg = new RegExp("(&)", "g");
	var reg2 = new RegExp("[^A-Z0-9,]", "g");
	var liste1 = expr.replace(reg, ",");
	var liste = liste1.replace(reg2, "");
	return liste;
}


/*
 * ================================================================= 
 * Purpose: Display a temporary message in input text fields (For showing help message on
 *          input field).
 * Input:   fieldId
 * Input:   message
 * Author:  Regis Houssin 
 * Licence: GPL
 * ==================================================================
 */
function displayMessage(fieldId,message) {
	var textbox = document.getElementById(fieldId);
	if (textbox.value == '') {
		textbox.style.color = 'grey';
		textbox.value = message;
	}
}

/*
 * ================================================================= 
 * Purpose: Hide a temporary message in input text fields (For showing help message on
 *          input field). 
 * Input:   fiedId 
 * Input:   message 
 * Author:  Regis Houssin
 * Licence: GPL
 * ==================================================================
 */
function hideMessage(fieldId,message) {
	var textbox = document.getElementById(fieldId);
	textbox.style.color = 'black';
	if (textbox.value == message) textbox.value = '';
}

/*
 * Request Core Method
 */
function requestCore(action, string, element, option) {
	return $.ajax({
		type: 'POST',
		url: 'core/ajax/core.php',
		data: { action: action, string: string, element: element, option: option},
		async: false
	}).responseText;
}

/*
 * Set module status
 */
function setModule(action, id, value) {
	$.post('core/ajax/moduleonoff.php', {
		action: action,
		id: id,
		value: value
	},
	function(result) {
		
	}, 'json');
}

/*
 * 
 */
function setConstant(url, code, input, entity) {
	$.get( url, {
		action: "set",
		name: code,
		entity: entity
	},
	function() {
		$("#set_" + code).hide();
		$("#del_" + code).show();
		$.each(input, function(type, data) {
			// Enable another element
			if (type == "disabled") {
				$.each(data, function(key, value) {
					$("#" + value).removeAttr("disabled");
					if ($("#" + value).hasClass("butActionRefused") == true) {
						$("#" + value).removeClass("butActionRefused");
						$("#" + value).addClass("butAction");
					}
				});
			// Show another element
			} else if (type == "showhide" || type == "show") {
				$.each(data, function(key, value) {
					$("#" + value).show();
				});
			// Set another constant
			} else if (type == "set") {
				$.each(data, function(key, value) {
					$("#set_" + key).hide();
					$("#del_" + key).show();
					$.get( url, {
						action: "set",
						name: key,
						value: value,
						entity: entity
					});
				});
			}
		});
	});
}

/*
 * 
 */
function delConstant(url, code, input, entity) {
	$.get( url, {
		action: "del",
		name: code,
		entity: entity
	},
	function() {
		$("#del_" + code).hide();
		$("#set_" + code).show();
		$.each(input, function(type, data) {
			// Disable another element
			if (type == "disabled") {
				$.each(data, function(key, value) {
					$("#" + value).attr("disabled", true);
					if ($("#" + value).hasClass("butAction") == true) {
						$("#" + value).removeClass("butAction");
						$("#" + value).addClass("butActionRefused");
					}
				});
			// Hide another element
			} else if (type == "showhide" || type == "hide") {
				$.each(data, function(key, value) {
					$("#" + value).hide();
				});
			// Delete another constant
			} else if (type == "del") {
				$.each(data, function(key, value) {
					$("#del_" + value).hide();
					$("#set_" + value).show();
					$.get( url, {
						action: "del",
						name: value,
						entity: entity
					});
				});
			}
		});
	});
}

/*
 * 
 */
function confirmConstantAction(action, url, code, input, box, entity, yesButton, noButton) {
	$("#confirm_" + code)
	.attr("title", box.title)
	.html(box.content)
	.dialog({
		resizable: false,
		height: 170,
		width: 500,
		modal: true,
		buttons: [
		{
			text : yesButton,
			click : function() {
				if (action == "set") {
					setConstant(url, code, input, entity);
				} else if (action == "del") {
					delConstant(url, code, input, entity);
				}
				// Close dialog
				$(this).dialog("close");
				// Execute another function
				if (box.function) {
					var fnName = box.function;
					if (window.hasOwnProperty(fnName)) {
						window[fnName]();
					}
				}
			}
		},
		{
			text : noButton,
			click : function() {
				$(this).dialog("close");
			}
		}
		]
	});
}

/*
 * Set Trash status
 */
function setTrashStatus() {
	var trashStatus = requestCore('getTrash', 'count');
	if (trashStatus) {
		var trash = $('#shortcuts li.trashList a.shortcut-trash-empty');
		trash.removeClass('shortcut-trash-empty').addClass('shortcut-trash-full');
	} else {
		var trash = $('#shortcuts li.trashList a.shortcut-trash-full');
		trash.removeClass('shortcut-trash-full').addClass('shortcut-trash-empty');
	}
}

/*
 * box actions (show/hide, remove)
 */
prth_box_actions = {
	init: function() {
		$('.box_actions').each(function() {
			$(this).append('<span class="bAct_hide"><img src="theme/blank.gif" class="bAct_x" alt="" /></span>');
			$(this).append('<span class="bAct_toggle"><img src="theme/blank.gif" class="bAct_minus" alt="" /></span>');
			$(this).find('.bAct_hide').on('click', function() {
				$(this).closest('.box_c').fadeOut('slow',function() {
					$(this).remove();
				});
			});
			$(this).find('.bAct_toggle').on('click', function() {
				if( $(this).closest('.box_c_heading').next('.box_c_content').is(':visible') ) {
					$(this).closest('.box_c_heading').next('.box_c_content').slideUp('slow',function() {
					});
					$(this).html('<img src="theme/blank.gif" class="bAct_plus" alt="" />');
				} else {
					$(this).closest('.box_c_heading').next('.box_c_content').slideDown('slow',function() {
					});
					$(this).html('<img src="theme/blank.gif" class="bAct_minus" alt="" />');
				}
			});
		});
	}
};

//* jQuery tools tabs
/*prth_tabs = {
	init: function() {
		$(".tabs").flowtabs(".box_c_content > .tab_pane");
	}
};*/

//* infinite tabs (jQuery UI tabs)
prth_infinite_tabs = {
	init: function() {
		$(".ui_tabs").tabs({
			scrollable: true
		});
	}
};

/* 
 * Timer for delayed keyup function
 */
(function($){
	$.widget("ui.onDelayedKeyup", {
		_init : function() {
			var self = this;
			$(this.element).bind('keyup input', function() {
				if(typeof(window['inputTimeout']) != "undefined"){
					window.clearTimeout(inputTimeout);
				}  
				var handler = self.options.handler;
				window['inputTimeout'] = window.setTimeout(function() {
					handler.call(self.element)
				}, self.options.delay);
			});
		},
		options: {
			handler: $.noop(),
			delay: 500
		}
	});
})(jQuery);

