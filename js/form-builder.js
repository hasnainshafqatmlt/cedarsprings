// Adapted from the 2020 "paper" grid to be the interface for the queue
// January 2023 (BN)
var overnightSeparatorDisplayed = false;
var playPassSeparatorDisplayed = false;
var dayCampSeparatorDisplayed = false;

/**
 * Takes the camper information from camper-action.js and builds the grid for the user
 * This function runs in order to put the return of the aysnc ajax calls in order with each other.
 * Calling buildCamperData() directly means that the summer schedule hasn't come back when needed (even with async: false enabled)
 * @param {obj} camper
 */
function createGrids(camper) {
  const urlParams = new URLSearchParams(window.location.search);
  const testmode = urlParams.get("duckfeet");
  overnightSeparatorDisplayed = false;

  $.ajax({
    type: "POST",
    url:
      typeof custom_camp_ajax !== "undefined" ? custom_camp_ajax.ajax_url : "",
    data: {
      action: "get_summer_schedule",
      testmode:
        typeof testmode !== "undefined" && testmode === "true"
          ? "true"
          : "false",
      // Uncomment the next line if you want to pass futureOnly from somewhere
      // futureOnly: true
    },
    dataType: "json",
    async: false,
  })
    .done(function (data) {
      buildCamperData(camper, data);
    })
    .fail(function () {
      let weeks = [];
      for (var i = 1; i < 13; i++) {
        weeks[i - 1] = "Week " + i;
      }

      buildCamperData(camper, weeks);
    });
}

function buildCamperData(camper, schedule) {
  // Reset section display flags for the new camper
  playPassSeparatorDisplayed = false;
  dayCampSeparatorDisplayed = false;
  overnightSeparatorDisplayed = false;

  // get the table
  var table = document.getElementById("campGridTable");
  if (table === null) {
    return;
  }
  // --- Insert gap row only if there's already at least 1 camper ---
  if (table !== null && table.rows.length > 0) {
    var gapRow = table.insertRow(-1);
    var gapCell = gapRow.insertCell(0);

    // Make the cell span all of your columns
    gapCell.colSpan = schedule.length + 1;

    // Give it a class so you can style it
    gapCell.classList.add("gapRow");

    // Some people like to put a non-breaking space so it’s definitely not empty
    gapCell.innerHTML = "&nbsp;";
  }

  // create the row in the table
  var row = table.insertRow(-1);

  // add the class to allow styling this first row
  row.classList.add("defaultRow");

  // create the first cell for the title
  var cellTitle = row.insertCell(0);
  cellTitle.classList.add("campName"); // don't let the class name fool you, this is actually the camper's name - we're just using the styling

  // add the HTML to the title cell
  cellTitle.innerHTML =
    "<span class='noCampLabel camper-name'>" + camper["first"] + "</span>";

  schedule.forEach(function (week, i) {
    var blankRow = row.insertCell(i + 1); // plus one because cell zero is the camper's name
    // Split week into two lines if it contains a space
    var weekParts = week.split(/ (.+)/); // splits into [month, dates] if possible
    if (weekParts.length > 1) {
      blankRow.innerHTML =
        "<p class='WeekNumber'><span>" +
        weekParts[0] +
        "<br><span style='white-space:nowrap;'>" +
        weekParts[1] +
        "</span></span></p>";
    } else {
      blankRow.innerHTML =
        "<p class='WeekNumber'><span>" + week + "</span></p>";
    }
    blankRow.classList.add("tableCell"); // TD class
    blankRow.classList.add("toBeScheduled"); // TD class unique to the first row
  });

  // NEXT - We build a row of "select one" options
  row = table.insertRow(-1);
  row.classList.add("clear-selection-row");
  cellTitle = row.insertCell(0);
  cellTitle.classList.add("campName");
  cellTitle.innerHTML = "<span>Clear Selection</span>";
  schedule.forEach(function (week, i) {
    var blankRow = row.insertCell(i + 1); // plus one because cell zero is the camper's name
    targetweek = parseInt(i) + 1;
    id = "R-" + camper["id"] + "-" + targetweek;
    html =
      '<label class="sr-only" for="' +
      id +
      '">' +
      week +
      ": Still to be scheduled</label>";
    html =
      '<input type="radio" class="rdoCampChoice" name="' +
      id +
      '" id="' +
      id +
      '" value="none" onclick="handleClearSelection(this, ' +
      camper["id"] +
      ", " +
      targetweek +
      "); checkCapacity(); restoreQueueOption(this); $(this).prop('checked', false);\" />";

    blankRow.innerHTML = html;
    blankRow.classList.add("tableCell"); // TD class
    blankRow.classList.add("toBeScheduled"); // TD class unique to the first row
  });

  // AFTER INITIAL / DEFAULT ROW

  Object.keys(camper["campStatus"]).forEach((campName) => {
    createRow(campName, camper["campStatus"][campName], camper);
  });

  // Display the grid functionality once the grid has loaded
  $("#buttons").show();
  $("#legend").show();
  $("#bottomSubmit").show();

  return true;
}

