// trigger the modal dialog when a camp name is clicked
// this populates the contents of the dialog, and then displays it
$("#myModal").on("show.bs.modal", function (event) {
  let button = $(event.relatedTarget); // Button that triggered the modal

  const msg =
    typeof button.data("msg") !== "undefined" ? button.data("msg") : null;
  const action =
    typeof button.data("action") !== "undefined" ? button.data("action") : null;

  var modal = $(this);

  // determine which message to display
  switch (action) {
    case "snooze":
      modal.find(".modal-title").text("Snooze Queue Entry");
      modal
        .find(".modal-body")
        .html(
          "If you are not yet ready to register for a camp but you are next in queue, you may pause your queue for 1 week. Snoozing allows you to step aside and allow the next person in queue to take an available place, and in one week, your place in front of the queue will be restored.<br /><br />Would you like to snooze the queue for " +
            msg["camper"] +
            " in " +
            msg["camp"] +
            " for the week of " +
            msg["week"] +
            "?<br /><br />" +
            '<div class="" ><button class="tw-btn-primary" onclick="takeAction(\'' +
            msg["id"] +
            "', 'snooze')\">Yes</button> " +
            '<button class="tw-btn-neutral" data-dismiss="modal" >No</button></div>'
        );
      break;

    case "cancel":
      modal.find(".modal-title").text("Remove Queue Entry");
      modal
        .find(".modal-body")
        .html(
          "Removing a queue entry takes your camper out of line for an available space in the selected camp. You can return to the queue, but will return with a new place in queue behind those currently awaiting a space.<br /><br />Would you like to remove the queue for " +
            msg["camper"] +
            " in " +
            msg["camp"] +
            " for the week of " +
            msg["week"] +
            "?<br /><br />" +
            '<div class="" ><button class="tw-btn-primary"onclick="takeAction(\'' +
            msg["id"] +
            "', 'cancel')\">Yes</button> " +
            '<button class="tw-btn-neutral" data-dismiss="modal" >No</button></div>'
        );

      break;

    case "reactivate":
      modal.find(".modal-title").text("Reactivate Expired Queue");
      modal
        .find(".modal-body")
        .html(
          "When a space becomes available, there is a limited amount of time to register for that spot. When a registration is not made in time, your place in the queue expires. You can re-activate your queue entry to return to the queue near the top of the list and be notified of the next  space available for your camper.<br /><br />Would you like to reactivate the queue for " +
            msg["camper"] +
            " in " +
            msg["camp"] +
            " for the week of " +
            msg["week"] +
            "?<br /><br />" +
            '<div class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" ><button class="btn" onclick="takeAction(\'' +
            msg["id"] +
            "', 'reactivate')\">Yes</button> " +
            '<button class="btn" data-dismiss="modal" >No</button></div>'
        );

      break;
  }
});

function returnToHomepage() {
  document.location = "index.php";
  return true;
}
