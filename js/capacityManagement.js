// ensures that we don't allow more campers than spots to sign up for a camp by turning registration fields into camper queue fields when required

// the index page provides the capacities variable

function checkCapacity() {
  // beacuse I cannot easily tell what was unselected - each time there is a change, we'll check all of the radio buttons which are checked
  // if the capacity is equal to or less than the count, then convert registered to queues
  // then, check all converted queues, and ensure that the capacity for their numbers is not greater than that checked - if so, put them back to registered

  // the count for each of the selected camps
  const campCounts = new Array(); // const on array locks in the variable type as array, but doesn't make the array values constants.

  // look through all the radio buttons
  $(".rdoCampChoice").each(function () {
    // get the week/camp combo needed for looking up the capacity
    let z = this.value.split("-");
    let campIdentifier = "wk" + z[3] + "cmp" + z[2];

    // for any which is checked, count each week/camp combo
    if (this.checked == true) {
      // store the count so that we can see how many of these are currently chosen
      if (campIdentifier in campCounts) {
        campCounts[campIdentifier]++;
      } else {
        campCounts[campIdentifier] = 1;
      }
    }
  });

  // Add campfire nights checkbox counting
  $(".chkCampChoice").each(function () {
    if (this.checked && this.id.split("-")[2] === "73523") {
      // Check if it's campfire nights
      let z = this.value.split("-");
      let campIdentifier = "wk" + z[3] + "cmp" + z[2];

      if (campIdentifier in campCounts) {
        campCounts[campIdentifier]++;
      } else {
        campCounts[campIdentifier] = 1;
      }
    }
  });

  // we now have a variable full of capacities and a variable full of chosen camps
  // we need to find out if any of the chosen camps have exhausted the capacities
  for (var key in campCounts) {
    let value = campCounts[key];

    if (capacities[key] - value < 1) {
      // Adjust all matching and unselected week/camps to be queue options as we've run out of space
      radioToCheckboxes(key);
    }
  }

  // now, we need to do the inverse and find if there are any camper queue check boxes we converted that need to return to be registration radio buttons
  // we do this by looking at all of the converted types, and ensuring that the capacities minus radio buttons still equal zero. If they don't, convert back
  // using the document selector because the dynamically added class names for convertedElements causes some order of operation issues with a JQuery selector
  $(document.getElementsByClassName("convertedElement")).each(function () {
    let a = $(this).attr("id").substring(5); // removes the tdwk_ from the front of the entry string
    entry = a.split("-");

    let campIdentifier = "wk" + entry[2] + "cmp" + entry[1];
    // if we find any where the radio button count is lower than the listed capacity, return these converted types back into radio buttons
    if (
      campCounts[campIdentifier] === undefined ||
      capacities[campIdentifier] - campCounts["campIdentifier"] > 0
    ) {
      checkboxToRadio($(this).attr("id"));
    }
  });
}

// find all radio buttons that match the ID and which are not checked
// change them to corosponding queue checkboxes, but also note if they need to have an active flag queued for when they return to radio buttons
function radioToCheckboxes(identifier, campAction) {
  // the identifier is stored in the class list - so we're getting every rdoCampChoice that matches the identifier
  $(".rdoCampChoice." + identifier).each(function () {
    // only worry about those not checked as those which are checked are what was needed to get us into this function
    if (this.checked == false) {
      // get the containing table cell - we'll just modify it's HTML
      let cell = $(this).parent();

      // need to collect a bunch of information about the current radio button in order to create the correct check box.
      let entry = $(this).attr("id").split("-");
      let campAction = entry[0];
      let camperId = entry[1];
      let campId = entry[2];
      let week = entry[3];

      //<label class="sr-only" for="R-3719505-107190-1">Max: Week 1, Battlefield Live</label>
      let labelText = $(cell).children("label").html();
      let a = labelText.split(":");
      let camperName = a[0];

      let b = a[1].split(", ");
      let camp = b[1];

      // change everything about this cell to become a camper queue element
      cell.addClass("fullCamp");
      cell.addClass("convertedElement");

      if (campAction == "A") {
        cell.addClass("total");
      }

      cell.html(""); // clear the existing radio button
      cell.html(
        createCheckBox(camp, week, campId, camperId, camperName, campAction)
      ); // add a new checkbox - this function comes from form-builder.js
    }
  });

  // Now handle campfire night checkboxes
  $(".chkCampChoice." + identifier).each(function () {
    // Check if this is a campfire night checkbox
    if (this.id.split("-")[2] === "73523" && !this.checked) {
      let cell = $(this).parent();
      let entry = $(this).attr("id").split("-");

      cell.addClass("fullCamp");
      cell.addClass("convertedElement");

      // Replace checkbox with queue checkbox
      cell.html(
        createCheckBox(
          "Campfire Nights",
          entry[3], // week
          entry[2], // campId
          entry[1], // camperId
          "", // camperName
          "Q" // queue action
        )
      );
    }
  });

  return true;
}