function createRow(campName, camp, camper) {
  var table = document.getElementById("campGridTable");
  var weeks = camp["weeks"];
  var campId = camp["id"];
  var numberOfWeeks = Object.keys(weeks).length;
  var campIcon = "";
  const isPlayPass = campName === "Play Pass";

  // Create Play Pass section header if this is the Play Pass row and header not yet displayed
  if (isPlayPass && !playPassSeparatorDisplayed) {
    var playPassDividerRow = table.insertRow(-1);
    playPassDividerRow.classList.add("camp-divider-row");
    playPassDividerRow.classList.add("play-pass-header");
    var playPassDividerCell = playPassDividerRow.insertCell(0);
    playPassDividerCell.colSpan = numberOfWeeks + 1;
    playPassDividerCell.innerHTML =
      '<span class="camp-section-label">Play Pass<br />Register one day at a time</span>';
    playPassSeparatorDisplayed = true;
  }

  // Create Day Camp section header after Play Pass but before any regular day camps
  // Only if we've already shown Play Pass (or processed it) and not yet shown the day camp header
  if (
    !isPlayPass &&
    playPassSeparatorDisplayed &&
    !dayCampSeparatorDisplayed &&
    campId !== "9999" &&
    campId !== "73523"
  ) {
    var dayCampDividerRow = table.insertRow(-1);
    dayCampDividerRow.classList.add("camp-divider-row");
    var dayCampDividerCell = dayCampDividerRow.insertCell(0);
    dayCampDividerCell.colSpan = numberOfWeeks + 1;
    dayCampDividerCell.innerHTML =
      '<span class="camp-section-label" style="margin:10px; display:block;">Full Week Day Camp Options</span>';
    dayCampSeparatorDisplayed = true;
  }

  // Create the divider row before overnight camps
  if (
    (campId === "9999" || campId === "73523") &&
    !overnightSeparatorDisplayed
  ) {
    var dividerRow = table.insertRow(-1);
    dividerRow.classList.add("camp-divider-row");
    var dividerCell = dividerRow.insertCell(0);
    dividerCell.colSpan = numberOfWeeks + 1;
    dividerCell.innerHTML =
      '<span class="camp-section-label" style="margin:10px; display:block;">Overnight Programs</span>';
    overnightSeparatorDisplayed = true;
  }

  var row = table.insertRow(-1);
  var cellTitle = row.insertCell(0);
  var highlightedName = "";

  // Highlight Explorers
  if (campName == "Explorers - Cubs" || campName == "Explorers - Grizzlies") {
    row.classList.add("highlightedRow");
    highlightedName = "highlightedcampname";
    campIcon = '<span class="camp-type-icon discount-icon"></span>';
  }

  // Style Overnight
  if (campId === "9999" || campId === "73523") {
    row.classList.add("overnight-camp-row");
    campIcon = '<span class="camp-type-icon overnight-icon"></span>';
  }

  // Title column HTML
  /*	cellTitle.innerHTML = isPlayPass
		? '<a href="#" data-toggle="modal" data-target="#myModal" data-camp="' + campId + '" class="' + highlightedName + '">' + campIcon + campName + '</a><br /><a href="playpass.php" class="play-pass-registration-link">Daily Registration</a>'
		: '<a href="#" data-toggle="modal" data-target="#myModal" data-camp="' + campId + '" class="' + highlightedName + '">' + campIcon + campName + '</a>';
*/
  cellTitle.innerHTML = isPlayPass
    ? '<a href="#" data-toggle="modal" data-target="#myModal" data-camp="' +
      campId +
      '" class="' +
      highlightedName +
      '">' +
      campIcon +
      campName +
      '<br /><span style="font-size:small">$69 per day</span></a>'
    : '<a href="#" data-toggle="modal" data-target="#myModal" data-camp="' +
      campId +
      '" class="' +
      highlightedName +
      '">' +
      campIcon +
      campName +
      "</a>";
  cellTitle.classList.add("campName");

  for (var i = 1; i < numberOfWeeks + 1; i++) {
    let newCell = row.insertCell(i);
    newCell.classList.add("tableCell");

    if (isPlayPass) {
      switch (weeks[i]) {
        case "unavailable":
          newCell.classList.add("unavailableCamp");
          break;
        case "registered":
          newCell.classList.add("registeredCamp");
          newCell.innerHTML = '<p class="fullText">Registered</p>';
          break;
        case "registered elsewhere":
          newCell.classList.add("registeredElsewhereCamp");
          break;
        case "full":
          newCell.classList.add("fullCamp");
          newCell.innerHTML = '<p class="fullText">Full</p>';
          break;
        case "queued":
          newCell.classList.add("queuedCamp");
          newCell.innerHTML = '<p class="fullText">In Queue</p>';
          break;
        case "available":
        default:
          newCell.classList.add("availableCamp");
          newCell.classList.add("play-pass-row");
          newCell.innerHTML =
            '<a class="playpass-button" href="playpass.php">Daily<br />Registration</a>';
          break;
      }

      newCell.id = "tdWk_" + camper["id"] + "-" + campId + "-" + i;
      continue;
    }

    // Regular rendering below (unchanged for non-Play Pass)
    const hasAccelerateThisWeek = document.querySelector(
      `input[name="R-${camper["id"]}-9999-${i}"][checked]`
    );
    const isCampfireNights = campId === "73523";
    const isAccelerate = campId === "9999";

    if (hasAccelerateThisWeek && isCampfireNights) {
      newCell.classList.add("ineligibleCamp");
      newCell.innerHTML = '<p class="fullText">Unavailable with Accelerate</p>';
      continue;
    }

    switch (weeks[i]) {
      case "unavailable":
        newCell.classList.add("unavailableCamp");
        break;
      case "ineligible":
        newCell.classList.add("ineligibleCamp");
        newCell.innerHTML = '<p class="fullText">Ineligible</p>';
        break;
      case "registered":
        newCell.classList.add("registeredCamp");
        newCell.innerHTML = '<p class="fullText">Registered</p>';
        break;
      case "registered elsewhere":
        newCell.classList.add("registeredElsewhereCamp");
        break;
      case "available":
        if (campName == "Campfire Nights") {
          newCell.innerHTML = createCheckBox(
            campName,
            i,
            campId,
            camper["id"],
            camper["first"],
            "R"
          );
          break;
        }
        newCell.innerHTML = createRdoButton(
          campName,
          i,
          campId,
          camper["id"],
          camper["first"]
        );
        break;
      case "full":
        newCell.classList.add("fullCamp");
        newCell.innerHTML = createCheckBox(
          campName,
          i,
          campId,
          camper["id"],
          camper["first"]
        );
        break;
      case "total":
        if (campName == "Campfire Nights") {
          newCell.innerHTML = createCheckBox(
            campName,
            i,
            campId,
            camper["id"],
            camper["first"],
            "R"
          );
          break;
        }
        newCell.innerHTML = createRdoButton(
          campName,
          i,
          campId,
          camper["id"],
          camper["first"],
          true
        );
        break;
      case "active":
        newCell.classList.add("activeCamp");
        if (campName == "Campfire Nights") {
          newCell.innerHTML = createCheckBox(
            campName,
            i,
            campId,
            camper["id"],
            camper["first"],
            "A"
          );
          break;
        }
        newCell.innerHTML = createRdoButton(
          campName,
          i,
          campId,
          camper["id"],
          camper["first"],
          true
        );
        break;
      case "queued":
        newCell.classList.add("queuedCamp");
        newCell.innerHTML = '<p class="fullText">In Queue</p>';
        break;
      case "add-on":
        newCell.classList.add("addonCamp");
        if (campName == "Campfire Nights") {
          newCell.innerHTML = createCheckBox(
            campName,
            i,
            campId,
            camper["id"],
            camper["first"],
            "A"
          );
          break;
        }
        newCell.innerHTML = createRdoButton(
          campName,
          i,
          campId,
          camper["id"],
          camper["first"],
          true
        );
        break;
    }

    if (isAccelerate && newCell.querySelector('input[type="radio"]')) {
      const radio = newCell.querySelector('input[type="radio"]');
      radio.addEventListener("change", function () {
        const weekNum = this.id.split("-")[3];
        const campfireCheckboxes = document.querySelectorAll(
          `input[type="checkbox"][id*="-73523-${weekNum}"]`
        );
        campfireCheckboxes.forEach((checkbox) => {
          checkbox.checked = false;
          checkbox.disabled = this.checked;
          checkbox.parentElement.classList.toggle(
            "unavailableCamp",
            this.checked
          );
        });
      });
    }

    newCell.id = "tdWk_" + camper["id"] + "-" + campId + "-" + i;
  }
}

