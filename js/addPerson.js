function submitAddPerson() {
  addPersonLoadingStart();

  if (!validateForm()) {
    addPersonLoadingStop();
    return false;
  }

  // if the form is good - we'll package up the data, send it to an ajax page
  // we then reset the form and show a success message when we hear back from the ajax page

  var camperFirstName = document.getElementById("camperFirstName").value.trim();
  var camperLastName = document.getElementById("camperLastName").value.trim();
  var camperDOB = document.getElementById("camperDOB").value.trim();

  var genderRdo = document.getElementsByName("camperGender");

  for (i = 0; i < genderRdo.length; i++) {
    if (genderRdo[i].checked) camperGender = genderRdo[i].value;
  }
  const formData = {
    firstName: camperFirstName,
    lastName: camperLastName,
    camperDob: camperDOB,
    gender: camperGender,
    account: getCookie("account"),
    key: getCookie("key"),
    action: "add_person",
  };

  // Disabling the form elements to reenforce the idea that we're thinking
  document.getElementById("camperFirstName").disabled = true;
  document.getElementById("camperLastName").disabled = true;
  document.getElementById("camperDOB").disabled = true;
  document.getElementById("camperFemale").disabled = true;
  document.getElementById("camperMale").disabled = true;

  console.log("::adminAjaxUrl", adminAjaxUrl);
  $.ajax({
    type: "POST", // define the type of HTTP verb we want to use (POST for our form)
    url: adminAjaxUrl, // the url where we want to POST
    data: formData, // our data object
    dataType: "json", // what type of data do we expect back from the server
  })
    .done(function (data) {
      if (data == null) {
        // this almost always means an API error
        $(".server_error").show();

        addPersonLoadingStop();
        return false;
      }

      if (data["Success"] == true) {
        successMessage();
        return true;
      }

      // are we authenticated? If not, redirect to the login screen
      if (data["Authenticated"] !== true) {
        setCookie("reAuth", "addPerson");
        document.location = "index.php";
        return false;
      }
    })
    .fail(function () {
      $(".server_error").show();

      addPersonLoadingStop();
      return false;
    });

  return true;
}

// upon succesfull creation of a new person, this resets the form
function successMessage() {
  $(".server_success").show();

  document.getElementById("camperFirstName").value = "";
  document.getElementById("camperLastName").value = "";
  document.getElementById("camperDOB").value = "";
  document.getElementById("camperFemale").checked = false;
  document.getElementById("camperMale").checked = false;

  addPersonLoadingStop();
}

/** Form validation  */
function validateForm() {
  var camperFirstName = document.getElementById("camperFirstName").value.trim();
  var camperLastName = document.getElementById("camperLastName").value.trim();
  var camperDOB = document.getElementById("camperDOB").value.trim();
  var camperFemale = document.getElementById("camperFemale").checked;
  var camperMale = document.getElementById("camperMale").checked;

  var formValidationMsg = document.getElementById("formValidationMsg");

  formValidationMsg.innerHTML = "&nbsp;";

  if (
    camperFirstName == "" ||
    camperLastName == "" ||
    camperDOB == "" ||
    (camperFemale == false && camperMale == false)
  ) {
    formValidationMsg.innerHTML = "All fields are required.";
    return false;
  }

  // DOB validation
  if (!dateValidator(camperDOB)) {
    formValidationMsg.innerHTML =
      "Please enter a valid date of birth for your camper (M/D/YYYY).";
    return false;
  }

  return true;
}

/* https://www.scaler.com/topics/date-validation-in-javascript/ */
function dateValidator(date) {
  let dateformat = /^(0?[1-9]|1[0-2])[\/](0?[1-9]|[1-2][0-9]|3[01])[\/]\d{4}$/;

  // Matching the date through regular expression
  if (date.match(dateformat)) {
    let operator = date.split("/");

    // Extract the string into month, date and year
    let datepart = [];
    if (operator.length > 1) {
      datepart = date.split("/");
    }
    let month = parseInt(datepart[0]);
    let day = parseInt(datepart[1]);
    let year = parseInt(datepart[2]);

    // Create a list of days of a month
    let ListofDays = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    if (month == 1 || month > 2) {
      if (day > ListofDays[month - 1]) {
        //to check if the date is out of range
        return false;
      }
    } else if (month == 2) {
      let leapYear = false;
      if ((!(year % 4) && year % 100) || !(year % 400)) leapYear = true;
      if (leapYear == false && day >= 29) return false;
      else if (leapYear == true && day > 29) {
        console.log("Invalid date format!");
        return false;
      }
    }
  } else {
    console.log("Invalid date format!");
    return false;
  }
  return true;
}

function addPersonLoadingStart() {
  $("#loginBtn").prop("disabled", true);
  $("#loginBtn").html("Loading");
  $(".server_success").hide();

  $(".server_error").hide();
}

function addPersonLoadingStop() {
  document.getElementById("camperFirstName").disabled = false;
  document.getElementById("camperLastName").disabled = false;
  document.getElementById("camperDOB").disabled = false;
  document.getElementById("camperFemale").disabled = false;
  document.getElementById("camperMale").disabled = false;

  $("#loginBtn").html("Add Camper");
  $("#loginBtn").prop("disabled", false);
}
