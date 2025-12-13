<?php
require_once plugin_dir_path(__FILE__) . '/../../classes/PluginLogger.php';
/**
 * Extends the standard Ultracamp API Class with methods for interacting with 
 * the cart API and processing user authentication requests
 */


require_once plugin_dir_path(__FILE__) . '../../includes/ultracamp.php';

class CartAndUser extends UltracampModel
{

    protected $apiKey;
    protected $accountId;

    public $cartKey;
    public $cartContainer;
    public $finalCart;

    public $cartId;
    public $error_msg; // The Rest API needs error details from this library, but I don't want to modify methods, and break the queue - so, I'm going to store them in a property that the API can retrieve when needed.

    protected $hooks = [];

    function __construct($logger = null)
    {
        parent::__construct($logger);
    }


    /**
     * Add a hook to run after cart processing
     * 
     * @param string $hook_name Hook name
     * @param callable $callback Function to call
     * @return void
     */
    function add_action($hook_name, $callback)
    {
        if (!isset($this->hooks[$hook_name])) {
            $this->hooks[$hook_name] = [];
        }
        $this->hooks[$hook_name][] = $callback;
    }

    /**
     * Run hooks for a specific action
     * 
     * @param string $hook_name Hook name
     * @param mixed ...$args Arguments to pass to hook functions
     * @return void
     */
    function do_action($hook_name, ...$args)
    {
        if (isset($this->hooks[$hook_name]) && is_array($this->hooks[$hook_name])) {
            foreach ($this->hooks[$hook_name] as $callback) {
                call_user_func_array($callback, $args);
            }
        }
    }

    // the authentications call is very different than most of the other UC calls
    // it uses outbound headers for authentication (rather than the URL)
    // and it replies with both body content and header info that needs to be captured
    // returns an array with the API key as ['q'], ['account'] and ['contact']+first/last
    // It will accept a user account number, but an unspecified zero is also acceptable
    function authenticateUser($user, $pass, $account = 0)
    {
        $user = trim($user);
        $pass = filter_var(trim($pass), FILTER_SANITIZE_STRING);

        if (!filter_var($user) || !isset($pass)) {
            return array("StatusCode" => 400, "Message" => "CSC INTERNAL ERROR: Invalid email address or password submitted.");
        }

        $credentials = base64_encode($user . ':' . $pass);

        if (!$this->production) {
            PluginLogger::log("Debug ::" . "Attempting to authenticated UC user" . array('User' => $user, 'Password' => '--hidden--', 'Account' => $account, 'Hash' => $credentials));
        }

        $url = "https://rest.ultracamp.com/api/camps/107/accounts/$account/authenticate/credentials";

        $curl = curl_init();

        // Authentication:
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);

