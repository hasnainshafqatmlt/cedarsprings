// display the transportation option description whenever selected
$("#transportation").change(function() {
    let templateid = $("#transportation").val();

    $("#transportationDescription").html(transportationOptions[templateid]);

    // remove any exception fields
    $('#additionalBusesSection').hide();
    $('.additionalBuses').remove();
    $('#additionalBusesSection').children("label").remove();
    
    // check to see if the chosen option needs alternative choices
    if(findValue(weeksFull, templateid))
    {
       setLoading(true);
       createExceptionTransportationField(templateid);
    }

    return true;
});

function updateBusDescription(selectValue) {
    const templateid = selectValue.value;

    $("#transportationDescription").html(transportationOptions[templateid]);
    return true;
}

// itterate through the weeksfull object to find if the chosen transportation object exists
function findValue(object, value) {
    for (let key in object) {
      if (key === value) {
        return true;
      }
    }
    return false;
  }

/* puts the transportation segment into and out of a loading state to prevent clicking faster than AJAX can handle */
function setLoading(status = false) {
    $("#transportation").prop('disabled', status);
    $('.additionalBuses').prop('disabled', status);

  }

/**
 * 
 * @param {int} templateid 
 * takes the templateid of the primary transportation choice and provides any needed alternatives for weeks listed
 */
function createExceptionTransportationField(templateid) {

    // build a select for each week which the primary choice is not available
    // this information is cached in the fullWeeks variable in the submitCamperQueue page
    // var weeksFull = {"107203":{"1":true},"126473":{"1":true},"107205":{"1":true},"126683":{"1":true},"107229":{"1":true}};

    weeks = weeksFull[templateid];

    for( let week in weeks) {
        // create a select element for each week
            // give it an ID
            $('#additionalBusesSection').append($('<label>' ,{
                                                    class : "control-label detail-info",
                                                    for : 'addBus-' +week,
                                                    text: summerSchedule[week-1]}));
                                                
            $('#additionalBusesSection').append($('<select>' ,{
                                                    class : "form-control detail-info additionalBuses",
                                                    id : 'addBus-' +week,
                                                    name: "additionalBuses-" +week,
                                                    onchange: 'updateBusDescription(this)'
                                                    }));
            $('#addBus-'+week).prop('required',true);
                                                
        // run an ajax reuqest to get the transportation options
            // populate the options using the preset ID (this ensure things show up on the form in the right order)

        const formData = {
            'week'  : week
            }; 
    
        $.ajax({
            type        : 'POST', // define the type of HTTP verb we want to use (POST for our form)
            url         : 'ajax/getAdditionalTransportationOptions.php', // the url where we want to POST
            data        : formData, // our data object
            dataType    : 'json', // what type of data do we expect back from the server
            })
            .done(function(data) {
                const select = $("#addBus-" +week);
                if(data['error']) {
                    //console.log(data['error']);
                    return true;
                }
                // add a select one row
                select.append($("<option>", {
                    value: "",
                    text: "-- Make a Selection --"
                }));

                data.forEach(function(bus) {
                    select.append(
                        $("<option>", {
                            value: bus.templateid,
                            text: bus.name
                        })
                    );
                });
        
            })
            .fail(function() {
               //console.log("Failed to load bus list for week " +week);
            });
    }
   
    // when everything is done load, unset the loading
    $('#additionalBusesSection').show();
    setLoading();
}