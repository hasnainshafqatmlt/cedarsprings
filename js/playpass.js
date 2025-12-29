// playpass.js - Enhanced JavaScript functionality for Play Pass registration

document.addEventListener("DOMContentLoaded", function () {
  // Variables to store form state
  let selectedCamper = null;
  let selectedWeek = null;
  let selectedDays = [];
  let isEditMode = false;
  let editIndex = -1;

  // Store existing selections (will be populated via AJAX)
  let existingSelections = [];

  // Helper function for secure AJAX requests with session-based auth
  // Tries session first (most secure), falls back to sending credentials if needed
  function makeSecureAjaxRequest(action, params, onSuccess, onError) {
    // First attempt: Use session-based auth (no credentials in POST body)
    const bodyParams = { action, ...params };

    function attemptRequest(includeAuth = false) {
      if (includeAuth) {
        const authKey = typeof getCookie === "function" ? getCookie("key") : "";
        const authAccount =
          typeof getCookie === "function" ? getCookie("account") : "";
        if (authKey && authAccount) {
          bodyParams.key = authKey;
          bodyParams.account = authAccount;
        }
      }

      return fetch(adminAjaxUrl, {
        method: "POST",
        credentials: "same-origin", // Important: sends session cookie
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams(bodyParams),
      })
        .then((response) => response.json())
        .then((data) => {
          // If auth failed, retry with credentials
          if (
            !data.success &&
            (data.message === "Authentication required" ||
              data.error === "Authentication required")
          ) {
            if (!includeAuth) {
              return attemptRequest(true);
            }
          }
          return data;
        });
    }

    return attemptRequest(false)
      .then(onSuccess)
      .catch(
        onError || ((error) => console.error(`Error in ${action}:`, error))
      );
  }

  const transportationSection = document.getElementById(
    "transportation-section"
  );

  // Check if a camper is already selected (when there's only one)
  const preSelectedCamper = document.querySelector(
    'input[name="selected_camper"]:checked'
  );
  if (preSelectedCamper) {
    selectedCamper = preSelectedCamper.value;
    // Show week selection since a camper is already selected
    document.getElementById("week-selection").style.display = "block";
    // Update steps indicator
    updateSteps(1);
    // Add selected class to the camper option
    const camperOption = document
      .querySelector(`.camper-option input[value="${selectedCamper}"]`)
      .closest(".camper-option");
    camperOption.classList.add("selected");

    // Load existing selections for this camper
    loadExistingSelections(selectedCamper);
  }

  // Pricing data (initialized from PHP)
  let currentPricing = {
    dayCost: window.pricingData ? window.pricingData.dayCost : 75,
    lunchCost: window.pricingData ? window.pricingData.lunchCost : 9,
    extCareCost: window.pricingData ? window.pricingData.extCareCost : 15,
  };

  // Elements
  const camperOptions = document.querySelectorAll(
    'input[name="selected_camper"]'
  );
  const weekOptions = document.querySelectorAll('input[name="selected_week"]');
  const daySelection = document.getElementById("day-selection");
  const optionsSection = document.getElementById("options-section");
  const submitSection = document.getElementById("submit-section");
  const dayList = document.querySelector(".day-list");
  const lunchOptionsContainer = document.querySelector(
    ".lunch-options-container"
  );
  const extendedCareContainer = document.querySelector(
    ".extended-care-container"
  );
  const lunchOptionsHeader = document.querySelector("#lunch-options h4");

  // Event listeners for camper selection
  camperOptions.forEach((option) => {
    option.addEventListener("change", function () {
      selectedCamper = this.value;
      // Reset subsequent selections
      resetWeekSelection();

      // Clear any badges or status indicators from week options
      clearWeekStatusIndicators();

      // Show week selection if camper is selected
      document.getElementById("week-selection").style.display = "block";
      // Animate week section appearance
      animateSection("week-selection");
      // Update steps indicator
      updateSteps(1);

      // Load existing selections for this camper
      loadExistingSelections(selectedCamper);
    });
  });

  // Function to load existing selections for a camper
  function loadExistingSelections(camperId) {
    // console.log("Loading existing selections for camper:", camperId);

    // SECURE APPROACH: Try session-based auth first (no credentials in POST body)
    // If that fails, fall back to sending credentials (for path-restricted cookies)
    function makeRequest(includeAuth = false) {
      const bodyParams = {
        action: "getExistingSelections",
        camper_id: camperId,
      };

      // Only include credentials if session auth failed
      if (includeAuth) {
        const authKey = typeof getCookie === "function" ? getCookie("key") : "";
        const authAccount =
          typeof getCookie === "function" ? getCookie("account") : "";
        if (authKey && authAccount) {
          bodyParams.key = authKey;
          bodyParams.account = authAccount;
        }
      }

      return fetch(adminAjaxUrl, {
        method: "POST",
        credentials: "same-origin", // Important: sends session cookie
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams(bodyParams),
      });
    }

    // Try session-based auth first (most secure)
    makeRequest(false)
      .then((response) => response.json())
      .then((data) => {
        // If auth failed, try with credentials as fallback
        if (!data.success && data.message === "Authentication required") {
          return makeRequest(true).then((response) => response.json());
        }
        return data;
      })
      .then((data) => {
        // console.log("Existing cart selections data:", data);
        existingSelections = data.selections || [];
        // Continue with week selection, highlighting weeks that are already in cart
        highlightExistingSelections();

        // After loading cart selections, check for registered weeks
        getRegisteredWeeks(camperId);
      })
      .catch((error) => {
        console.error("Error loading existing selections:", error);
        existingSelections = [];

        // Still check for registered weeks even if cart selections fail
        getRegisteredWeeks(camperId);
      });
  }

  // Function to get registered weeks for a camper
  function getRegisteredWeeks(camperId) {
    makeSecureAjaxRequest(
      "getRegisteredWeeks",
      { camper_id: camperId },
      (data) => {
        // console.log("Registered weeks data:", data);
        if (data.success && data.registeredWeeks) {
          // Highlight weeks that already have registrations
          highlightRegisteredWeeks(data.registeredWeeks);
        }
      },
      (error) => {
        console.error("Error loading registered weeks:", error);
      }
    );
  }

  // Function to highlight weeks with existing registrations
  function highlightRegisteredWeeks(registeredWeeks) {
    if (!registeredWeeks.length) return;

    getRegistrationDetails(selectedCamper)
      .then((registrationDetails) => {
        // console.log("Registration details:", registrationDetails);

        document.querySelectorAll(".week-option").forEach((weekOption) => {
          const weekInput = weekOption.querySelector(
            'input[name="selected_week"]'
          );
          const weekNum = weekInput ? parseInt(weekInput.value) : 0;

          // Check if this week exists in registrations for the current camper
          const registrationInfo = registrationDetails.find(
            (reg) => reg.week_number === weekNum
          );

          if (registrationInfo) {
            // Remove any existing badges
            const existingBadge = weekOption.querySelector(
              ".registration-badge, .playpass-badge, .camp-badge"
            );
            if (existingBadge) {
              existingBadge.remove();
            }

            // Remove existing classes
            weekOption.classList.remove(
              "has-registration",
              "has-playpass",
              "has-camp"
            );

            if (registrationInfo.is_play_pass) {
              // This is a Play Pass registration
              const playPassBadge = document.createElement("div");
              playPassBadge.className = "playpass-badge";
              playPassBadge.textContent = "Play Pass";
              weekOption.appendChild(playPassBadge);

              // Add class for styling
              weekOption.classList.add("has-playpass");
            } else {
              // This is a regular camp registration
              const campBadge = document.createElement("div");
              campBadge.className = "camp-badge";
              campBadge.textContent = registrationInfo.camp_name;
              weekOption.appendChild(campBadge);

              // Add class for styling
              weekOption.classList.add("has-camp");
            }
          }
        });
      })
      .catch((error) => {
        console.error("Error highlighting registered weeks:", error);

        // Fallback to simple highlighting if detailed fetching fails
        document.querySelectorAll(".week-option").forEach((weekOption) => {
          const weekInput = weekOption.querySelector(
            'input[name="selected_week"]'
          );
          const weekNum = weekInput ? parseInt(weekInput.value) : 0;

          // Check if this week exists in registrations for the current camper
          const isRegistered = registeredWeeks.includes(weekNum);

          if (isRegistered) {
            // Mark this week as "already registered"
            const registrationBadge = document.createElement("div");
            registrationBadge.className = "registration-badge";
            registrationBadge.textContent = "Registered";
            weekOption.appendChild(registrationBadge);

            // Add class for styling
            weekOption.classList.add("has-registration");
          }
        });
      });
  }

  // Function to highlight weeks that are already in the cart
  function highlightExistingSelections() {
    if (!existingSelections.length) return;

    document.querySelectorAll(".week-option").forEach((weekOption) => {
      const weekInput = weekOption.querySelector('input[name="selected_week"]');
      const weekNum = weekInput ? parseInt(weekInput.value) : 0;

      // Check if this week exists in selections for the current camper
      const existingSelection = existingSelections.find(
        (selection) =>
          selection.data.camper_id == selectedCamper &&
          selection.data.week == weekNum
      );

      // console.log("Is in edit mode:", isEditMode, "for week:", weekNum, "Edit index:", editIndex);

      if (existingSelection) {
        // Remove any existing badges first
        const existingBadge = weekOption.querySelector(".edit-badge");
        if (existingBadge) {
          existingBadge.remove();
        }

        // Mark this week as "already in cart"
        const editBadge = document.createElement("div");
        editBadge.className = "edit-badge";
        editBadge.textContent = "In Cart";
        weekOption.appendChild(editBadge);

        // Add "edit" class for styling
        weekOption.classList.add("in-cart");
      }
    });
  }

  // Function to update steps indicator
  function updateSteps(stepIndex) {
    const steps = document.querySelectorAll(".registration-steps .step");
    steps.forEach((step, index) => {
      if (index < stepIndex) {
        step.classList.remove("active");
        step.classList.add("completed");
      } else if (index === stepIndex) {
        step.classList.add("active");
        step.classList.remove("completed");
      } else {
        step.classList.remove("active");
        step.classList.remove("completed");
      }
    });
  }

  // Function to animate section appearance
  function animateSection(sectionId) {
    const section = document.getElementById(sectionId);
    section.classList.add("animated-section");
    setTimeout(() => {
      section.classList.remove("animated-section");
    }, 500);
  }

  // Function to select camper (called from onclick in HTML)
  window.selectCamper = function (element, camperId) {
    // Remove selected class from all options
    document.querySelectorAll(".camper-option").forEach((option) => {
      option.classList.remove("selected");
    });

    // Add selected class to clicked option
    element.classList.add("selected");

    // Check radio button
    document.getElementById("camper-" + camperId).checked = true;
    selectedCamper = camperId;

    // Clear any badges or status indicators from week options
    clearWeekStatusIndicators();

    // Show week selection
    document.getElementById("week-selection").style.display = "block";
    animateSection("week-selection");

    // Update steps indicator
    updateSteps(1);

    // Reset subsequent selections
    resetWeekSelection();

    // Load existing selections for this camper
    loadExistingSelections(selectedCamper);
  };

  // Function to select week (called from onclick in HTML)
  window.selectWeek = function (element, weekNum) {
    // console.log("Selecting week:", weekNum, "Selected camper:", selectedCamper);

    // First, clear any existing error notices when selecting any week
    const messagesContainer = document.getElementById("messages-container");
    document
      .querySelectorAll(".play-pass-message.error")
      .forEach((el) => el.remove());

    // Check if this week has a regular camp registration (not Play Pass)
    if (element.classList.contains("has-camp")) {
      // Hide any displayed sections after week selection
      resetDaySelection();

      // Hide transportation section too
      const transportationSection = document.getElementById(
        "transportation-section"
      );
      if (transportationSection) {
        transportationSection.style.display = "none";
      }

      // Show notification that regular camp weeks can't have Play Pass added
      const campNotice = document.createElement("div");
      campNotice.className = "play-pass-message error";
      campNotice.innerHTML = `<strong>Cannot Add Play Pass:</strong> This week already has a camp registration. Play Pass can only be added to weeks without regular camp registrations.`;

      // Add new notice
      messagesContainer.appendChild(campNotice);

      // Scroll to notice
      campNotice.scrollIntoView({ behavior: "smooth", block: "start" });

      // Still update the week selection UI
      // Remove selected class from all options
      document.querySelectorAll(".week-option").forEach((option) => {
        option.classList.remove("selected");
      });

      // Add selected class to clicked option
      element.classList.add("selected");

      // Check radio button
      document.getElementById("week-" + weekNum).checked = true;
      selectedWeek = weekNum;

      // Update steps indicator - stay on week selection step
      updateSteps(1);

      return; // Stop further processing for invalid weeks
    }

    // Clear any edit notices when selecting a new valid week
    document
      .querySelectorAll(".play-pass-message.edit-notice")
      .forEach((el) => el.remove());

    // Original select week code continues here...
    // Remove selected class from all options
    document.querySelectorAll(".week-option").forEach((option) => {
      option.classList.remove("selected");
    });

    // Add selected class to clicked option
    element.classList.add("selected");

    // Check radio button
    document.getElementById("week-" + weekNum).checked = true;
    selectedWeek = weekNum;

    // Update pricing for selected week
    updatePricingForWeek(weekNum);

    // Update steps indicator
    updateSteps(2);

    // Check if this week is already in the cart for this camper
    const existingSelection = existingSelections.find(
      (selection) =>
        parseInt(selection.data.camper_id) === parseInt(selectedCamper) &&
        parseInt(selection.data.week) === parseInt(weekNum)
    );

    // Check if this is a Play Pass registration that can be edited
    const isPlayPassRegistration = element.classList.contains("has-playpass");
    let noticeMessage = "";

    if (existingSelection) {
      // Set edit mode for cart item
      isEditMode = true;
      editIndex = existingSelection.index;

      noticeMessage = `<strong>Edit Mode:</strong> You're editing an existing entry for this camper and week. Changes will update your current selection instead of creating a new one.`;
    } else if (isPlayPassRegistration) {
      // Editing a registered Play Pass
      isEditMode = true;
      noticeMessage = `<strong>Edit Registration:</strong> You're modifying an existing Play Pass registration. Changes will be added to your cart and processed after checkout.`;
    } else {
      // Not editing, reset edit mode
      isEditMode = false;
      editIndex = -1;
    }

    // Show appropriate notices for edit modes
    if (noticeMessage) {
      const editNotice = document.createElement("div");
      editNotice.className = "play-pass-message edit-notice";
      editNotice.innerHTML = noticeMessage;

      // Add new notice
      messagesContainer.appendChild(editNotice);

      // Scroll to notice
      editNotice.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    // Reset day selection and load day options
    resetDaySelection();

    // Always load day options, regardless of edit mode
    loadDayOptions();
  };

  // Function to reset week selection
  function resetWeekSelection() {
    // Uncheck all week options
    weekOptions.forEach((option) => {
      option.checked = false;
    });

    // Remove selected class from week options
    document.querySelectorAll(".week-option").forEach((option) => {
      option.classList.remove("selected");
    });

    // Reset selected week
    selectedWeek = null;

    // Clear day selection and subsequent sections
    resetDaySelection();

    // Reset edit mode
    isEditMode = false;
    editIndex = -1;

    // Clear any error or edit notices
    const messagesContainer = document.getElementById("messages-container");
    if (messagesContainer) {
      document
        .querySelectorAll(
          ".play-pass-message.error, .play-pass-message.edit-notice"
        )
        .forEach((el) => el.remove());
    }
  }

  // Function to reset day selection
  function resetDaySelection() {
    daySelection.style.display = "none";
    optionsSection.style.display = "none";

    // Also hide transportation section
    const transportationSection = document.getElementById(
      "transportation-section"
    );
    if (transportationSection) {
      transportationSection.style.display = "none";
    }

    // Reset all selected days
    selectedDays = [];

    // Clear contents
    dayList.innerHTML = "";
    lunchOptionsContainer.innerHTML = "";
    extendedCareContainer.innerHTML = "";

    // Reset cost summary if it exists
    const costSummary = document.getElementById("cost-summary");
    if (costSummary) {
      costSummary.style.display = "none";
    }

    // Also reset any transportation window selections
    const transportOptions = document.querySelectorAll(
      '.transport-option input[type="radio"]'
    );
    transportOptions.forEach((option) => {
      option.checked = false;
    });

    document.querySelectorAll(".transport-option").forEach((option) => {
      option.classList.remove("selected");
    });

    // Set default window B checked (common default)
    const windowB = document.getElementById("window-b");
    if (windowB) {
      windowB.checked = true;
      const parentOption = windowB.closest(".transport-option");
      if (parentOption) {
        parentOption.classList.add("selected");
      }
    }
  }

  // Function to update pricing for a specific week via AJAX
  function updatePricingForWeek(weekNum) {
    if (!weekNum) return;

    makeSecureAjaxRequest(
      "getPlayPassPricing",
      { week: weekNum },
      (data) => {
        if (!data.error) {
          // Update pricing data
          currentPricing.dayCost =
            parseFloat(data.dayCost) || currentPricing.dayCost;
          currentPricing.lunchCost =
            parseFloat(data.lunchCost) || currentPricing.lunchCost;
          currentPricing.extCareCost =
            parseFloat(data.extCareCost) || currentPricing.extCareCost;

          // Update lunch header with new price
          if (lunchOptionsHeader) {
            lunchOptionsHeader.textContent = `Hot Lunch ($${currentPricing.lunchCost.toFixed(
              2
            )}/day)`;
          }
        }
      },
      (error) => {
        console.error("Error loading pricing data:", error);
      }
    );
  }

  // Function to load day options via AJAX
  function loadDayOptions() {
    if (!selectedCamper || !selectedWeek) {
      return;
    }

    // Show loading indicator
    dayList.innerHTML =
      '<div class="loading-indicator"><div class="spinner"></div><p>Loading available days...</p></div>';
    daySelection.style.display = "block";
    animateSection("day-selection");

    // Build request parameters
    const params = {
      camper: selectedCamper,
      week: selectedWeek,
    };

    // Add edit flag if in edit mode
    if (isEditMode && editIndex >= 0) {
      params.edit_mode = 1;
      params.edit_index = editIndex;
    }

    makeSecureAjaxRequest(
      "getPlayPassDays",
      params,
      (data) => {
        // console.log("Day options response:", data);

        if (data.error) {
          // Special handling for the regular registration error
          if (data.regular_registration) {
            dayList.innerHTML = `<p class="notice">This camper is already registered for a regular camp (${data.camp_name}) in this week. Play Pass cannot be added.</p>`;
            optionsSection.style.display = "none";
            transportationSection.style.display = "none";
            return;
          }

          // Handle other errors
          dayList.innerHTML = `<p class="error">${data.error}</p>`;
          return;
        }

        // Update day cost if provided in the data
        if (data.day_cost) {
          currentPricing.dayCost = parseFloat(data.day_cost);
        }

        // Check if this is an existing registration that we're editing
        const hasRegisteredDays =
          data.days && data.days.some((day) => day.registered);
        const isEditingRegistration = hasRegisteredDays;

        // Set up the form based on what we're editing
        if (isEditingRegistration) {
          // Update form action for editing existing registration (WordPress AJAX)
          // Form action is already set to admin-ajax.php, just ensure action field exists
          let actionField = document
            .getElementById("playPassForm")
            .querySelector('input[name="action"]');
          if (!actionField) {
            actionField = document.createElement("input");
            actionField.type = "hidden";
            actionField.name = "action";
            document.getElementById("playPassForm").appendChild(actionField);
          }
          actionField.value = "editExistingPlayPass";
          // console.log("Form action changed to WordPress AJAX for editing existing registration");

          // Update submit button text
          const submitButton = document.querySelector(
            '#options-section button[type="submit"]'
          );
          if (submitButton) {
            submitButton.textContent = "Update Registration";
          }
        } else if (isEditMode && data.existing_selection) {
          // Update form action for editing cart item (WordPress AJAX)
          let actionField = document
            .getElementById("playPassForm")
            .querySelector('input[name="action"]');
          if (!actionField) {
            actionField = document.createElement("input");
            actionField.type = "hidden";
            actionField.name = "action";
            document.getElementById("playPassForm").appendChild(actionField);
          }
          actionField.value = "editPlayPassSelection";
          // console.log("Form action changed to WordPress AJAX for editing cart item");

          // Add edit index as hidden field
          let editIndexField = document.getElementById("edit_index");
          if (!editIndexField) {
            editIndexField = document.createElement("input");
            editIndexField.type = "hidden";
            editIndexField.name = "edit_index";
            editIndexField.id = "edit_index";
            document.getElementById("playPassForm").appendChild(editIndexField);
          }
          editIndexField.value = editIndex;

          // Update submit button text
          const submitButton = document.querySelector(
            '#options-section button[type="submit"]'
          );
          if (submitButton) {
            submitButton.textContent = "Update Selection";
          }
        } else {
          // Regular mode - new Play Pass registration (WordPress AJAX)
          // Form action is already set to admin-ajax.php, just ensure action field exists
          let actionField = document
            .getElementById("playPassForm")
            .querySelector('input[name="action"]');
          if (!actionField) {
            actionField = document.createElement("input");
            actionField.type = "hidden";
            actionField.name = "action";
            document.getElementById("playPassForm").appendChild(actionField);
          }
          actionField.value = "processPlayPass";
          // console.log("Form action set to WordPress AJAX for new registration");

          // Update submit button text
          const submitButton = document.querySelector(
            '#options-section button[type="submit"]'
          );
          if (submitButton) {
            submitButton.textContent = "Add to Cart";
          }
        }

        renderDayOptions(data);
      },
      (error) => {
        console.error("Error:", error);
        dayList.innerHTML =
          '<p class="error">Error loading day options. Please try again.</p>';
      }
    );
  }

  // Function to render day options
  function renderDayOptions(data) {
    dayList.innerHTML = "";

    if (data.days.length === 0) {
      dayList.innerHTML =
        '<p class="notice">No available days found for this week.</p>';
      return;
    }

    // Check if this week has any registered days
    let hasRegisteredDays = false;
    data.days.forEach((day) => {
      if (day.registered) {
        hasRegisteredDays = true;
      }
    });

    // Set editing existing registration flag
    const isEditingRegistration = hasRegisteredDays;

    // If editing existing registration, show notification
    if (isEditingRegistration) {
      const messagesContainer = document.getElementById("messages-container");
      const editRegistrationNotice = document.createElement("div");
      editRegistrationNotice.className = "play-pass-message edit-notice";
      editRegistrationNotice.innerHTML = `<strong>Edit Mode:</strong> You're editing an existing registration. Changes will be added to your cart and submitted when you checkout.`;

      // Remove any previous edit notices
      document
        .querySelectorAll(".play-pass-message.edit-notice")
        .forEach((el) => el.remove());

      // Add new notice
      messagesContainer.appendChild(editRegistrationNotice);
    }

    // Add calendar-style display
    const calendar = document.createElement("div");
    calendar.className = "play-pass-calendar";

    // Add weekday headers
    const headerRow = document.createElement("div");
    headerRow.className = "calendar-row header";
    const daysOfWeek = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];

    daysOfWeek.forEach((day) => {
      const dayHeader = document.createElement("div");
      dayHeader.className = "calendar-cell header";
      dayHeader.textContent = day;
      headerRow.appendChild(dayHeader);
    });

    calendar.appendChild(headerRow);

    // Add day selection row
    const daysRow = document.createElement("div");
    daysRow.className = "calendar-row days";

    // Map days by day number for easy access
    const daysMap = {};
    data.days.forEach((day) => {
      daysMap[day.day_num] = day;
    });

    // Get pre-selected days if in edit mode
    const preSelectedDays =
      isEditMode && data.existing_selection ? data.existing_selection.days : [];
    const preSelectedLunch =
      isEditMode && data.existing_selection
        ? data.existing_selection.lunch
        : [];
    const preSelectedMorningCare =
      isEditMode && data.existing_selection
        ? data.existing_selection.morning_care
        : [];
    const preSelectedAfternoonCare =
      isEditMode && data.existing_selection
        ? data.existing_selection.afternoon_care
        : [];

    // Add transportation selection when in edit mode or for existing registrations
    if (
      (isEditMode &&
        data.existing_selection &&
        data.existing_selection.transportation_window) ||
      (isEditingRegistration && data.transportation_window)
    ) {
      // Use either cart edit data or existing registration data
      const transportWindow =
        isEditMode && data.existing_selection
          ? data.existing_selection.transportation_window
          : data.transportation_window;

      // Log the transportation window being applied
      // console.log("Setting transportation window from data:", transportWindow);

      // The window ID is in the format 'window-a' or 'window-b'
      const windowLetter = transportWindow.split(" ")[1].toLowerCase();
      const windowOption = document.getElementById("window-" + windowLetter);

      if (windowOption) {
        windowOption.checked = true;
        // Add selected class to parent
        const parentOption = windowOption.closest(".transport-option");
        if (parentOption) {
          parentOption.classList.add("selected");
        }
        // console.log("Applied transportation window selection:", windowLetter);
      } else {
        console.error(
          "Could not find window option element for:",
          windowLetter
        );
      }
    }

    // Store original registered days for change tracking
    const originalRegisteredDays = [];

    // Create cell for each day of the week
    for (let i = 1; i <= 5; i++) {
      const dayCell = document.createElement("div");
      dayCell.className = "calendar-cell day";
      dayCell.setAttribute("data-day", daysOfWeek[i - 1]);

      const day = daysMap[i];
      if (day) {
        // Calculate date - ensure we're starting with Monday of the week
        const dayDate = new Date(data.week_start);

        // Check if week_start is already Monday
        const startDay = dayDate.getDay(); // 0 = Sunday, 1 = Monday, etc.

        if (startDay === 0) {
          // If week_start is Sunday, add 1 day to get to Monday
          dayDate.setDate(dayDate.getDate() + 1);
        } else if (startDay > 1) {
          // If week_start is after Monday, subtract to get to the previous Monday
          dayDate.setDate(dayDate.getDate() - (startDay - 1));
        }

        // Now add the day offset (i-1 for 0-based indexing)
        dayDate.setDate(dayDate.getDate() + (i - 1));

        // Create date display
        const dateDisplay = document.createElement("div");
        dateDisplay.className = "day-date";
        dateDisplay.textContent = dayDate.toLocaleDateString("en-US", {
          month: "short",
          day: "numeric",
        });

        // Check if this date is July 4th
        const isJulyFourth =
          dayDate.getMonth() === 6 && dayDate.getDate() === 4; // July is month 6 (0-indexed)

        // Create checkbox for day selection
        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.name = "selected_days[]";
        checkbox.id = `day-${day.day_num}`;
        checkbox.value = day.day_num;
        checkbox.dataset.templateId = day.template_id;
        checkbox.dataset.isRegistered = day.registered ? "true" : "false";

        // Check if this day is pre-selected (for edit mode)
        const isPreSelected = preSelectedDays.includes(day.day_num);

        // Special handling for July 4th - always mark as unavailable
        if (isJulyFourth) {
          checkbox.disabled = true;
          day.available = false; // Force unavailable

          // Add special July 4th class/status
          dayCell.classList.add("unavailable");
          dayCell.classList.add("july-fourth");
        }
        // Regular day handling
        else {
          // Set initial state - make registered days selectable when editing an existing registration
          if (day.registered) {
            originalRegisteredDays.push(day.day_num);
            checkbox.disabled = !isEditingRegistration; // Enable checkbox if editing registration
            checkbox.checked = true; // Always checked initially if registered
          } else {
            // Disable checkbox if day has passed or is not available
            checkbox.disabled = day.past_day || !day.available;
            checkbox.checked = isPreSelected;
          }
        }

        if (checkbox.checked) {
          selectedDays.push(day.day_num);
        }

        // Apply appropriate class
        if (isJulyFourth) {
          // Nothing additional needed - we already added 'unavailable' and 'july-fourth' classes
        } else if (day.past_day) {
          dayCell.classList.add("past-day");
          dayCell.classList.add("unavailable");
        } else if (day.registered) {
          dayCell.classList.add("registered");
          if (isEditingRegistration) {
            dayCell.classList.add("editable");
          }
        } else if (!day.available) {
          dayCell.classList.add("unavailable");
        } else if (isPreSelected) {
          dayCell.classList.add("pre-selected");
        }

        // Create label for checkbox
        const label = document.createElement("label");
        label.htmlFor = `day-${day.day_num}`;

        // Check if this is July 4th
        if (isJulyFourth) {
          // Create firework icon
          const icon = document.createElement("span");
          icon.className = "firework-icon";
          // icon.innerHTML = '🎆'; // Unicode firework emoji
          icon.innerHTML = "✨"; // Unicode sparkles emoji
          icon.style.fontSize = "36px"; // Make icon larger

          // Replace day name with icon
          label.innerHTML = "";
          label.appendChild(icon);
        } else {
          // Regular day name
          label.textContent = day.name;
        }

        // Add status indicator
        const status = document.createElement("div");
        status.className = "day-status";
        if (isJulyFourth) {
          status.textContent = "July 4th - Closed";
        } else if (day.past_day) {
          status.textContent = "Past Day";
        } else if (day.registered) {
          status.textContent = isEditingRegistration
            ? "Registered (Editable)"
            : "Already Registered";
        } else if (!day.available) {
          status.textContent = "Full";
        } else if (isPreSelected) {
          status.textContent = "Selected";
        } else {
          status.textContent = "Available";
        }

        // Add event listener for checkbox
        checkbox.addEventListener("change", function () {
          if (this.checked) {
            selectedDays.push(day.day_num);
            if (day.registered) {
              dayCell.classList.add("registered-kept");
              dayCell.classList.remove("registered-removed");
            } else {
              dayCell.classList.add("newly-selected");
            }
          } else {
            const index = selectedDays.indexOf(day.day_num);
            if (index > -1) {
              selectedDays.splice(index, 1);
            }

            if (day.registered) {
              dayCell.classList.add("registered-removed");
              dayCell.classList.remove("registered-kept");
            } else {
              dayCell.classList.remove("newly-selected");
            }

            // When a day is deselected, also deselect any associated options
            const dayNum = day.day_num;
            const lunchCheckbox = document.getElementById(
              `lunch_day_${dayNum}`
            );
            const morningCareCheckbox = document.getElementById(
              `morning_care_day_${dayNum}`
            );
            const afternoonCareCheckbox = document.getElementById(
              `afternoon_care_day_${dayNum}`
            );

            if (lunchCheckbox) lunchCheckbox.checked = false;
            if (morningCareCheckbox) morningCareCheckbox.checked = false;
            if (afternoonCareCheckbox) afternoonCareCheckbox.checked = false;
          }

          // Update option sections based on selected days
          updateOptionSections();

          // Regenerate lunch and extended care options to match selected days
          renderLunchOptions();
          renderExtendedCareOptions();
          updateCostSummary();
        });

        // Assemble day cell
        dayCell.appendChild(dateDisplay);
        dayCell.appendChild(checkbox);
        dayCell.appendChild(label);
        dayCell.appendChild(status);
      } else {
        // Day not available
        dayCell.classList.add("unavailable");
        dayCell.textContent = "Not Available";
      }

      daysRow.appendChild(dayCell);
    }

    calendar.appendChild(daysRow);
    dayList.appendChild(calendar);

    // Add note about pricing
    const pricingNote = document.createElement("p");
    pricingNote.className = "pricing-note";
    pricingNote.innerHTML = `<strong>Pricing:</strong> $${currentPricing.dayCost.toFixed(
      2
    )} per day. Select multiple days for your customized camp experience!`;
    dayList.appendChild(pricingNote);

    // Initialize option sections
    updateOptionSections();

    // Make the full div cell clickable
    addDayCellClickHandlers();
  }

  // Function to update option sections based on selected days
  function updateOptionSections() {
    if (selectedDays.length > 0) {
      // Show transportation section
      transportationSection.style.display = "block";
      animateSection("transportation-section");
      updateSteps(3);

      // Show options section immediately as well (no need to wait for continue button)
      optionsSection.style.display = "block";
      animateSection("options-section");
      updateSteps(4);

      // Initialize lunch and extended care options
      renderLunchOptions();
      renderExtendedCareOptions();

      // Show friends section
      const friendsSection = document.getElementById("friends-section");
      if (friendsSection) {
        friendsSection.style.display = "block";
        animateSection("friends-section");
      }

      updateCostSummary();
    } else {
      transportationSection.style.display = "none";
      optionsSection.style.display = "none";
      // Hide friends section too
      const friendsSection = document.getElementById("friends-section");
      if (friendsSection) {
        friendsSection.style.display = "none";
      }
      updateSteps(2);
    }
  }

  // Add click handler for transportation options
  document.querySelectorAll(".transport-option").forEach((option) => {
    option.addEventListener("click", function () {
      // Update the radio button
      const radio = this.querySelector('input[type="radio"]');
      radio.checked = true;

      // Remove selected class from all options
      document.querySelectorAll(".transport-option").forEach((opt) => {
        opt.classList.remove("selected");
      });

      // Add selected class to clicked option
      this.classList.add("selected");

      // Ensure options section stays visible (in case user clicks transport after options are shown)
      if (selectedDays.length > 0 && optionsSection.style.display !== "block") {
        optionsSection.style.display = "block";
        renderLunchOptions();
        renderExtendedCareOptions();
        updateCostSummary();
      }
    });
  });

  // Update the window.selectTransportation function as well
  window.selectTransportation = function (element, value) {
    // Remove selected class from all options
    document.querySelectorAll(".transport-option").forEach((option) => {
      option.classList.remove("selected");
    });

    // Add selected class to clicked option
    element.classList.add("selected");

    // Check radio button
    document.getElementById("window-" + value.toLowerCase()).checked = true;

    // Ensure options section stays visible
    if (selectedDays.length > 0 && optionsSection.style.display !== "block") {
      optionsSection.style.display = "block";
      renderLunchOptions();
      renderExtendedCareOptions();
      updateCostSummary();
    }
  };

  // Function to render lunch options
  function renderLunchOptions() {
    lunchOptionsContainer.innerHTML = "";

    if (selectedDays.length === 0) {
      return;
    }

    // Get pre-selected lunch options
    let preSelectedLunch = [];

    // If in cart edit mode
    if (isEditMode && document.getElementById("edit_index")) {
      preSelectedLunch =
        existingSelections[document.getElementById("edit_index").value]?.data
          ?.lunch || [];
    }

    // Create a flex container instead of a table
    const optionsContainer = document.createElement("div");
    optionsContainer.className = "options-flex-container";

    // Create header row
    const headerRow = document.createElement("div");
    headerRow.className = "options-flex-row options-header-row";

    // Add label cell as first column
    const labelHeader = document.createElement("div");
    labelHeader.className = "options-flex-cell options-label-cell";
    labelHeader.textContent = "Day";
    headerRow.appendChild(labelHeader);

    // Add option header
    const optionHeader = document.createElement("div");
    optionHeader.className = "options-flex-cell";
    optionHeader.textContent = "Hot Lunch";
    headerRow.appendChild(optionHeader);

    optionsContainer.appendChild(headerRow);

    // Create a row for each selected day
    const dayNames = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
    // Create a Set to eliminate duplicate days
    const uniqueDays = [...new Set(selectedDays)];
    uniqueDays.sort((a, b) => a - b);

    uniqueDays.forEach((dayNum) => {
      const dayRow = document.createElement("div");
      dayRow.className = "options-flex-row";

      // Add day name cell
      const dayCell = document.createElement("div");
      dayCell.className = "options-flex-cell options-label-cell";
      dayCell.textContent = dayNames[dayNum - 1];
      dayRow.appendChild(dayCell);

      // Add lunch checkbox cell
      const lunchCell = document.createElement("div");
      lunchCell.className = "options-flex-cell";

      const lunchCheckboxContainer = document.createElement("div");
      lunchCheckboxContainer.className = "checkbox-container";

      const lunchCheckbox = document.createElement("input");
      lunchCheckbox.type = "checkbox";
      lunchCheckbox.name = `lunch_day_${dayNum}`;
      lunchCheckbox.id = `lunch_day_${dayNum}`;
      lunchCheckbox.value = dayNum;
      lunchCheckbox.className = "options-checkbox";

      // Check if lunch was pre-selected for this day (edit mode)
      lunchCheckbox.checked = preSelectedLunch.includes(parseInt(dayNum));

      // Only enable lunch checkbox if the day is selected
      lunchCheckbox.disabled = !selectedDays.includes(parseInt(dayNum));

      const lunchLabel = document.createElement("label");
      lunchLabel.htmlFor = `lunch_day_${dayNum}`;
      lunchLabel.className = "checkbox-label";

      // Add price info to the label
      lunchLabel.textContent = `$${currentPricing.lunchCost.toFixed(2)}`;

      // Add change event to update cost summary
      lunchCheckbox.addEventListener("change", updateCostSummary);

      lunchCheckboxContainer.appendChild(lunchCheckbox);
      lunchCheckboxContainer.appendChild(lunchLabel);
      lunchCell.appendChild(lunchCheckboxContainer);

      dayRow.appendChild(lunchCell);
      optionsContainer.appendChild(dayRow);
    });

    lunchOptionsContainer.appendChild(optionsContainer);
  }

  // Function to render extended care options
  function renderExtendedCareOptions() {
    extendedCareContainer.innerHTML = "";

    if (selectedDays.length === 0) {
      return;
    }

    // Get pre-selected extended care options
    let preSelectedMorningCare = [];
    let preSelectedAfternoonCare = [];

    // If in cart edit mode
    if (isEditMode && document.getElementById("edit_index")) {
      preSelectedMorningCare =
        existingSelections[document.getElementById("edit_index").value]?.data
          ?.morning_care || [];
      preSelectedAfternoonCare =
        existingSelections[document.getElementById("edit_index").value]?.data
          ?.afternoon_care || [];
    }

    // Create a flex container instead of a table
    const optionsContainer = document.createElement("div");
    optionsContainer.className = "options-flex-container";

    // Create header row
    const headerRow = document.createElement("div");
    headerRow.className = "options-flex-row options-header-row";

    // Add day header as first column
    const dayHeader = document.createElement("div");
    dayHeader.className = "options-flex-cell options-label-cell";
    dayHeader.textContent = "Day";
    headerRow.appendChild(dayHeader);

    // Add morning care header
    const morningHeader = document.createElement("div");
    morningHeader.className = "options-flex-cell";
    morningHeader.textContent = "Morning Care";
    headerRow.appendChild(morningHeader);

    // Add afternoon care header
    const afternoonHeader = document.createElement("div");
    afternoonHeader.className = "options-flex-cell";
    afternoonHeader.textContent = "Afternoon Care";
    headerRow.appendChild(afternoonHeader);

    optionsContainer.appendChild(headerRow);

    // Create a row for each selected day
    const dayNames = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
    // Create a Set to eliminate duplicate days
    const uniqueDays = [...new Set(selectedDays)];
    uniqueDays.sort((a, b) => a - b);

    uniqueDays.forEach((dayNum) => {
      const dayRow = document.createElement("div");
      dayRow.className = "options-flex-row";

      // Add day name cell
      const dayCell = document.createElement("div");
      dayCell.className = "options-flex-cell options-label-cell";
      dayCell.textContent = dayNames[dayNum - 1];
      dayRow.appendChild(dayCell);

      // Add morning care checkbox cell
      const morningCell = document.createElement("div");
      morningCell.className = "options-flex-cell";

      const morningCheckboxContainer = document.createElement("div");
      morningCheckboxContainer.className = "checkbox-container";

      const morningCheckbox = document.createElement("input");
      morningCheckbox.type = "checkbox";
      morningCheckbox.name = `morning_care_day_${dayNum}`;
      morningCheckbox.id = `morning_care_day_${dayNum}`;
      morningCheckbox.value = dayNum;
      morningCheckbox.className = "options-checkbox";

      // Check if morning care was pre-selected for this day
      morningCheckbox.checked = preSelectedMorningCare.includes(
        parseInt(dayNum)
      );

      // Only enable morning care checkbox if the day is selected
      morningCheckbox.disabled = !selectedDays.includes(parseInt(dayNum));

      const morningLabel = document.createElement("label");
      morningLabel.htmlFor = `morning_care_day_${dayNum}`;
      morningLabel.className = "checkbox-label";
      morningLabel.textContent = `$${currentPricing.extCareCost.toFixed(2)}`;

      // Add change event to update cost summary
      morningCheckbox.addEventListener("change", updateCostSummary);

      morningCheckboxContainer.appendChild(morningCheckbox);
      morningCheckboxContainer.appendChild(morningLabel);
      morningCell.appendChild(morningCheckboxContainer);

      dayRow.appendChild(morningCell);

      // Add afternoon care checkbox cell
      const afternoonCell = document.createElement("div");
      afternoonCell.className = "options-flex-cell";

      const afternoonCheckboxContainer = document.createElement("div");
      afternoonCheckboxContainer.className = "checkbox-container";

      const afternoonCheckbox = document.createElement("input");
      afternoonCheckbox.type = "checkbox";
      afternoonCheckbox.name = `afternoon_care_day_${dayNum}`;
      afternoonCheckbox.id = `afternoon_care_day_${dayNum}`;
      afternoonCheckbox.value = dayNum;
      afternoonCheckbox.className = "options-checkbox";

      // Check if afternoon care was pre-selected for this day
      afternoonCheckbox.checked = preSelectedAfternoonCare.includes(
        parseInt(dayNum)
      );

      // Only enable afternoon care checkbox if the day is selected
      afternoonCheckbox.disabled = !selectedDays.includes(parseInt(dayNum));

      const afternoonLabel = document.createElement("label");
      afternoonLabel.htmlFor = `afternoon_care_day_${dayNum}`;
      afternoonLabel.className = "checkbox-label";
      afternoonLabel.textContent = `$${currentPricing.extCareCost.toFixed(2)}`;

      // Add change event to update cost summary
      afternoonCheckbox.addEventListener("change", updateCostSummary);

      afternoonCheckboxContainer.appendChild(afternoonCheckbox);
      afternoonCheckboxContainer.appendChild(afternoonLabel);
      afternoonCell.appendChild(afternoonCheckboxContainer);

      dayRow.appendChild(afternoonCell);

      optionsContainer.appendChild(dayRow);
    });

    extendedCareContainer.appendChild(optionsContainer);

    // Add note about drop-off/pick-up
    const careNote = document.createElement("p");
    careNote.className = "care-note";
    careNote.innerHTML =
      "<strong>Note:</strong> Morning Care (7:00-9:30 AM) and Afternoon Care (4:00-6:00 PM) are available at our Lake Stevens campus only and provide flexibility in your drop off and pick up times.";
    extendedCareContainer.appendChild(careNote);
  }

  // Function to update cost summary
  function updateCostSummary() {
    const costSummary = document.getElementById("cost-summary");
    const costBreakdown = document.getElementById("cost-breakdown");

    // Check if elements exist
    if (!costSummary || !costBreakdown) return;

    const selectedDaysElements = document.querySelectorAll(
      'input[name="selected_days[]"]:checked'
    );
    const selectedLunch = document.querySelectorAll(
      'input[name^="lunch_day_"]:checked'
    );
    const selectedMorningCare = document.querySelectorAll(
      'input[name^="morning_care_day_"]:checked'
    );
    const selectedAfternoonCare = document.querySelectorAll(
      'input[name^="afternoon_care_day_"]:checked'
    );
    const transportationWindow = document.querySelector(
      'input[name="transportation_window"]:checked'
    );

    // Count original days and new days for cost differential calculations
    const originalDays = [];
    const removedDays = [];
    const addedDays = [];
    const keptDays = [];

    // Process days to determine which are new, removed, or kept
    document
      .querySelectorAll('input[name="selected_days[]"]')
      .forEach((checkbox) => {
        const dayNum = parseInt(checkbox.value);
        const isRegistered = checkbox.dataset.isRegistered === "true";
        const isChecked = checkbox.checked;

        if (isRegistered) {
          originalDays.push(dayNum);
          if (isChecked) {
            keptDays.push(dayNum);
          } else {
            removedDays.push(dayNum);
          }
        } else if (isChecked) {
          addedDays.push(dayNum);
        }
      });

    // Calculate costs
    const dayCost = currentPricing.dayCost;
    const lunchCost = currentPricing.lunchCost;
    const extCareCost = currentPricing.extCareCost;
    const transportCost = 0; // Direct drop-off is included with base price

    // Calculate totals for different components
    const addedDayTotal = addedDays.length * dayCost;
    const removedDayTotal = removedDays.length * dayCost;
    const netDayTotal = addedDayTotal - removedDayTotal;

    // Similarly calculate lunch and extended care costs
    const lunchTotal = selectedLunch.length * lunchCost;
    const morningCareTotal = selectedMorningCare.length * extCareCost;
    const afternoonCareTotal = selectedAfternoonCare.length * extCareCost;

    const currentTotal =
      (keptDays.length + addedDays.length) * dayCost +
      lunchTotal +
      morningCareTotal +
      afternoonCareTotal +
      transportCost;

    // Checking if this is an edit to an existing registration
    const isEditingRegistration = originalDays.length > 0;

    let html = "";

    if (isEditingRegistration) {
      // Show differential cost breakdown for existing registrations
      html += `<h4>Cost Changes:</h4>`;

      if (addedDays.length > 0) {
        html += `<div class="cost-row added">
                    <div class="cost-label">Added Days (${
                      addedDays.length
                    } x ${dayCost.toFixed(2)})</div>
                    <div class="cost-value">+$${addedDayTotal.toFixed(2)}</div>
                </div>`;
      }

      if (removedDays.length > 0) {
        html += `<div class="cost-row removed">
                    <div class="cost-label">Removed Days (${
                      removedDays.length
                    } x ${dayCost.toFixed(2)})</div>
                    <div class="cost-value">-$${removedDayTotal.toFixed(
                      2
                    )}</div>
                </div>`;
      }

      // Show net change in cost
      const netChange = netDayTotal; // In a real scenario, add lunch and ext care changes

      html += `<div class="cost-row total-change ${
        netChange >= 0 ? "added" : "removed"
      }">
                <div class="cost-label">Total Change</div>
                <div class="cost-value">${
                  netChange >= 0 ? "+" : ""
                }$${netChange.toFixed(2)}</div>
            </div>`;

      html += `<hr class="cost-divider">`;
    }

    // Then show the full current cost breakdown
    html += `<h4>${
      isEditingRegistration ? "Updated Cost:" : "Cost Summary:"
    }</h4>`;

    html += `<div class="cost-row">
            <div class="cost-label">Camp Days (${
              selectedDaysElements.length
            } x ${dayCost.toFixed(2)})</div>
            <div class="cost-value">$${(
              selectedDaysElements.length * dayCost
            ).toFixed(2)}</div>
        </div>`;

    if (selectedLunch.length > 0) {
      html += `<div class="cost-row">
                <div class="cost-label">Hot Lunch (${
                  selectedLunch.length
                } x ${lunchCost.toFixed(2)})</div>
                <div class="cost-value">$${lunchTotal.toFixed(2)}</div>
            </div>`;
    }

    if (selectedMorningCare.length > 0) {
      html += `<div class="cost-row">
                <div class="cost-label">Morning Care (${
                  selectedMorningCare.length
                } x ${extCareCost.toFixed(2)})</div>
                <div class="cost-value">$${morningCareTotal.toFixed(2)}</div>
            </div>`;
    }

    if (selectedAfternoonCare.length > 0) {
      html += `<div class="cost-row">
                <div class="cost-label">Afternoon Care (${
                  selectedAfternoonCare.length
                } x ${extCareCost.toFixed(2)})</div>
                <div class="cost-value">$${afternoonCareTotal.toFixed(2)}</div>
            </div>`;
    }

    // doesn't upate when the transportaion window is changed - as it doen't impact the cost, commenting it out for now
    /*
        if(transportationWindow) {
            const windowDesc = formatTransportationWindow(transportationWindow.value);
            html += `<div class="cost-row">
                <div class="cost-label">Transportation: ${windowDesc}</div>
                <div class="cost-value">Included</div>
            </div>`;
        } else {
            // If no window is explicitly selected, default to Window B for display
            const windowDesc = formatTransportationWindow('Window B');
            html += `<div class="cost-row">
                <div class="cost-label">Transportation: ${windowDesc}</div>
                <div class="cost-value">Included</div>
            </div>`;
        }
        */
    html += `<div class="cost-row total">
            <div class="cost-label">Total</div>
            <div class="cost-value">$${currentTotal.toFixed(2)}</div>
        </div>`;

    costBreakdown.innerHTML = html;

    // Update form action and button text for editing registration
    if (isEditingRegistration) {
      // Change form action to WordPress AJAX for editing
      const form = document.getElementById("playPassForm");
      if (form) {
        let actionField = form.querySelector('input[name="action"]');
        if (!actionField) {
          actionField = document.createElement("input");
          actionField.type = "hidden";
          actionField.name = "action";
          form.appendChild(actionField);
        }
        actionField.value = "editExistingPlayPass";

        // Also add a hidden field to indicate this is an edit to an existing registration
        let editRegistrationField =
          document.getElementById("edit_registration");
        if (!editRegistrationField) {
          editRegistrationField = document.createElement("input");
          editRegistrationField.type = "hidden";
          editRegistrationField.name = "edit_registration";
          editRegistrationField.id = "edit_registration";
          editRegistrationField.value = "1";
          form.appendChild(editRegistrationField);
        }

        // Update button text
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
          submitButton.textContent = "Update Registration";
        }
      }
    }

    // Display cost summary only when days are selected
    costSummary.style.display =
      selectedDaysElements.length > 0 ? "block" : "none";
  }

  // Form validation
  document
    .getElementById("playPassForm")
    .addEventListener("submit", function (event) {
      let isValid = true;
      let errorMessage = "";

      // Check if camper is selected
      const selectedCamperElem = document.querySelector(
        'input[name="selected_camper"]:checked'
      );
      if (!selectedCamperElem) {
        isValid = false;
        errorMessage = "Please select a camper";
      }

      // Check if week is selected
      const selectedWeekElem = document.querySelector(
        'input[name="selected_week"]:checked'
      );
      if (isValid && !selectedWeekElem) {
        isValid = false;
        errorMessage = "Please select a week";
      }

      // Check if at least one day is selected
      const selectedDaysElements = document.querySelectorAll(
        'input[name="selected_days[]"]:checked'
      );
      if (isValid && selectedDaysElements.length === 0) {
        isValid = false;
        errorMessage = "Please select at least one day";
      }

      // Check if transportation window is selected
      const transportationWindow = document.querySelector(
        'input[name="transportation_window"]:checked'
      );
      if (isValid && !transportationWindow) {
        isValid = false;
        errorMessage = "Please select a transportation window";
      }

      if (!isValid) {
        event.preventDefault();

        // Remove any existing error messages
        const existingError = document.querySelector(
          "#messages-container .play-pass-message.error"
        );
        if (existingError) {
          existingError.remove();
        }

        // Show error message
        const errorDiv = document.createElement("div");
        errorDiv.className = "play-pass-message error";
        errorDiv.textContent = errorMessage;

        // Insert at top of messages container
        const messagesContainer = document.getElementById("messages-container");
        messagesContainer.insertBefore(errorDiv, messagesContainer.firstChild);

        // Scroll to error message
        messagesContainer.scrollIntoView({
          behavior: "smooth",
          block: "start",
        });

        return false;
      }
      // Prevent default form submission - we'll use AJAX instead
      event.preventDefault();

      // Remove any existing hidden fields to avoid duplicates
      const existingCamperId = this.querySelector('input[name="camper_id"]');
      const existingWeekNum = this.querySelector('input[name="week_num"]');
      const existingTransport = this.querySelector(
        'input[name="transportation_window"][type="hidden"]'
      );
      const existingEditMode = this.querySelector('input[name="edit_mode"]');
      const existingEditIndex = this.querySelector('input[name="edit_index"]');

      if (existingCamperId) existingCamperId.remove();
      if (existingWeekNum) existingWeekNum.remove();
      if (existingTransport) existingTransport.remove();
      if (existingEditMode) existingEditMode.remove();
      if (existingEditIndex) existingEditIndex.remove();

      // All valid, add hidden fields with selected values
      if (selectedCamperElem && selectedWeekElem && transportationWindow) {
        // Add camper_id (PHP expects this name, form has selected_camper)
        const camperInput = document.createElement("input");
        camperInput.type = "hidden";
        camperInput.name = "camper_id";
        camperInput.value = selectedCamperElem.value;
        this.appendChild(camperInput);

        // Add week_num (PHP expects this name, form has selected_week)
        const weekInput = document.createElement("input");
        weekInput.type = "hidden";
        weekInput.name = "week_num";
        weekInput.value = selectedWeekElem.value;
        this.appendChild(weekInput);

        // Add transportation window value
        const transportInput = document.createElement("input");
        transportInput.type = "hidden";
        transportInput.name = "transportation_window";
        transportInput.value = transportationWindow.value;
        this.appendChild(transportInput);

        // Add edit mode flag if in edit mode
        if (isEditMode) {
          const editModeInput = document.createElement("input");
          editModeInput.type = "hidden";
          editModeInput.name = "edit_mode";
          editModeInput.value = "1";
          this.appendChild(editModeInput);

          // Add edit_index if available
          if (editIndex >= 0) {
            const editIndexInput = document.createElement("input");
            editIndexInput.type = "hidden";
            editIndexInput.name = "edit_index";
            editIndexInput.value = editIndex;
            this.appendChild(editIndexInput);
          }
        }
      }

      // Ensure action is set for WordPress AJAX
      // Check if action field already exists (set by other parts of the code)
      let actionField = this.querySelector('input[name="action"]');
      if (!actionField) {
        actionField = document.createElement("input");
        actionField.type = "hidden";
        actionField.name = "action";
        this.appendChild(actionField);
        // Default to processPlayPass if not already set
        actionField.value = "processPlayPass";
      }
      // If action field exists but is empty, set default
      if (!actionField.value) {
        actionField.value = "processPlayPass";
      }

      // Show loading state
      const submitButton = this.querySelector('button[type="submit"]');
      const originalButtonText = submitButton ? submitButton.textContent : "";
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = "Processing...";
      }

      // Get form data
      const formData = new FormData(this);

      // Submit via WordPress AJAX
      fetch(adminAjaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          // Handle JSON response from WordPress AJAX
          if (data.success) {
            // Success - redirect to playpass page
            window.location.href = data.redirect || "/camps/queue/playpass";
          } else {
            // Error - show message and re-enable button
            if (submitButton) {
              submitButton.disabled = false;
              submitButton.textContent = originalButtonText;
            }

            // Show error message
            const errorDiv = document.createElement("div");
            errorDiv.className = "play-pass-message error";
            errorDiv.textContent =
              data?.message ||
              data?.error ||
              "An error occurred. Please try again.";
            const messagesContainer =
              document.getElementById("messages-container");
            messagesContainer.insertBefore(
              errorDiv,
              messagesContainer.firstChild
            );
            messagesContainer.scrollIntoView({
              behavior: "smooth",
              block: "start",
            });
          }
        })
        .catch((error) => {
          console.error("Error submitting form:", error);
          if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = originalButtonText;
          }

          // Show error message
          const errorDiv = document.createElement("div");
          errorDiv.className = "play-pass-message error";
          errorDiv.textContent = "An error occurred. Please try again.";
          const messagesContainer =
            document.getElementById("messages-container");
          messagesContainer.insertBefore(
            errorDiv,
            messagesContainer.firstChild
          );
        });
    });
});