        // need specific headers for this reqeust
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'ultracamp-camp-api-key:7EJWNDKAMG496K9Q',
            'ultracamp-account-credentials:' . $credentials
        ));
        curl_setopt($curl, CURLOPT_URL, $url);

        // this function is called by curl for each header received
        curl_setopt(
            $curl,
            CURLOPT_HEADERFUNCTION,
            function ($curl, $header) use (&$headers) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) // ignore invalid headers
                    return $len;

                $headers[strtolower(trim($header[0]))][] = trim($header[1]);

                return $len;
            }
        );

        try {
            $curl_response = curl_exec($curl);
        } catch (Exception $e) {
            PluginLogger::log("Error:: Unable to process Ultracamp Login due to error: " . $e->getMessage());
            return false;
        }

        PluginLogger::log("Debug ::" . "UC Authenticate User API Time" . curl_getinfo($curl, CURLINFO_TOTAL_TIME));

        $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($responseCode == 401) {
            PluginLogger::log("Debug ::" . "Ultracamp responded with an unauthorized user code.");
        } else if ($responseCode < 200 || $responseCode >= 300) {
            PluginLogger::log("Error:: Ultracamp responded with an error when authenticating a user. Response Code: " . $responseCode);
            PluginLogger::log("Error:: User attempting to authenticate: " . $user);
        }

        $result = json_decode($curl_response, true);

        curl_close($curl);

        // send back to the caller the needed data to confirm and record the login results
        // if the authentication succeeded
        if (!empty($result['Authenticated']) && $result['Authenticated'] == true) {
            $data = $result;
            // snag the account api key so we don't need to re-authorize for a cart transaction
            $data['q'] = $headers['ultracamp-account-api-key'][0];
            $data['sso-token'] = $headers['ultracamp-token'][0];
            $this->apiKey = $data['q']; // store this in class variable as well so the cart functionality can reach it easily
            $this->accountId = $account;

            return $data;
        } else {
            PluginLogger::log("Debug ::" . "Ultracamp did not authenticate the user.");
            $this->apiKey = NULL;
        }

        // if there is anything other than a valid authentication result, return the result as it contains the error
        PluginLogger::log("Debug ::" . "Authentication Result" . $result);
        return $result;
    }

    function validateAccountKey($key, $account, $returnHeaders = false)
    {
        $account = trim($account);
        $key = urldecode(filter_var(trim($key), FILTER_SANITIZE_STRING));

        if (!isset($account) || !isset($key) || !is_numeric($account)) {
            return array("StatusCode" => 400, "Message" => "Invalid key or account number submitted to validateAccountKey().");
        }

        $url = "https://rest.ultracamp.com/api/camps/107/accounts/$account/authenticate/key";
        $headers = []; // holds the response headers, if we're collecting them
        $curl = curl_init();

        // add the headers, if requested. This was built 5/8/24 for the sso api page to access additional authentication details
        if ($returnHeaders) {
            // Collect returned headers
            curl_setopt(
                $curl,
                CURLOPT_HEADERFUNCTION,
                function ($curl, $header) use (&$headers) {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);
                    if (count($header) < 2) // ignore invalid headers
                        return $len;

                    $headers[strtolower(trim($header[0]))][] = trim($header[1]);

                    return $len;
                }
            );
        }

        // Authentication:
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        // need specific headers for this reqeust
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'ultracamp-account-api-key:' . $key
        ));
        curl_setopt($curl, CURLOPT_URL, $url);

        PluginLogger::log("Debug ::" . "Validating UC Account Information", array($account, $key));

        try {
            $curl_response = curl_exec($curl);
        } catch (Exception $e) {
            PluginLogger::log("Error:: Unable to validate Ultracamp Login due to error: " . $e->getMessage());
            return false;
        }
        PluginLogger::log("Debug ::" . "UC Validate Account Key API Time", curl_getinfo($curl, CURLINFO_TOTAL_TIME));

        $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // we don't log code 401 as that is expired credentials, and we handle that gracefully with the user
        if (($responseCode < 200 || $responseCode >= 300) && $responseCode != 401) {
            PluginLogger::log("Error:: Ultracamp responded with an error when validating a user account key. Response Code: " . $responseCode);
            PluginLogger::log("Error json_decode::" . json_decode($curl_response, true));
        }

        $result = json_decode($curl_response, true);
        if ($returnHeaders) {
            $result['headers'] = $headers; // Append headers to result if requested
        }

        curl_close($curl);
        PluginLogger::log("Debug ::" . "UC Model validate result" . $result);

        // update the class key if needed
        if ($this->apiKey != $key) {
            $this->apiKey = $key;
            $this->accountId = $account;
        }

        return $result;
    }

    // create a shopping cart - the key must already be set in the class through a login or validation method call.
    // in order to provide a bit of retry logic, the method can recoursively call itself once, upon failure.
    // Should the cart fail due to an ultracamp error, the method will wait 5 seconds and attempt a single retry.
    function createCart($retry = false)
    {

        // ensure that there is an User API Key available
        if (empty($this->apiKey)) {
            PluginLogger::log("Error:: Unable to create a cart due to a missing api key. Ensure that a key is set, or user credentials are validated using validateAccountKey().");
            return false;
        }

        $url = "https://rest.ultracamp.com/api/camps/107/accounts/" . $this->accountId . "/carts/create";

        $curl = curl_init();

        // Authentication:
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        // need specific headers for this reqeust
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'ultracamp-account-api-key:' . $this->apiKey,
            'Content-Length: 0',
            'Accept: application/json'
        ));

        curl_setopt($curl, CURLOPT_URL, $url);

        // Carts use a PUT method (as opposed to GET or POST)
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");

        try {
            $curl_response = curl_exec($curl);
        } catch (Exception $e) {
            PluginLogger::log("Error:: Unable to create an Ultracamp Cart due to a curl error: " . $e->getMessage());
            return false;
        }

        PluginLogger::log("Debug ::" . "UC Create Cart API Time" . curl_getinfo($curl, CURLINFO_TOTAL_TIME));
        PluginLogger::log("Debug ::" . "UC Account API Key: " . $this->apiKey);

        $result = json_decode($curl_response, true);
        $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($responseCode < 200 || $responseCode >= 300) {
            PluginLogger::log("Error:: Failed to create a new UC Cart. Response Code: " . $responseCode);

            if (is_array($result)) {
                PluginLogger::log("Error:: UC Response", $result);
            } else {
                PluginLogger::log("Error:: UC Response" . $result);
            }

            PluginLogger::log("Error:: URL: " . $url);

            // check to see if retry is true - if it is not, try one more time, otherwise fail
            if ($retry) {
                PluginLogger::log("Warning:: Create Cart Failed on retry.");
                return false;
            } else {
                PluginLogger::log("Warning:: Create cart will sleep for 5 seconds and then retry.");
                sleep(5);
                $this->createCart(true);
            }
        } else {
            PluginLogger::log("Debug ::" . "UC create cart result" . $result);
        }


        // Collect returned headers
        curl_setopt(
            $curl,
            CURLOPT_HEADERFUNCTION,
            function ($curl, $header) use (&$headers) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) // ignore invalid headers
                    return $len;

                $headers[strtolower(trim($header[0]))][] = trim($header[1]);

                return $len;
            }
        );


        curl_close($curl);

        $this->cartId = $result['CartId'];
        $this->cartKey = $result['CartKey'];
        $this->cartContainer = array("CartId" => $result['CartId'], "CartKey" => $result['CartKey']);

        return $this->cartId;
    }

    function addReservation($session, $person, $account, $options, $discounts = [])
    {
        // ensure that we have a cart to work with
        if (empty($this->cartId)) {
            // Jan 4th 2024 - I've added logic to capture if the cart isn't created, as the error logs show that Ultracamp has become unreliable in this matter
            // This is a part of the functionality to save errors into the queue when they happen, but I don't want to indicate every place that I touch
            // for that logic, so the note is going here.

            // the method returns false on error, and the cart ID on success - the method has built in retry logic as well
            if (!$this->createCart()) {
                PluginLogger::log("Error:: Cart creation failed - returning to the customer in an error state.");
                return false;
            }
        }



        $reservation = (object) array(
            'CartId'      => $this->cartId,
            'CartKey'     => $this->cartKey,
            'IdSession'   => $session,
            'IdPerson'    => (int)$person,
            'IdAccount'   => (int)$account,
            "Options"     => $options,
            "CustomDiscounts" => $discounts
        );

        $this->cartContainer['Reservation'] = $reservation;

        // Apparently you can only upload a single session reservation at a time, so we'll do so here
        $result = $this->postReservation();

        // Get reservation ID if available
        if (
            isset($this->cartContainer['Response']) &&
            isset($this->cartContainer['Response']->ReservationId)
        ) {
            $reservationId = $this->cartContainer['Response']->ReservationId;

            // Run the hook with the reservation ID
            $this->do_action('after_ultracamp_cart_process', $this, [$reservationId]);
        }

        return PluginLogger::log("Debug ::" . "Reservation Response Code: $result");
    }

    function postReservation()
    {
        PluginLogger::clear();
        // ensure that there is an User API Key available
        if (empty($this->apiKey)) {
            PluginLogger::log("Error:: Unable to create a cart due to a missing api key. Ensure that a key is set, or user credentials are validated using validateAccountKey().");
            return false;
        }

        $url = "https://rest.ultracamp.com/api/camps/107/accounts/" . $this->accountId . "/carts/" . $this->cartId . "/add/session/reservation";

        $curl = curl_init();

        PluginLogger::log("apiKey:: " . $this->apiKey);
        // need specific headers for this reqeust
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'ultracamp-account-api-key:' . $this->apiKey,
            'Content-Type:application/json'
        ));

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($this->cartContainer));

        $jsonPayload = json_encode($this->cartContainer);
        PluginLogger::log("PAYLOAD TO ULTRACAMP: " . $jsonPayload);

        try {
            $curl_response = curl_exec($curl);
        } catch (Exception $e) {
            PluginLogger::log("Error:: Unable to add a reservaion to the cart due to error: " . $e->getMessage());
            return false;
        }

        $result = json_decode($curl_response, true);
        $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        PluginLogger::log("Debug ::" . "UC Add Reservation API Time" . curl_getinfo($curl, CURLINFO_TOTAL_TIME));

        if ($responseCode < 200 || $responseCode >= 300) {
            PluginLogger::log("Error:: Failed to add session reservation to cart. Response Code: " . $responseCode);

            if (is_array($result)) {
                PluginLogger::log("Error:: UC Response:: " . $result);
            } else {
                PluginLogger::log("Error:: UC Response:: " . $result);
            }

            PluginLogger::log("Error:: URL: " . $url);
            PluginLogger::log("Error:: Reservation Attempted: " . json_encode($this->cartContainer['Reservation']));
            PluginLogger::log("Error:: Cart: " . json_encode($this->cartContainer));

            curl_close($curl);

            return false;
        } else {
            PluginLogger::log("Info:: " . "Reservation Attempted: " . json_encode($this->cartContainer['Reservation']));
            //PluginLogger::log("Debug ::"."UC add reservation result", $result);
        }

        curl_close($curl);

        return $responseCode;
    }
    /**
     * Close out a cart and return the Ultracamp URL for the customer to use to pay
     */
    function completeCart()
    {
        if (empty($this->cartId)) {
            PluginLogger::log("Error:: completeCart() called without an active cart to complete.");
            return false;
        }

        // Setup the cURL parameters
        $url = "https://rest.ultracamp.com/api/camps/107/accounts/" . $this->accountId . "/carts/" . $this->cartId . "/complete/" . $this->cartKey;
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'ultracamp-account-api-key:' . $this->apiKey,
            'Content-Type:application/json'
        ));

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');

        try {
            $curl_response = curl_exec($curl);
        } catch (Exception $e) {
            PluginLogger::log("Error:: Unable to complete an Ultracamp Cart due to error: " . $e->getMessage());
            return false;
        }

        $result = json_decode($curl_response, true);
        $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        PluginLogger::log("Debug ::" . "UC Complete Cart Time" . curl_getinfo($curl, CURLINFO_TOTAL_TIME));

        if ($responseCode < 200 || $responseCode >= 300) {
            PluginLogger::log("Error:: Failed to complete the cart.");
            PluginLogger::log("Error:: UC Response: " . $result);
            PluginLogger::log("Error:: URL: " . $url);

            curl_close($curl);

            return false;
        } else {
            // PluginLogger::log("Debug ::"."UC complete cart result", $result);
        }

        curl_close($curl);

        return $result;
    }

    /** Takes an array of account information (contact, email, password and the like)
     * and creates an Ultracamp account with it. It will return the account number on success
     * with the exception of the phone number fields, where one is required, but not both, all fields are required
     * email: This is a mandatory field used as the 'UserName' in the account model and for setting the primary contact's email. The function checks for its presence at the beginning and logs an error if it's missing.
     * password: This is another mandatory field used in the account model. Like email, the function checks for its presence upfront.
     * address: This field contributes to the address details in the account model. If present, it sets the 'Street' within the 'Address' structure.
     * city: An field that, if provided, sets the 'City' within the 'Address' structure.
     * state: An field used to set the 'State' in the 'Address' structure.
     * zip: This field sets the 'Zip' code in the 'Address' structure.
     * cellPhone: An field used to set the 'Cell' and 'Primary' phone numbers in the 'Phone' structure, after being cleansed by the cleansePhone method.
     * primaryPhone: An field used to set the 'Primary' phone number in the 'Phone' structure, after cleansing.
     * firstName: Along with lastName, this field contributes to the primary contact's name in the account model.
     * lastName: This works in tandem with firstName to set the primary contact's name.
     */

    function createAccount($accountInfo)
    {
        $this->error_msg = '';
        if (empty($accountInfo['email']) || empty($accountInfo['password'])) {
            PluginLogger::log("Error:: Unable to create an account without an email address and password.");
            $this->error_msg = "Unable to create an account without an email address and password.";
            return array('status' => 'error', 'message' => $this->error_msg);
        }

        // Validate that at least one phone number is provided
        if (empty($accountInfo['cellPhone']) && empty($accountInfo['primaryPhone'])) {
            PluginLogger::log("Error:: Unable to create an account without a phone number.");
            $this->error_msg = "Unable to create an account without a phone number.";
            return array('status' => 'error', 'message' => $this->error_msg);
        }

        // PluginLogger::log("Debug ::"."Account Creation Input", $accountInfo);

        // build the most basic of models
        $model = array(
            'IdCamp' => 107,
            'UserName' => $accountInfo['email'],
            'Password' => $accountInfo['password']
        );

        // add any address info, if applicable
        if (!empty($accountInfo['address'])) {
            $address['Street'] = $accountInfo['address'];
            $address['Country'] = 'US';

            if (!empty($accountInfo['city'])) {
                $address['City'] = $accountInfo['city'];
            }
            if (!empty($accountInfo['state'])) {
                $address['State'] = $accountInfo['state'];
            }
            if (!empty($accountInfo['zip'])) {
                $address['Zip'] = $accountInfo['zip'];
            }
        }

        // Phone number handling
        $cellPhone = isset($accountInfo['cellPhone']) ? $this->cleansePhone($accountInfo['cellPhone']) : '';
        $primaryPhone = isset($accountInfo['primaryPhone']) ? $this->cleansePhone($accountInfo['primaryPhone']) : '';

        // due to an error in the api 1/18/25 where the home phone number caused an error if missing, we'll include that too
        $phone['Home'] = isset($accountInfo['homePhone']) ? $this->cleansePhone($accountInfo['homePhone']) : $cellPhone;

        if (!empty($cellPhone)) {
            $phone['Cell'] = $cellPhone;
            if (empty($primaryPhone)) {
                $phone['Primary'] = $cellPhone;
            }
        }
        if (!empty($primaryPhone)) {
            $phone['Primary'] = $primaryPhone;
        }

        // add the primary contact
        if (!empty($accountInfo['firstName']) && !empty($accountInfo['lastName'])) {
            $primary['Name']['First'] = $accountInfo['firstName'];
            $primary['Name']['Last'] = $accountInfo['lastName'];
            if (!empty($address)) {
                $primary['Address'] = $address;
            }
            if (!empty($phone)) {
                $primary['Phone'] = $phone;
            }
            $primary['Email'] = $accountInfo['email'];
        }

        // pull the whole thing together into a single array
        if (!empty($address)) {
            $model['Address'] = $address;
        }
        if (!empty($phone)) {
            $model['Phone'] = $phone;
        }
        if (!empty($primary)) {
            $model['PrimaryContact'] = $primary;
        }

        PluginLogger::log("Debug ::" . "New Account Model:" . json_encode($model));

        // Run to Ultracamp with this information
        $url = "https://rest.ultracamp.com/api/camps/107/accounts/create";
        $curl = curl_init();

        // need specific headers for this reqeust
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'ultracamp-api-key: 7EJWNDKAMG496K9Q',
            'Content-Type:application/json'
        ));

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($model));

        try {
            $curl_response = curl_exec($curl);
        } catch (Exception $e) {
            PluginLogger::log("Error:: Unable to create an Ultracamp Account due to error: " . $e->getMessage());
            $this->error_msg = "Unable to create an Ultracamp Account due to error: " . $e->getMessage();
            return array('status' => 'error', 'message' => $this->error_msg);
        }

        $result = json_decode($curl_response, true);
        $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        PluginLogger::log("Debug ::" . "UC Create Account API Time" . curl_getinfo($curl, CURLINFO_TOTAL_TIME));
        curl_close($curl);

        // 4/10/2024 - I've udpated the full response as the Rest API needs this library, and it wasn't working well with the Camper Queue anyway

        // let's handle error responses from Ultracamp
        if ($responseCode < 200 || $responseCode >= 300) {

            // if the respons isn't an array, something unexpected happened, let's capture that as an error first, so we don't have to check for it at each subsequent step
            if (!is_array($result)) {
                $this->error_msg  = "Ultracamp did not respond with an expected array when attempting to create an new account. HTTP Response Code: " . $responseCode . " and Response message: " . $result;
                PluginLogger::log("Error:: " . $this->error_msg);
                return array('status' => 'error', 'message' => $this->error_msg);
            }

            // the most common error is that there is already an account using this email address
            if ($result[0] == 'Account -- UserName -- Already Exists.') {
                $this->error_msg = "Duplicate account creation attempted for address " . $accountInfo['email'];
                PluginLogger::log("Info:: " . $this->error_msg);
                return array('status' => 'error', 'message' => 'User name already exists', 'responseCode' => $responseCode);
            }
            if ($result[0] == 'Primary Contact -- Email -- Duplicate -- Account With Email Already Exists.') {
                $this->error_msg = "Duplicate account creation attempted for address " . $accountInfo['email'];
                PluginLogger::log("Info:: " . $this->error_msg);
                return array('status' => 'error', 'message' => 'User name already exists', 'responseCode' => $responseCode);
            }

            // Beyond a duplicate account error, we don't capture anything specfic at this point, so just return it as it comes

            $this->error_msg  = "UC Response: " . implode('; ', $result);
            PluginLogger::log("Error:: UC Response", $result);

            return array('status' => 'error', 'message' => $this->error_msg, 'responseCode' => $responseCode);
        } else {
            PluginLogger::log("Debug ::" . "New Account Created: " . json_encode($result));
        }

        return array('status' => 'success', 'message' => $result);
    }

    /**
     * Takes an array of biographical information and creates a new user in the account
     * The user is assumed to be a camper, and so contact information is copied from the primary on the account
     * Must have firstName, lastName, camperDob, account and gender in the array
     */
    function addCamperToAccount($camper)
    {

        // build the top portion of the camper model
        $model = array(
            'IdAccount' => $camper['account'],
            "IsPrimaryContact" => false,
            "IsSecondaryContact" => false,
            "IsEnabled" => true,
            'Gender' => $camper['gender'],
            'Birthdate' => date("Y-m-d", strtotime($camper['camperDob'])),
            "Name" => array(
                "First" => $camper['firstName'],
                "Last"  => $camper['lastName']
            )
        );

        // Ultracamp requires address and phone number information
        // we're assuming this is a camper, so we'll run to UC, get the primary's info and then just send it back as the camper's too
        $primaryPerson = $this->getPrimaryAccountPerson($camper['account']);

        $model['Email'] = $primaryPerson->Email;

        $model['Address'] = array(
            'Street' => $primaryPerson->Address,
            'City' => $primaryPerson->City,
            'State' => $primaryPerson->State,
            'Zip' => $primaryPerson->ZipCode,
            'Country' => $primaryPerson->Country
        );

        // they require the phone number as digits only, but send it formatted - so I have to nuke the formating
        $model['Phone'] = array(
            'Primary' => preg_replace('/[^0-9]/', '', $primaryPerson->PrimaryPhoneNumber)
        );

        PluginLogger::log("Debug ::" . "New Person Model:" . json_encode($model));

        // Run to Ultracamp with this information
        $url = "https://rest.ultracamp.com/api/camps/107/accounts/" . $camper['account'] . '/people/create';
        $curl = curl_init();

        // need specific headers for this reqeust
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'ultracamp-api-key: 7EJWNDKAMG496K9Q',
            'Content-Type:application/json'
        ));

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($model));

        try {
            $curl_response = curl_exec($curl);
        } catch (Exception $e) {
            PluginLogger::log("Error:: Unable to create an Ultracamp Person due to error: " . $e->getMessage());
            return false;
        }

        $result = json_decode($curl_response, true);
        $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        PluginLogger::log("Debug ::" . "UC Create Person API Time" . curl_getinfo($curl, CURLINFO_TOTAL_TIME));

        if ($responseCode < 200 || $responseCode >= 300) {
            PluginLogger::log("Error:: Failed to create a new person in Ultracamp. Response Code: " . $responseCode);

            if (is_array($result)) {
                PluginLogger::log("Error:: UC Response", $result);
            } else {
                PluginLogger::log("Error:: UC Response" . $result);
            }

            PluginLogger::log("Error:: URL: " . $url);
            curl_close($curl);

            return false;
        } else {
            PluginLogger::log("Debug ::" . "New Person Created in Ultracamp: " . json_encode($result));
        }

        curl_close($curl);



        return $result;
    }

    // quick function to cleanse phone numbers
    function cleansePhone($str)
    {
        $digits = preg_replace('/\D/', '', $str); // Remove all non-digits
        return (strlen($digits) == 10) ? $digits : null; // Check if the result is 10 digits long
    }
}