function createRdoButton(
  camp,
  week,
  campId,
  camperId,
  camperName,
  active = null
) {
  // action-camper-camp-session
  let action = "";
  if (active) {
    action = "A-";
  } else {
    action = "R-";
  }

  let id = action + camperId + "-" + campId + "-" + week;
  let value = action + camperId + "-" + campId + "-" + week;
  let name = "R-" + camperId + "-" + week;
  let label = camperName + ": Week " + week + ", " + camp;
  let cssClass = "rdoCampChoice";

  // need to allow for JS to find all of a camp when the capacity is reduced - so we're using a string like this
  let campIdentifier = "wk" + week + "cmp" + campId;
  cssClass += " " + campIdentifier;

  // determine if the radio button should be checked
  let checked = checkIfChecked(id) ? " checked " : "";

  let html = '<label class="sr-only" for="' + id + '">' + label + "</label>";
  html +=
    '<input type="radio" class="' +
    cssClass +
    '" name="' +
    name +
    '" id="' +
    id +
    '" value="' +
    value +
    '"' +
    checked;
  html += ' onchange="checkCapacity(); handleRadioChange(this)"';
  html += " />";
  return html;
}

function createCheckBox(camp, week, campId, camperId, camperName, campAction) {
  // Campfire nights, create check boxes with the R action - need to allow for that
  // However, I cannot just assume an incoming value because i use the campAction = A for something else

  action = "Q-";
  if (campAction) {
    action = campAction + "-";
  }

  // action-camper-camp-session
  let id = action + camperId + "-" + campId + "-" + week;
  let value = action + camperId + "-" + campId + "-" + week;
  let name = action + camperId + "-" + campId + "-" + week;
  let label = camperName + ": Week " + week + ", " + camp;
  let cssClass = "chkCampChoice";

  // camp action can come in when capacity management is converting a radio to a check box - if the radio is a 'total' category, we need to store that knowledge
  // camp action just tacks it onto the class list as a place to store the knowledge
  // Add specific class for campfire add-ons
  if (campId === "73523") {
    if (campAction === "R" || campAction === "A") {
      cssClass += " campfire-night-checkbox";

      if (campAction === "A") {
        cssClass += " campfire-addon";
      }
    }
  }

  // Keep total class for other cases
  if (campAction == "A" && campId !== "73523") {
    cssClass += " total";
  }

  // need to allow for JS to find all of a camp when the capacity is reduced - so we're using a string like this
  cssClass += " wk" + week + "cmp" + campId;

  // determine if the radio button should be checked
  let checked = checkIfChecked(id) ? " checked " : "";

  let html = '<label class="sr-only" for="' + id + '">' + label + "</label>";
  html +=
    '<input type="checkbox" class="' +
    cssClass +
    '" name="' +
    name +
    '" id="' +
    id +
    '" value="' +
    value +
    '"' +
    checked;

  // If this is a campfire night checkbox, add capacity check
  if (campId === "73523") {
    html += ' onchange="checkCapacity()"';
  }

  html += " />";

  return html;
}

