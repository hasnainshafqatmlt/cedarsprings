/* Collects the form inputs from the login form */

// run a cookie check on each page load and clean the login form if they're authorized
chooseLoginMode();

function chooseLoginMode() {
  // check to see if we have a cookie called name - we have three, so just picking the one that is user facing
  showLoadingBox();

  // check to see if the cookie exists. No cookie, we're not logged in

  if (!validateCookie("name")) {
    // if there isn't a cookie, lets check and see if we are able to save a cookie in the browser
    if (!testCookieSupport()) {
      showCookiesRequiredMessage();
      hideLoadingBox();
      return false;
    }

    // if we're able to process cookies, then the user just isn't logged in
    hideLoadingBox();
    console.log("chooseLoginMode >>");
    customLogout();
    return true;
  }

  // see if there is an incoming reAuth Cookie
  if (checkCookie("reAuth")) {
    userReauthorize();
    return true;
  }

  // if there is a cookie, assume we're logged in
  showAsLoggedIn();
  return true;
}

// take the cookie info and validate that it is still valid
// if it isn't, reset the login form
function validateCookie() {
  // get the needed info from the cookies
  const key = getCookie("key");
  const account = getCookie("account");
  const name = getCookie("name");

  // if we don't have the cookie elements, delete any that we do and then cancel the function
  if (key.length == 0 || account.length == 0 || name.length == 0) {
    console.log("validateCookie >>");
    showAsLoggedOut();
    return false;
  }

  return true;
}

function submitLoginForm() {
  // need some type of loading indicator
  loginLoadingStart();
  $(".invalid_credentials").hide();
  $(".server_error").hide();

  // clear the reAuth cookie (if it exists)
  deleteCookie("reAuth");

  const formData = {
    action: "custom_login",
    nonce: custom_login_ajax.nonce,
    user: $("#userEmail").val(),
    pass: $("#userPassword").val(),
  };

  $.ajax({
    type: "POST", // define the type of HTTP verb we want to use (POST for our form)
    url: custom_login_ajax.ajax_url, // the url where we want to POST
    data: formData, // our data object
    dataType: "json", // what type of data do we expect back from the server
  })
    .done(function (data) {
      if (data == null) {
        // this almost always means an API error
        $(".server_error").show();

        loginLoadingStop();
        return false;
      }
      // are we authenticated? If not, throw a message and stop there
      console.log("data >>", data);
      if (data["Authenticated"] !== true) {
        $(".invalid_credentials").show();
        loginLoadingStop();
        return false;
      }
      setCookie("key", data["q"]);
      setCookie("account", data["AccountId"]);
      setCookie("name", data["contact"]["FirstName"]);
      setCookie("uc-token", data["sso-token"]);
      showAsLoggedIn();
      loginLoadingStop();
      location.href = "/camps-summer-queue-registration/?duckfeet=true";

      return false;
    })

    .fail(function () {
      $(".invalid_credentials").show();
      loginLoadingStop();
      return false;
    });
}

function loginLoadingStart() {
  $("#loginBtn").prop("disabled", true);
  $("#loginBtn").html("Loading");
}

function loginLoadingStop() {
  $("#loginBtn").html("Log In");
  $("#loginBtn").prop("disabled", false);
}

function showLoadingBox() {
  $("#loading-section").show();
}

function hideLoadingBox() {
  $("#loading-section").hide();
}

function showAsLoggedIn() {
  $("#login-section").hide();
  $("#userPassword").val(""); // clear the password field as we hide it

  if (checkCookie("uc-token")) {
    $("#ucLogin-button").attr(
      "href",
      "https://www.ultracamp.com/sso/login.aspx?idCamp=107&tkn=" +
        getCookie("uc-token")
    );
    $("#ucLogin-button").html("Access<br />Ultracamp");
  }

  $("#contactName").html(getCookie("name"));
  $("#loggedin-section").show();

  displayCampers();
}

function showAsLoggedOut() {
  $("#userPassword").val(""); // ensure that the password field is clear before we show it

  // we re-arrange the text and size on this with user re-auth. Put it back when not in re-auth
  if (!checkCookie("reAuth")) {
    $("#loginInstructions").html("Log in to Your Account.");
    $("#loginInstructions").css("font-size", "24px");
  }

  hideCampers();
  $("#login-section").show();

  $("#loggedin-section").hide();
  $("#contact-name").html("");
  $("#campGrid").html("");
  $("#showSiblingOptions").val("false");
  $("#ucLogin-button").attr(
    "href",
    "https://www.ultracamp.com/clientlogin.aspx?idCamp=107&campCode=CP7"
  );
  $("#ucLogin-button").html("Login to <br />Ultracamp");

  $("#successMsg").remove();
  console.log(" showAsLoggedOut function that removing cookie::  ");
  deleteCookie("key");
  deleteCookie("account");
  deleteCookie("name");
  deleteCookie("uc-token");
  hideLoadingBox();
  return true;
}

function showCookiesRequiredMessage() {
  hideCampers();
  $("#login-section").show();

  $("#loggedin-section").hide();
  $("#contact-name").html("");

  // display a message indication that we need cookies
  $("#loginBox").html(
    '<span class="pricing">Cookies are Required</span><br /><span style="color:white">Please enable cookie support in your web browser in order to continue. This page uses cookies to store your choices as you build your family summer schedule.</span>'
  );
}

function userReauthorize() {
  $("#loginInstructions").html("Please re-enter your password to continue.");
  $("#loginInstructions").css("font-size", "24px");
  console.log("userReauthorize >>");
  showAsLoggedOut();
}

function showBox(location) {
  // This function can be used to show different sections
  if (location == "login") {
    console.log("showBox >>");
    showAsLoggedOut();
  }
}

function customLogout(cond) {
  $.ajax({
    type: "POST",
    url: custom_login_ajax.ajax_url,
    data: { action: "custom_logout" },
    dataType: "json",
    success: function (data) {
      // Clear cookies and show login form
      console.log("logout");
      deleteCookie("key");
      deleteCookie("account");
      deleteCookie("name");
      deleteCookie("uc-token");
      showAsLoggedOut();
      console.log(" in customLogout and redirect", location.pathname);
      if (location.pathname !== "/camps/queue/")
        location.href = "/camps/queue/";
    },
  });
  return false;
}

// function deleteCookie(name) {
//   document.cookie = name + "=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;";
// }