function removeSelection(index) {
  if (confirm("Are you sure you want to remove this selection?")) {
    fetch(adminAjaxUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `action=removePlayPassSelection&index=${index}`,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          window.location.reload();
        } else {
          alert("Error removing selection: " + data.message);
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("Error removing selection");
      });
  }
}

function removeEdit(editId) {
  if (confirm("Are you sure you want to remove this edit?")) {
    fetch(adminAjaxUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `action=removePlayPassEdit&edit_id=${editId}`,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          window.location.reload();
        } else {
          alert("Error removing edit: " + data.message);
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("Error removing edit");
      });
  }
}

// Function to get registration details for a camper
function getRegistrationDetails(camperId) {
  return new Promise((resolve, reject) => {
    makeSecureAjaxRequest(
      "getRegisteredWeeks",
      { camper_id: camperId },
      (data) => {
        if (data.success) {
          // Get details for each registered week
          const registeredWeeks = data.registeredWeeks || [];

          if (registeredWeeks.length === 0) {
            resolve([]);
            return;
          }

          // Get camp type information for each week
          const requests = registeredWeeks.map((weekNum) => {
            makeSecureAjaxRequest(
              "getCampTypeForWeek",
              { camper_id: camperId, week_num: weekNum },
              (weekData) => {
                if (weekData.success) {
                  return {
                    week_number: weekNum,
                    camp_name: weekData.camp_name || "Unknown Camp",
                    is_play_pass: weekData.is_play_pass || false,
                  };
                }
                return null;
              }
            ).catch((error) => {
              console.error(
                `Error getting camp type for week ${weekNum}:`,
                error
              );
              return null;
            });
          });

          Promise.all(requests)
            .then((results) => {
              resolve(results.filter((result) => result !== null));
            })
            .catch((error) => {
              console.error("Error processing registration details:", error);
              resolve([]);
            });
        } else {
          resolve([]);
        }
      }
    ).catch((error) => {
      console.error("Error getting registered weeks:", error);
      reject(error);
    });
  });
}