// convert a specific checkbox back into a radio button
function checkboxToRadio(cellId) {
  let cell = $("#" + cellId);
  let entry = cellId.substring(5).split("-");

  // Check if this is a campfire nights cell
  if (entry[1] === "73523") {
    // Campfire nights ID
    cell.removeClass("fullCamp");
    cell.removeClass("convertedElement");

    // Check if parent TD has addonCamp class to determine action
    let action = cell.hasClass("addonCamp") ? "A" : "R";

    // Create standard campfire nights checkbox
    cell.html(
      createCheckBox(
        "Campfire Nights",
        entry[2], // week
        entry[1], // campId
        entry[0], // camperId
        "", // camperName
        action // A or R based on addonCamp class
      )
    );

    return true;
  }

  // need to collect a bunch of information about the current radio button in order to create the correct check box.
  let camperId = entry[0];
  let campId = entry[1];
  let week = entry[2];
  let total = false;

  // check to see if this needs to be an A element, rather than R
  if (cell.hasClass("total")) {
    total = true;
  }

  //<label class="sr-only" for="R-3719505-107190-1">Max: Week 1, Battlefield Live</label>
  let labelText = cell.children("label").html();
  let a = labelText.split(":");
  let camperName = a[0];

  let b = a[1].split(", ");
  let camp = b[1];

  // change everything about this cell to become a camper queue element
  cell.removeClass("fullCamp");
  cell.removeClass("convertedElement");

  cell.html(""); // clear the existing checkbox button
  cell.html(createRdoButton(camp, week, campId, camperId, camperName, total)); // add a new checkbox - this function comes from form-builder.js

  return true;
}

/**
 * Takes the array of the camp identifier and disables any checkboxes that are in the same camp/week combo
 * @param {array} z
 */
function removeQueueOption(z) {
  // there isn't a good way to find the check boxes in the row - so we're just going to look for them with brute force
  $(".chkCampChoice").each(function () {
    let chkId = this.id.split("-");
    if (chkId[0] == "Q" && chkId[1] == z[1] && chkId[3] == z[3]) {
      // this check box matches and is in the row
      $(this).prop("checked", false);
      $(this).hide();
      $(this).parent().removeClass("fullCamp");
    }
  });
}

/**
 * Takes object of the clear selection radio button and re-enables any hidden checkboxes that are in the same camp/week combo
 * @param {obj} rdoBtn
 */
function restoreQueueOption(rdoBtn) {
  z = rdoBtn.id.split("-");
  //z = identifier.split('-');

  // there isn't a good way to find the check boxes in the row - so we're just going to look for them with brute force
  $(".chkCampChoice").each(function () {
    let chkId = this.id.split("-");
    if (
      chkId[0] == "Q" &&
      chkId[1] == z[1] &&
      chkId[3] == z[2] &&
      $(this).is(":hidden")
    ) {
      // this check box matches and is in the row
      $(this).show();
      $(this).parent().addClass("fullCamp");
    }
  });
}
