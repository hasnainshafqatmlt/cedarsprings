/* Collects the loads camp options based upon the chosen camper */
var campers;

// loads the campers from the account and displays them in the grid
function displayCampers() {
  // Get the URL parameters
  const urlParams = new URLSearchParams(window.location.search);
  const testmode = urlParams.get("duckfeet");

  var host = "https://cedarsprings.camp/camps/summer/queue/";
  hideCampers();
  showLoadingBox();
  // need to send the account number and the account key for user validation
  const account = getCookie("account");
  const key = getCookie("key");

  // if we don't have the cookie elements, log the user out and stop processing
  if (key.length == 0 || account.length == 0) {
    console.log(" in displayCampers function ::");
    showAsLoggedOut();
    return false;
  }

  // send the data to the server for validation
  const formData = {
    action: "get_campers",
    key,
    account,
    testmode:
      typeof testmode !== "undefined" && testmode === "true" ? "true" : "false",
  };

  $.ajax({
    type: "POST",
    url: custom_camp_ajax.ajax_url,
    data: formData,
    dataType: "json",
  })
    .done(function (data) {
      // if the server fails user validation, log out the user
      if (data["Authenticated"] !== true) {
        console.log("Ultracamp shows that the user credentials have expired.");
        userReauthorize();
        return false;
      }

      // with the list of campers returned - go and get their camp info
      // pass the results to a method to build the HTML
      let camperCount = 0;
      data["campers"].forEach(function (camper) {
        camperCount++;
        createGrids(camper); // this function is in the form-builder.js file
      });

      // if there are multiple campers on the account, mark the flag to show the sibling friend option on the submit page
      if (camperCount > 1) {
        $("#showSiblingOptions").val("true");
      }
      showCamperSection();

      // Now that the grid is loaded, delete the cookie with old form data - this ensures that we don't always have a populated form when we don't want it
      deleteCookie("formInput");

      // if there are not ANY campers, then show the "add campers" button
      if (camperCount == 0) {
        showAddCamperButton();
      }
    })
    .fail(function () {
      console.log(" in displayCampers fail function");
      showAsLoggedOut();
      return false;
    });
}

function hideCampers() {
  $("#camper-section").hide();
  $("#camper_text").hide();

  $("#camper_choices").html("");
}

function showCamperSection() {
  $("#camper-section").show();
  showBox("loggedin-section");
  hideLoadingBox();
}

function showAddCamperButton() {
  $("#camper-section").html(
    '<div class="tw-bg-[#FFF8F0] tw-text-center tw-px-6 tw-py-4 tw-rounded-xl tw-mt-4"><div style="width:100%; margin-bottom:25px;"><p class="description instructions tw-text-emerald-[#FFF8F0] tw-px-6 tw-py-4 tw-rounded-xl tw-mt-4">No one listed on your account falls within the camper age range of 5 to 13 years old. Would you like to add someone to your account?</p><a class="tw-btn-primary" href="/camps/queue/addperson/">Add a Person</a></div>'
  );

  // for the mobile page
  $("input").hide();
}