function formatTransportationWindow(window) {
  if (window === "Window A") {
    return "Drop-off 8:00-8:30 AM, Pick-up 4:00-4:15 PM";
  } else if (window === "Window B") {
    return "Drop-off 9:00-9:30 AM, Pick-up 5:00-5:15 PM";
  }
  return window; // Default fallback
}

function clearWeekStatusIndicators() {
  document.querySelectorAll(".week-option").forEach((weekOption) => {
    // Remove all status-related classes
    weekOption.classList.remove(
      "has-playpass",
      "has-camp",
      "in-cart",
      "selected"
    );

    // Remove any badges
    const badges = weekOption.querySelectorAll(
      ".playpass-badge, .camp-badge, .registration-badge, .edit-badge"
    );
    badges.forEach((badge) => badge.remove());
  });
}

function addDayCellClickHandlers() {
  // Get all day cells
  const dayCells = document.querySelectorAll(".calendar-cell.day");

  // Add click handler to each cell
  dayCells.forEach((cell) => {
    // Skip if the cell is marked as unavailable or july-fourth
    if (
      cell.classList.contains("unavailable") ||
      cell.classList.contains("july-fourth")
    ) {
      return;
    }

    // Add pointer cursor to show it's clickable
    cell.style.cursor = "pointer";

    // Create a single click handler for the entire cell
    const cellClickHandler = function (e) {
      // Don't handle click if it was directly on the checkbox
      if (e.target.type === "checkbox") {
        return;
      }

      e.preventDefault();
      e.stopPropagation();

      // Get the checkbox inside this cell
      const checkbox = cell.querySelector('input[type="checkbox"]');

      if (!checkbox || checkbox.disabled) {
        return;
      }

      // Toggle the checkbox directly
      checkbox.checked = !checkbox.checked;

      // Force the update of visual state immediately
      if (checkbox.checked) {
        if (checkbox.dataset.isRegistered === "true") {
          cell.classList.add("registered-kept");
          cell.classList.remove("registered-removed");
        } else {
          cell.classList.add("newly-selected");
        }
      } else {
        if (checkbox.dataset.isRegistered === "true") {
          cell.classList.add("registered-removed");
          cell.classList.remove("registered-kept");
        } else {
          cell.classList.remove("newly-selected");
        }
      }

      // Manually dispatch the change event after state is updated
      const changeEvent = new Event("change", { bubbles: true });
      checkbox.dispatchEvent(changeEvent);

      return false;
    };

    // Apply the handler to the cell
    cell.addEventListener("click", cellClickHandler);

    // Also apply to all child elements except the checkbox
    const childElements = cell.querySelectorAll(
      '*:not(input[type="checkbox"])'
    );
    childElements.forEach((element) => {
      element.addEventListener("click", cellClickHandler);
    });
  });
}

