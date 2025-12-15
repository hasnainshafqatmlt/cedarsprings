// trigger the modal dialog when a camp name is clicked
// this populates the contents of the dialog, and then displays it
$("#myModal").on("show.bs.modal", function (event) {
  let button = $(event.relatedTarget); // Button that triggered the modal

  const msg =
    typeof button.data("msg") !== "undefined"
      ? button.data("msg").trim()
      : null;

  var modal = $(this);

  //set a flag to know if this is camp behavior or other messages
  let displayCamp = typeof button.data("camp") !== "undefined" ? true : false;

  // if this is a camp coming in, then populate the dialog with that info
  // if(displayCamp) {

  //     const formData = {
  //         'campId'  : button.data('camp')
  //         };

  //    // run an ajax lookup for the camp info - then display the dialog box
  //    $.ajax({
  // 	type    : 'POST',
  // 	url     : 'ajax/getCampInfo.php',
  //     data    : formData, // our data object
  //     dataType: 'json',
  //     })
  //     .done(function(data) {

  //         if(data.maxAge) {
  //             var age = data.minAge + " - " + data.maxAge;
  //         } else {
  //             var age = data.minAge + " +";
  //         }

  //         modal.find('.modal-title').text(data.name + ": Ages " +age);
  //         modal.find('.modal-body').html(data.description);

  //         return true;
  //      })
  //     .fail(function(data) {
  //         return false;
  //     });
  // }

  // look for the indicated message and then provide that to the user
  if (msg == "newAccount") {
    modal.find(".modal-title").text("New Account Creation");
    modal
      .find(".modal-body")
      .html(
        "In order to continue, you'll need an account with our registration system. Click Create Account below to be walked through the account registration process. When you have completed the process, return to this page to log in with your newly created credentials.<br /><br />" +
          '<div class="modalButton" data-dismiss="modal" onclick="ucNewAccount()"><button class="btn ">Create Account</button></div>'
      );
    return true;
  }

  if (msg == "resetAccount") {
    modal.find(".modal-title").text("Forgot Account Credentials");
    modal
      .find(".modal-body")
      .html(
        'Clicking the Forgot Credentials button will take you to the login page of the registration system. Click the "Forgot your login information?" link on that page to reset your credentials. When you have completed the process, return to this page to log in with your newly created credentials to proceed.<br /><br />' +
          '<div class="modalButton" data-dismiss="modal" onclick="ucResetAccount()"><button class="tw-btn-primary ">Forgot Credentials</button></div>'
      );
    return true;
  }

  if (msg == "resetAccount-redirect") {
    modal.find(".modal-title").text("Forgot Account Credentials");
    modal
      .find(".modal-body")
      .html(
        "Click below to return to the login screen where you can click the 'forgot credentials' link on the login form<br /><br />" +
          '<div class="modalButton" data-dismiss="modal" onclick="returnToHomepage()"><button class="btn ">Return to Login</button></div>'
      );
    return true;
  }

  if (msg == "cellphone") {
    modal.find(".modal-title").text("Why do we need your cell phone number?");
    modal
      .find(".modal-body")
      .html(
        "We use your cell phone number to call and text you important updates about your camper's day while they are with us at camp. News and announcements such as weather impacts, bus delays, or questions about their reservation are directed to this phone number. We do not use your cell phone number for anything other than communication with you regarding existing reservations without your express permission."
      );
    return true;
  }

  if (msg == "passwordReqs") {
    modal.find(".modal-title").text("Password Requirements");
    modal
      .find(".modal-body")
      .html(
        "We need basic password requirements, including: <ul><li>7 or more characters <li>At least 1 character that is a letter<li>At least 1 character that is not a letter</ul>"
      );
    return true;
  }
});

function ucNewAccount() {
  window.open(
    "https://www.ultracamp.com/createNewAccount.aspx?idCamp=107&campCode=CP7",
    "_BLANK"
  );
  return true;
}

function ucResetAccount() {
  window.open(
    "https://www.ultracamp.com/clientlogin.aspx?idCamp=107&campCode=CP7&retrieveLogin=true",
    "_BLANK"
  );
  return true;
}

function returnToHomepage() {
  document.location = "index.php";
  return true;
}
