function submitCreateAccount() {
    loadingStart();

    if(!validateForm()) {
        loadingStop();
        return false;
    }

    // if the form is good - we'll package up the data, send it to an ajax page
    // we then either return the error that the account exists, or we move on to the add person page
    var parentFirstName = document.getElementById("parentFirstName").value.trim();
    var parentLastName = document.getElementById("parentLastName").value.trim();
    var emailAddress = document.getElementById("emailAddress").value.trim();
    var phoneNumber = document.getElementById("phoneNumber").value.trim();
    var password = document.getElementById("password").value;
    var address = document.getElementById("address").value.trim();
    var state = document.getElementById("state").value.trim();
    var city = document.getElementById("city").value.trim();
    var zip = document.getElementById("zip").value.trim();

    const formData = {
        'firstName'     : parentFirstName,
        'lastName'      : parentLastName,
        'emailAddress'  : emailAddress,
        'phoneNumber'   : phoneNumber,
        'password'      : password,
        'address'       : address,
        'state'         : state,
        'city'          : city,
        'zip'           : zip
        }; 
    
    $.ajax({
        type        : 'POST', // define the type of HTTP verb we want to use (POST for our form)
        url         : 'ajax/putCreateAccount.php', // the url where we want to POST
        data        : formData, // our data object
        dataType    : 'json', // what type of data do we expect back from the server
        })
        .done(function(data) {
            if(data == null) {
                // this almost always means an API error
                $('.server_error').show();
                loadingStop();
                return false;
            }

            if(data['Authenticated'] == true) {
                // if we're here, we authenticated and we're good
                setCookie('account', data['account']);
                setCookie('key', data['key']);
                setCookie('name', data['name']);

                //let get = '';
                if(data['username'] != '') {
                  //  get = '?username=' +data['username'];
                  setCookie('modifiedUserName', data['username']);
                }
                //document.location = "addPerson.php" +get;
                
                document.location = "addPerson.php";
                return true;
            }

            if(data.status === 'error') {
                handleAccountError(data);
                loadingStop();
                return false;
            }
        
            // Handle duplicate account case
            if(Array.isArray(data) && data[0] === 'User name already exists') {
                $('.ultracamp_error').html('There is already an account with this email address. Please try to recover your existing account by <a href=# data-toggle="modal" data-target="#myModal" data-msg="resetAccount">clicking here</a>.');
                $('.ultracamp_error').show();
                loadingStop();
                return false;
            }

            // Fallback error case
            $('.server_error').show();
            loadingStop();
            return false;
           
        }) .fail(function() {
            $('.server_error').show();

            loadingStop();
            return false;

        });

    return true;
}

// upon succesfull creation of a new person, this resets the form
function successMessage() {
    $('.server_success').show();

    document.getElementById("camperFirstName").value = '';
    document.getElementById("camperLastName").value = '';
    document.getElementById("camperDOB").value = '';
    document.getElementById("camperFemale").checked = false;
    document.getElementById("camperMale").checked = false;

    loadingStop();

}

function handleAccountError(response) {
    // Clear any existing error displays
    $('.ultracamp_error').hide();
    $('.server_error').hide();
    $('.field-error').remove();
    $('.form-group').removeClass('has-error');

    // Handle temp unavailable (Ultracamp test parameter error)
    if (response.message === "temporarily_unavailable") {
        $('.ultracamp_error').html(
            'We apologize, but we are currently experiencing technical difficulties with our registration system. ' +
            'You can create your account directly with our registration provider by ' +
            `<a href="${response.redirect}" target="_blank">clicking here</a>. ` +
            'Once your account is created, you can return here to register for camp.'
        );
        $('.ultracamp_error').show();
        return;
    }

    // Handle field-specific errors
    if (response.field) {
        const field = $(`#${response.field}`);
        const formGroup = field.closest('.form-group');
        formGroup.addClass('has-error');
        formGroup.find('.new-account-input')
                .append(`<div class="field-error">${response.message}</div>`);
        $('.field-error').show();
        
        // Scroll to the error field
        $('html, body').animate({
            scrollTop: formGroup.offset().top - 100
        }, 200);
        
        return;
    }

    // Generic error case
    $('.server_error').text(response.message || 'An error occurred while creating your account. Please try again.');
    $('.server_error').show();
}

