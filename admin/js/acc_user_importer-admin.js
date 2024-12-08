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

(function ($) {
  "use strict";

  $(function () {
    //Capture Update Request
    $("#update_status_submit").on("click keydown", function (e) {
      if (e.type === "keydown" && 13 !== e.which) {
        return;
      }
      e.preventDefault();
      jQuery(this).attr("disabled", "disabled");

      wpStartMembershipUpdate();
    });
  });

  /**
   * Run an API call to kick off the plugin import process.
   */
  function wpStartMembershipUpdate() {
    accSyncStartTime = new Date();
    logLocalOutput("Start time: " + accSyncStartTime);
    logLocalOutput(
      "Sending request to server, waiting for logs (be patient)...<br/>",
    );

    var apiData = {
      action: "accUserAPI",
      request: "import",
      security: ajax_object.nonce,
    };

    jQuery.post(ajax_object.url, apiData, function (response) {
      var responseObject = JSON.parse(response);
      logLocalOutput(responseObject.log);
      normalExit();
    });
  }

  /**
   * Log local output to 'Update Status Window' on the plugin page.
   */
  function logLocalOutput(val) {
    if (!val) return;
    var l = jQuery("#update_log");
    var logText = l.html();

    //remove '..' from end of textbox if one exists.
    if (logText.endsWith("..")) {
      l.html(logText.substr(0, logText.length - 6));
    }

    if (logText.length >= 29) {
      l.append("<br/>").append(val.toString()); //include a new line
    } else {
      l.append(val.toString()); //doesn't need a new line
    }
  }

  function normalExit() {
    $("#update_status_submit").removeAttr("disabled");
  }
})(jQuery);

/*
Note: For local testing with Chrome, disable CORS security policies via the following script:

	/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome --disable-web-security --user-data-dir /Users/razpeel/Temp

*/
