var usersInData, newUsers, updatedUsers, usersWithErrors;

(function( $ ) {
	'use strict';
	
	$(function() {
		
		//Capture Update Request
		$("#update_status_submit").on('click keydown', function(e) {
			if (e.type === 'keydown' && 13 !== e.which) { return; }
			e.preventDefault();
			jQuery(this).attr("disabled", "disabled");
			jQuery("#debug_status_submit").attr("disabled", "disabled");
			
			startMembershipUpdate();
		});
		
		//Capture Debug Request
		$("#debug_status_submit").on('click keydown', function(e) {
			if (e.type === 'keydown' && 13 !== e.which) { return; }
			e.preventDefault();
			jQuery("#update_status_submit").attr("disabled", "disabled");
			jQuery(this).attr("disabled", "disabled");
			
			startLocalMembershipUpdate();
		});
	});
	
	/**
	 * The journey of 1000 miles begins with a single footstep.
	 */
	function startMembershipUpdate () {
		
		usersInData = 0;
		newUsers = 0;
		updatedUsers = 0;
		usersWithErrors = 0;
		
		logLocalOutput("Member update requested started.");
		
		//Establish API
		wpEstablishLocalAPI(function (apiResponse) {
			
			//Get Token
			wpRequestACCToken(function (tokenResponse) {
			
				//Get First Data
				getNextDataset(tokenResponse.accessToken, 0, 3);
							
			}, onPostRequestFailure);
		}, onPostRequestFailure);
	}
	
	/**
	 * Iterate through datasets until we run out.
	 */
	function getNextDataset (accessToken, dataOffset, apiAttemptsRemaining) {
		
		//Exit If Limits Exceeded
		if (apiAttemptsRemaining <= 0) {
			return onPostRequestFailure();
		}
		
		//Get Data
		wpRequestACCData(accessToken, dataOffset,
			
			function (dataResponse) {
				
				//Parse Data
				wpProccessMembershipData(dataResponse.dataset, function (results) {
					
					//Add Totals
					usersInData += results.usersInData;
					newUsers += results.newUsers;
					updatedUsers += results.updatedUsers;
					usersWithErrors += results.usersWithErrors;
					
					//Loop Through Again
				 	if (dataResponse.HasNext == 1) {
						
						logLocalOutput("&nbsp;");
						logLocalOutput("More data found; the journey will continue.");
						logLocalOutput("&nbsp;");
						getNextDataset(accessToken, dataResponse.NextOffset, 3);
				 	}
			
					//Enable Buttons At End
					else {
						
						logLocalOutput("&nbsp;");
						logLocalOutput("<b><u>Membership update complete.</u>");
						logLocalOutput("--Parsed data for " + usersInData + " people total.");
						logLocalOutput("--Created accounts for " + newUsers + " people total.");
						logLocalOutput("--Updated data for " + updatedUsers + " people total.");
						logLocalOutput("--Errors updating " + usersWithErrors + " accounts total.</b>");
						
						$("#update_status_submit, #debug_status_submit").removeAttr("disabled");
						logLocalOutput("&nbsp;");
						logLocalOutput("This journey has come to an end.");
					}
			
				}, onPostRequestFailure);	
				
			},
			//API Request Failed
			function () {
				
				//Try Again
				getNextDataset (accessToken, dataOffset, apiAttemptsRemaining - 1);
				
			});
	}
	
	/**
	 * Run an API call to make sure that it passes security.
	 */
	function wpEstablishLocalAPI (successFn, failureFn) {
		
		logLocalOutput("Establishing local API connection.");
		
		var apiData = {'action': 'accUserAPI', 'request': 'establish', 'security': ajax_object.nonce};
		
		jQuery.post(ajax_object.url, apiData, function(response) {
			var responseObject = JSON.parse(response);
			
			if (responseObject.message == "established") {
				logLocalOutput("Local API connection established.");
				if (successFn) successFn.call(this, responseObject);
			}
			else {
				logLocalOutput("Error: A local API connection could not be established.");
				if (failureFn) failureFn.call(this, responseObject);
			}
		});	
	}
	
	/**
	 * Establish a connection with the ACC Database.
	 */
	function wpRequestACCToken (successFn, failureFn) {
		
		logLocalOutput("Requesting access token from national office.");
		logLocalOutput("..");
		
		var apiData = {'action': 'accUserAPI','request': 'getAccessToken','security': ajax_object.nonce};
		
		jQuery.post(ajax_object.url, apiData, function(response) {
			var responseObject = JSON.parse(response);
			
			if (responseObject.message == "success") {
				var accToken = String(responseObject.accessToken);
				logLocalOutput('Received token: ' + accToken.substr(0, 10) + '...');
				if (successFn) successFn.call(this, responseObject);
			}
			else {
				logLocalOutput("Error: Token was not granted.");
				if (failureFn) failureFn.call(this, responseObject);
			}
		});
	}
	
	/**
	 * Gathers and returns membership data.
	 */
	function wpRequestACCData (accessToken, dataOffset, successFn, failureFn) {
		
		//data validation for token
		if (accessToken === undefined || String(accessToken).length == 0) {
			logLocalOutput("Error: Invalid access token provided.");
			return;
		}
		
		logLocalOutput("Requesting membership data using token: " + String(accessToken).substr(0, 7));
		var apiData = {'action': 'accUserAPI','request': 'getMemberData','security': ajax_object.nonce, 'token': accessToken, 'offset': dataOffset};
		
		jQuery.post(ajax_object.url, apiData, function(response) {
			var responseObject = JSON.parse(response);
			
			if (responseObject.message == "success") {
				var accDataset = responseObject.dataset;
				
				logLocalOutput("Membership data received.");
				logLocalOutput("--" + responseObject.Count + " records expected.");
				logLocalOutput("--" + accDataset.length + " valid records provided.");
				logLocalOutput("--" + responseObject.TotalCount + " total records available.");
				logLocalOutput("Retrieving records from position [" + responseObject.Offset + "]");
				
				//check for duplicate emails
				var emailList = [];
				var duplicateList = [];
				for (var i = 0; i < accDataset.length; i++) {
					var userEmail = accDataset[i].Email;
					if (userEmail == undefined) break;
					if (emailList.indexOf(userEmail) != -1) {
						duplicateList.push(accDataset[i]);
					} else {
						emailList.push(userEmail);
					}
				}
				if (duplicateList.length > 0) {
					logLocalOutput("--" + duplicateList.length + " users with duplicate emails.");
					for (i = 0; i < duplicateList.length; i++) {
						logLocalOutput("&nbsp; [" + i + "]: " + duplicateList[i].FirstName + " " + duplicateList[i].LastName);
					}
					logLocalOutput("----------------");
				}
				
				//gather keys into array
				/*
				var uniqueKeys, availableKeys, i;
				uniqueKeys = [];
				for (i = 0; i < accDataset.length; i++) {
					availableKeys = Object.keys(accDataset[i]);
					uniqueKeys = uniqueKeys.concat(availableKeys.filter((item) => uniqueKeys.indexOf(item) < 0));
				}
				uniqueKeys.sort();
				logLocalOutput("--" + uniqueKeys.length + " unique values available.");
				for (i = 0; i < uniqueKeys.length; i++) {
					logLocalOutput("&nbsp; [" + i + "]: " + uniqueKeys[i]);
				}
				logLocalOutput("----------------");
				*/
				
				if (successFn) successFn.call(this, responseObject);
			}
			else {
				logLocalOutput("Error: " + (responseObject.errorMessage ? responseObject.errorMessage : 'Unknown.'));
				if (failureFn) failureFn.call(this, responseObject);
			}
			
		});
	}
	
	/**
	 * Ask Wordpress to update database with parsed membership info.
	 */
	function wpProccessMembershipData (accDataset, successFn, failureFn) {
		
		logLocalOutput("Waiting for dataset processing to complete.");
		logLocalOutput("..");
		
		var apiData = {'action': 'accUserAPI','request': 'processMemberData','security': ajax_object.nonce, 'dataset': JSON.stringify(accDataset)};
		
		jQuery.post(ajax_object.url, apiData, function(response) {
			var responseObject = JSON.parse(response);
			
			if (responseObject.message == "success") {
				logLocalOutput(responseObject.log);
				logLocalOutput("Membership data processed.");
				if (successFn) successFn.call(this, responseObject);
			}
			else {
				logLocalOutput("Error: " + (responseObject.errorMessage ? responseObject.errorMessage : 'Unknown.'));
				if (failureFn) failureFn.call(this, responseObject);
			}
			
		});
	}
	
	/**
	 * Unused - Test Mode -> Run the journey, while we are watching.
	 */
	function startLocalMembershipUpdate () {
		
		logLocalOutput("Test Mode >> Member update requested started.");
		
		requestACCToken(function (token) {
			
			$("#update_status_submit, #debug_status_submit").removeAttr("disabled");
			
			requestACCData(token.access_token, function (data) {
				
				logLocalOutput("</br>-Test Mode >> Update complete.");
				
				//Reable Update Buttons
				$("#update_status_submit, #debug_status_submit").removeAttr("disabled");
			});	
		});
		
	}
	
	/**
	 * Unused - Requests access token from national office. Runs locally for debug purposes.
	 *
	 * Used mostly for testing during development, but left in project for future use.
	 * i.e. We may decide to expand on this via Javascript in the future.
	 */
	function requestACCToken (successFn, failureFn) {
		
		logLocalOutput("Test Mode >> Requesting access token from national office.");
		logLocalOutput("..");
		
		jQuery.ajax("https://www.alpineclubofcanada.ca/" + jQuery("#accUM_tokenURI").val(), {
			type: "POST",
			data: {
				"grant_type": "password",
				"username":jQuery("#accUM_username").val(), 
				"password":jQuery("#accUM_password").val()
			},
			headers: {
				"Content-Type": "application/x-www-form-urlencoded",
			}, 
			success: function (responseObject) {
				var accessToken = responseObject["accessToken"];
				logLocalOutput('Test Mode >> Received token: ' + accessToken.substr(0, 10) + '...');
				if (successFn) successFn.call(this, responseObject);
			},
			error: function (responseObject) {
				logLocalOutput("Test Mode >> Token was not granted.");
				if (failureFn) failureFn.call(this, responseObject);
			}
		});
	}
	
	/**
	 * Unused - Requests membership dataset from national office.
	 *
	 * Used mostly for testing during development, but left in project for future use.
	 * i.e. We may decide to expand on this via Javascript in the future.
	 */
	function requestACCData (accessToken, successFn, failureFn) {
		
		logLocalOutput("Test Mode >> Requesting membership data using token.");
		logLocalOutput("..");
		
		var memberURI = "https://www.alpineclubofcanada.ca/" + jQuery("#accUM_memberURI").val();
		jQuery.ajax(memberURI, {
			type: "GET",
			contentType: "application/json",
			headers: {
				Authorization: "Bearer " + accessToken
			},
			success: function (responseObject) {
				var accDataset = responseObject;
				logLocalOutput('Test Mode >> Data returned: ' + accessToken.substr(0, 30) + '...');
				logLocalOutput("--" + accDataset.Count + " records in dataset.");
				logLocalOutput("--" + accDataset.TotalCount + " total records.");
				if (successFn) successFn.call(this, responseObject);
			},
			error: function (responseObject) {
				logLocalOutput("Test Mode >> Data not given.");
				if (failureFn) failureFn.call(this, responseObject);
			}
		});
	}
	
	/**
	 * Log local output to 'Update Status Window' on the plugin page.
	 */
	function logLocalOutput (val) {
		
		if (!val) return;
		var l = jQuery("#update_log");
		var logText = l.html();
		
		//remove '..' from end of textbox if one exists.
		if(logText.endsWith('..')) {
			l.html(logText.substr(0, logText.length - 6));
		}
			
		if ( logText.length >= 29) {
			l.append("<br/>").append(val.toString()); //include a new line
		}
		else {
			l.append(val.toString()); //doesn't need a new line
		}
	}
	
	function onPostRequestFailure () {
		
		//enable buttons after proccess has stopped
		$("#update_status_submit").attr("disabled", "");
		$("#debug_status_submit").attr("disabled", "");
		
		logLocalOutput('FAILED: Process Stopped...');
	}
	

})( jQuery );

/*
Note: For local testing with Chrome, disable CORS security policies via the following script:

	/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome --disable-web-security --user-data-dir /Users/razpeel/Temp

*/