/** Form validation  */
function validateForm() {
    let isValid = true;
    const fields = [
        {id: 'parentFirstName', label: "Parent's First Name", type: 'text'},
        {id: 'parentLastName', label: "Parent's Last Name", type: 'text'},
        {id: 'phoneNumber', label: "Phone Number", type: 'phone'},
        {id: 'emailAddress', label: "Email Address", type: 'email'},
        {id: 'password', label: "Password", type: 'password'},
        {id: 'passwordConfirm', label: "Password Confirmation", type: 'password'},
        {id: 'address', label: "Street Address", type: 'text'},
        {id: 'city', label: "City", type: 'text'},
        {id: 'state', label: "State", type: 'text'},
        {id: 'zip', label: "Zip Code", type: 'zip'}
    ];

    // Clear previous errors
    $('.field-error').remove();
    $('.form-group').removeClass('has-error');
    $('#formValidationMsg').html('&nbsp;');

    fields.forEach(field => {
        const element = $(`#${field.id}`);
        const value = element.val().trim();
        const formGroup = element.closest('.form-group');

        // Check for empty fields
        if (!value) {
            isValid = false;
            formGroup.addClass('has-error');
            formGroup.find('.new-account-input')
                    .append(`<div class="field-error">${field.label} is required</div>`);
            $('.field-error').show();
            return;
        }

        // Field-specific validation
        switch(field.type) {
            case 'phone':
                const phoneRegex = /^\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/;
                if (!phoneRegex.test(value.replace(/[^\d]/g, ''))) {
                    isValid = false;
                    formGroup.addClass('has-error');
                    formGroup.find('.new-account-input')
                            .append('<div class="field-error">Please enter a valid 10-digit phone number</div>');
                    $('.field-error').show();
                }
                break;

            case 'email':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    isValid = false;
                    formGroup.addClass('has-error');
                    formGroup.find('.new-account-input')
                            .append('<div class="field-error">Please enter a valid email address</div>');
                    $('.field-error').show();
                }
                break;

            case 'zip':
                const zipRegex = /(^\d{5}$)|(^\d{5}-\d{4}$)/;
                if (!zipRegex.test(value)) {
                    isValid = false;
                    formGroup.addClass('has-error');
                    formGroup.find('.new-account-input')
                            .append('<div class="field-error">Please enter a valid 5-digit zip code</div>');
                    $('.field-error').show();
                }
                break;
        }
    });

    // Password validation
    const password = $('#password').val();
    const confirmPassword = $('#passwordConfirm').val();
    const passwordGroup = $('#password').closest('.form-group');

    if (password !== confirmPassword) {
        isValid = false;
        passwordGroup.addClass('has-error');
        $('#passwordConfirm').closest('.form-group').addClass('has-error');
        passwordGroup.find('.new-account-input')
                    .append('<div class="field-error">Passwords do not match</div>');
        $('.field-error').show();
    }

    // Existing password complexity check
    const passwordRegex = /^(?=.*[A-Za-z])(?=.*[^A-Za-z]).{7,}$/;
    if(!passwordRegex.test(password)) {
        isValid = false;
        passwordGroup.addClass('has-error');
        passwordGroup.find('.new-account-input')
                    .append('<div class="field-error">Password must be at least 7 characters and contain both letters and numbers</div>');
        $('.field-error').show();
    }

    if (!isValid) {
        $('#formValidationMsg').html("Please correct the highlighted fields");
    }

    return isValid;
}

// Add real-time validation for phone number formatting
$('#phoneNumber').on('input', function() {
    let number = $(this).val().replace(/[^\d]/g, '');
    if (number.length >= 10) {
        number = number.replace(/(\d{3})(\d{3})(\d{4})/, "($1) $2-$3");
    }
    $(this).val(number);
});
    
function loadingStart() {
    $('#loginBtn').prop("disabled",true);
    $('#loginBtn').html("Loading");
    $('.server_success').hide();
    $('.server_error').hide()
    $('.ultracamp_error').hide()
}

function loadingStop() {


    $('#loginBtn').html("Create Account");
    $('#loginBtn').prop("disabled",false);

}