function handleProcessPlayPassCheckout() {
  const payload = new URLSearchParams({
    action: "processPlayPassCheckout",
  });
  const submitButton = document.getElementById("playpass-checkout-btn");
  const originalButtonText = submitButton ? submitButton.textContent : "";
  if (submitButton) {
    submitButton.disabled = true;
    submitButton.textContent = "Processing...";
  }
  fetch(adminAjaxUrl, {
    method: "POST",
    credentials: "same-origin",
    body: payload,
  })
    .then((response) => response.json())
    .then((data) => {
      // Handle JSON response from WordPress AJAX
      if (data.success) {
        // Success - redirect to playpass page
        if (data.redirect) {
          window.location.href = data.redirect || "/camps/queue/playpass";
        } else if (data?.action) {
          fetch(adminAjaxUrl, {
            method: "POST",
            credentials: "same-origin",
            body: new URLSearchParams({
              action: "processPlayPassCart",
            }),
          })
            .then((response) => response.json())
            .then((x) => {
              if (x.redirect) {
                window.location.href = x.redirect || "/camps/queue/playpass";
              }
            });
        }
      } else {
        // Error - show message and re-enable button
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = originalButtonText;
        }

        // Show error message
        const errorDiv = document.createElement("div");
        errorDiv.className = "play-pass-message error";
        errorDiv.textContent =
          data?.message ||
          data?.error ||
          "An error occurred. Please try again.";
        const messagesContainer = document.getElementById("messages-container");
        messagesContainer.insertBefore(errorDiv, messagesContainer.firstChild);
        messagesContainer.scrollIntoView({
          behavior: "smooth",
          block: "start",
        });
      }
    })
    .catch((error) => {
      console.error("Error submitting form:", error);
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = originalButtonText;
      }

      // Show error message
      const errorDiv = document.createElement("div");
      errorDiv.className = "play-pass-message error";
      errorDiv.textContent = "An error occurred. Please try again.";
      const messagesContainer = document.getElementById("messages-container");
      messagesContainer.insertBefore(errorDiv, messagesContainer.firstChild);
    });
}