function checkIfChecked(id) {
  // get the cookie info
  let data = getCookie("formInput");

  if (data == "") {
    return false;
  }

  let string = decodeURI(data);
  let values = string.split("_");

  // if the response isn't an array, return false
  if (!Array.isArray(values)) {
    return false;
  }

  // because the state of a camp could change, such as from R to Q, we aren't going to care about the action element of the string
  // if the camper/camp/week combo should be checked, regardless of the action previously chosen, then check this box
  // the result is that someone signing up for a waitlist could find themselves making a reservation if they relaod and things have changed (or the otherway around)
  var subID = id.substring(2);
  var foundIt = false;
  values.forEach(function (haystack) {
    let straw = haystack.substring(2);
    if (subID == straw) {
      foundIt = true;
    }
  });

  // returns true/false if the requested ID exists in the array
  return foundIt;
}

// sets the submit buttons to "loading"
function setLoading() {
  $(".btn-submit").each(function () {
    $(this).html("Loading...");
    $(this).prop("disabled", false);
    $(this).css("background-color", "");
  });
}

function handleClearSelection(radio, camperId, weekNum) {
  // Re-enable Campfire Night checkboxes
  const campfireCheckboxes = document.querySelectorAll(
    `input[type="checkbox"][id*="-73523-${weekNum}"]`
  );
  campfireCheckboxes.forEach((checkbox) => {
    checkbox.disabled = false;
    checkbox.parentElement.classList.remove("unavailableCamp");
    checkbox.checked = false;
  });
}

function handleRadioChange(radio) {
  const [_, camperId, campId, weekNum] = radio.id.split("-");

  // If this is not an Accelerate selection, re-enable Campfire Night checkboxes
  if (campId !== "9999" && radio.checked) {
    const campfireCheckboxes = document.querySelectorAll(
      `input[type="checkbox"][id*="-73523-${weekNum}"]`
    );
    campfireCheckboxes.forEach((checkbox) => {
      checkbox.disabled = false;
      checkbox.parentElement.classList.remove("unavailableCamp");
    });
  }
}
