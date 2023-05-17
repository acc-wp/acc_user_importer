/*
 * This javascript code gets executed when the user presses the "Update" button.
 * It initiates a membership update by sending requests to the local Wordpress
 * PHP code.
 *       -Request the list of changed members since a certain date.
 *        This is done in a single request to the Wordpress PHP code (WP).
 * 	     -For those members, get membership information and update the database.
 *        This is done by repetitively sending requests to WP, until all
 *        members with changes have been processed.
 *       -A log window shows the progress.
 *
 * Note: this code was originally used as a test bed and had functions
 * to test directly the ACC APIs. But it has evolved and it is now mainly
 * used as a way to trigger the PHP code.
 */

var usersInData, newUsers, updatedUsers, usersWithErrors, accSyncStartTime;

(function( $ ) {
	'use strict';

	$(function() {

		//Capture Update Request
		$("#update_status_submit").on('click keydown', function(e) {
			if (e.type === 'keydown' && 13 !== e.which) { return; }
			e.preventDefault();
			jQuery(this).attr("disabled", "disabled");

			wpStartMembershipUpdate();
		});

	});

	/**
	 * Start the membership update.
	 * In this version, the client code sends request to the Wordpress server
	 * who itself contacts the ACC server API.
	 */
	function wpStartMembershipUpdate () {

		usersInData = 0;
		newUsers = 0;
		updatedUsers = 0;
		usersWithErrors = 0;

		logLocalOutput("Manual member update starting.");
		accSyncStartTime = new Date();
		logLocalOutput("Start time: " + accSyncStartTime);

		//Establish API
		wpEstablishLocalAPI(function (apiResponse) {

			//Get list of changed users
			wpRequestChangedMembers(function (changeList) {

				// Get membership data (recursive)
				if (changeList.length == 0) {
					logLocalOutput("We are done processing");
					return normalExit();
				}
				getNextDataset(changeList, 0, 3);

			}, onPostRequestFailure);
		}, onPostRequestFailure);
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
	 * Gets a list of membership changes.
	 */
	function wpRequestChangedMembers (successFn, failureFn) {

		logLocalOutput("Getting list of members with recent changes");

		// Very basic validation for token
		var accessToken = jQuery("#accUM_token").val();
		if (accessToken === undefined || String(accessToken).length == 0) {
			logLocalOutput("Error, please provide an access token.");
			if (failureFn) failureFn.call(this, responseObject);
			return;
		}

		var apiData = {'action': 'accUserAPI','request': 'getChangedMembers', 'security': ajax_object.nonce};
		jQuery.post(ajax_object.url, apiData, function(response) {
			var responseObject = JSON.parse(response);
			logLocalOutput(responseObject.log);
			if (responseObject.message == "success") {
				logLocalOutput("Number of members with changes: " + responseObject.count);
				if (successFn) successFn.call(this, responseObject.results);
			} else {
				logLocalOutput("Error: " + (responseObject.errorMessage ? responseObject.errorMessage : 'Unknown.'));
				if (failureFn) failureFn.call(this, responseObject);
			}
		});
	}


	/**
	 * Iterate through the change list until we run out. Recursive function.
	 * Each iteration, take N members, request for their latest info,
	 * and update the database. The changeList remains the same along the way.
	 * However, dataOffset increases on each iteration by the number of
	 * members we processed.  For example if we process 10 members at a time,
	 * then dataOffset will take the successive values of 0, 10, 20...
	 */
	function getNextDataset (changeList, dataOffset, apiAttemptsRemaining) {

		//Exit If Limits Exceeded
		if (apiAttemptsRemaining <= 0) {
			return onPostRequestFailure();
		}

		logLocalOutput("&nbsp;");
		logLocalOutput("Getting data for member " + dataOffset + " and on");
		//logLocalOutput(changeList);

		if (dataOffset > changeList.length) {
			logLocalOutput("Error: attempt to getNextDataset with the changeList fully processed");
			return onPostRequestFailure();
		}

		//Get Data
		wpRequestACCData(changeList, dataOffset,

			function (changeList, dataOffset, memberArray) {

				//Parse Data
				wpProccessMembershipData(changeList, dataOffset, memberArray, function (changeList, dataOffset) {
					// logLocalOutput("callback from wpProccessMembershipData");
					// logLocalOutput(changeList);
					// logLocalOutput("dataOffset=" + dataOffset);

					//Loop Through Again
					if (dataOffset < changeList.length) {
						//logLocalOutput("dataOffset " + dataOffset + " is smaller than changelist " + changeList.length);
						// logLocalOutput("&nbsp;");
						// logLocalOutput("More data found; the journey will continue.");
						// logLocalOutput("&nbsp;");

						// 2M server throttles API at 10 requests per minute max.
						// Sleep 7s to avoid HTTP errors.
						setTimeout(() => { getNextDataset(changeList, dataOffset, 3); }, 7000);
				 	}

					//Enable Buttons At End
					else {

						logLocalOutput("&nbsp;");
						logLocalOutput("<b><u>Membership update complete.</u>");
						logLocalOutput("--Parsed data for " + usersInData + " people total.");
						logLocalOutput("--Created accounts for " + newUsers + " people total.");
						logLocalOutput("--Updated data for " + updatedUsers + " people total.");
						if (usersWithErrors != 0) {
							logLocalOutput("--Errors updating " + usersWithErrors + " accounts total.</b>");
						}
						logLocalOutput("&nbsp;");

						//Now that we have updated all members, check for expiry
						wpProccessExpiry();
					}

				}, onPostRequestFailure);

			},
			//API Request Failed
			function () {
				//Try Again
				getNextDataset (changeList, dataOffset, apiAttemptsRemaining - 1);
			});
	}


	/**
	 * Gathers and returns membership data.
	 */
	function wpRequestACCData (changeList, dataOffset, successFn, failureFn) {

		var apiData = {'action': 'accUserAPI','request': 'getMemberData','security': ajax_object.nonce, 'changeList': changeList, 'offset': dataOffset};

		jQuery.post(ajax_object.url, apiData, function(response) {
			var responseObject = JSON.parse(response);
			logLocalOutput(responseObject.log);

			if (responseObject.message == "success") {
				var memberCount = responseObject.results.length;
				var nextOffset = responseObject.nextDataOffset;

				// logLocalOutput(`Success receiving data for ${memberCount} members, nextOffset=${nextOffset}`);
				// logLocalOutput("-----php side log------");
				// logLocalOutput(responseObject.log);
				// logLocalOutput("-----end of log------");

				if (successFn) successFn.call(this, changeList, nextOffset, responseObject.results);
			} else {
				logLocalOutput("Error: " + (responseObject.errorMessage ? responseObject.errorMessage : 'Unknown.'));
				if (failureFn) failureFn.call(this, responseObject);
			}

		});
	}

	/**
	 * Ask Wordpress to update database with parsed membership info.
	 */
	function wpProccessMembershipData (changeList, dataOffset, memberArray, successFn, failureFn) {

		logLocalOutput("&nbsp;");
		logLocalOutput("Will now update the Wordpress database");

		var apiData = {'action': 'accUserAPI',
					   'request': 'processMemberData',
		               'security': ajax_object.nonce,
					   'dataset': memberArray};

		jQuery.post(ajax_object.url, apiData, function(response) {
			var responseObject = JSON.parse(response);
			logLocalOutput(responseObject.log);

			//Update overall stats
			usersInData += responseObject.usersInData;
			newUsers += responseObject.newUsers;
			updatedUsers += responseObject.updatedUsers;
			usersWithErrors += responseObject.usersWithErrors;

			if (responseObject.message == "success") {
				//logLocalOutput("Success updating the Wordpress database");
				// logLocalOutput("-----php side log------");
				// logLocalOutput(responseObject.log);
				// logLocalOutput("-----end of log------");
				if (successFn) successFn.call(this, changeList, dataOffset);
			}
			else {
				logLocalOutput("Error: " + (responseObject.errorMessage ? responseObject.errorMessage : 'Unknown.'));
				if (failureFn) failureFn.call(this, responseObject);
			}
		});
	}

	/**
	 * Ask Wordpress to scan user database for expired memberships
	 */
	 function wpProccessExpiry () {

		//logLocalOutput("Requesting to process member expiry");

		var apiData = {'action': 'accUserAPI','request': 'processExpiry','security': ajax_object.nonce, 'dataset': ''};

		jQuery.post(ajax_object.url, apiData, function(response) {
			var responseObject = JSON.parse(response);
			logLocalOutput(responseObject.log);

			if (responseObject.message == "success") {
				//logLocalOutput(responseObject.log);
				logLocalOutput("Finished expiry processing.");
				logLocalOutput("&nbsp;");
				logLocalOutput("This journey has come to an end.");
				var accSyncEndTime = new Date();
				var duration = (accSyncEndTime.getTime() - accSyncStartTime.getTime()) / 1000;
				logLocalOutput("Start time: " + accSyncStartTime);
				logLocalOutput("End time: " + accSyncEndTime);
				logLocalOutput("Duration: " + duration + "seconds");
				normalExit();
			} else {
				logLocalOutput("Error: " + (responseObject.errorMessage ? responseObject.errorMessage : 'Unknown.'));
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

	function normalExit() {
		$("#update_status_submit").removeAttr("disabled");
	}

	function onPostRequestFailure (responseObject) {

		//enable buttons after proccess has stopped
		//$("#update_status_submit").attr("disabled", "");
		$("#update_status_submit").removeAttr("disabled");

		// logLocalOutput("-----php side log------");
		// logLocalOutput(responseObject.log);
		// logLocalOutput("-----end of log------");
		logLocalOutput('FAILED: Process Stopped...');
		var accSyncEndTime = new Date();
		var duration = (accSyncEndTime.getTime() - accSyncStartTime.getTime()) / 1000;
		logLocalOutput("Start time: " + accSyncStartTime);
		logLocalOutput("End time: " + accSyncEndTime);
		logLocalOutput("Duration: " + duration + "seconds");
}


})( jQuery );

/*
Note: For local testing with Chrome, disable CORS security policies via the following script:

	/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome --disable-web-security --user-data-dir /Users/razpeel/Temp

*/
