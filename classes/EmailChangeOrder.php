<?php

/**
 * 3/24/23 - More CQ BN
 * Takes the information from ActiveManager and sends the office an email letting them know what needs to be updated
 */

date_default_timezone_set('America/Los_Angeles');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// require_once(__DIR__ . '/../../../../vendor/autoload.php');


class EmailChangeOrder
{

    public $logger;
    protected $db;
    protected $tables;

    protected $uc;
    protected $production;
    protected $CQModel;

    function __construct($logger = null)
    {
        $this->setLogger($logger);

        require_once __DIR__ . '/../classes/config.php';
        require_once __DIR__ . '/../classes/CQModel.php';

        require_once plugin_dir_path(__FILE__) . '../includes/db-reservations.php';
        require_once plugin_dir_path(__FILE__) . '../includes/ultracamp.php';


        $this->db = new reservationsDb();
        $this->tables = new CQConfig;

        $this->db->setLogger($this->logger);
        $this->uc = new UltracampModel($this->logger);

        $this->CQModel = new CQModel($this->logger);

        $host = gethostname();
        if (isset($host) && $host == 'host.cedarsprings.camp') {
            $this->production = true;
        }

        if ($this->production) {
            PluginLogger::log("Operating in production!");
        } else {
            PluginLogger::log("Operating in Dev Mode");
        }
    }

    function createChangeOrder($entry, $reservation, $pod, $additionalElements = [])
    {
        PluginLogger::log("Starting Change Order", compact('entry', 'reservation', 'pod'));

        if (is_array($entry)) {
            $e = $entry;
        } else {
            // action-camper-camp-week
            $e = explode('-', $entry);
        }

        if ($e[0] != 'C') {
            // not an active entry element, we're not going to do anything with it
            return false;
        }

        // Build the camper name from the model's result
        $camperName = $this->CQModel->getCamperName($e[1]);
        $name = $camperName['FirstName'] . ' ' . $camperName['LastName'];

        PluginLogger::log("camper_name", $name ?? 'N/A');
        PluginLogger::log("session", isset($e[3]) ? $this->CQModel->getWeek($e[3]) : 'N/A');
        PluginLogger::log("old_camp", $reservation['camp_name'] ?? 'N/A');
        PluginLogger::log("new_camp", isset($e[2]) ? $this->CQModel->getCamp($e[2]) : 'N/A');
        PluginLogger::log("pod", isset($pod['name']) ? $pod['name'] : 'N/A');
        PluginLogger::log("reservation_ucid", $reservation['reservation_ucid'] ?? 'N/A');
        PluginLogger::log("Additional Elements", $additionalElements);

        // Depending on the type of change (camp change, add campfire, add camp to campfire), we use a different template
        // 1) Check to see if we're adding a camp to a campfire night
        if (isset($e[2]) && !isset($reservation['camp_name'])) {
            $email_template = file_get_contents(__DIR__ . "/../queue/templates/addCampToCampfireNights.php");

            // This email has a lot of additional variables, managed through the additionalElements array
            $email_template = str_replace('{{ pod }}', $additionalElements['pod']['name'] ?? '', $email_template);
            $email_template = str_replace('{{ transportation }}', $additionalElements['transportation']['name'] ?? '', $email_template);
            $email_template = str_replace('{{ bus }}', $additionalElements['bus']['name'] ?? '', $email_template);
            $email_template = str_replace(
                '{{ extCare }}',
                [
                    'F' => 'Full Day',
                    'A' => 'Morning',
                    'P' => 'Evening'
                ][$additionalElements['extCare']] ?? '',
                $email_template
            );
            // reformat lunch as a string                                
            if (in_array('selectall', $additionalElements['lunch'])) {
                $hotlunch = "All Week";
            } else {
                // Capitalize the first letter of each weekday and combine into a string
                $hotlunch = implode(
                    ", ",
                    array_map('ucfirst', $additionalElements['lunch'])
                );
            }
            $email_template = str_replace('{{ hotLunch }}', $hotlunch ?? '', $email_template);
        }
        // 2) Check to see if we're adding a campfire night to a camp
        elseif ($e[2] == 73523 && $reservation['overnight_choice'] == null) {
            $email_template = file_get_contents(__DIR__ . "/../queue/templates/addCampfireNights.php");
        }
        // Otherwise, assume that we're changing camps
        else {
            $email_template = file_get_contents(__DIR__ . "/../queue/templates/changeOrderTemplate.php");
        }

        $email_template = str_replace('{{ camper_name }}', $name ?? '', $email_template);
        $email_template = str_replace('{{ session }}', isset($e[3]) ? $this->CQModel->getWeek($e[3]) : '', $email_template);
        $email_template = str_replace('{{ old_camp }}', $reservation['camp_name'] ?? '', $email_template);
        $email_template = str_replace('{{ new_camp }}', isset($e[2]) ? $this->CQModel->getCamp($e[2]) : '', $email_template);
        $email_template = str_replace('{{ pod }}', isset($pod['name']) ? $pod['name'] : '', $email_template);
        $email_template = str_replace('{{ reservation_ucid }}', $reservation['reservation_ucid'] ?? '', $email_template);

        if ($this->sendEmail($email_template)) {
            PluginLogger::log("Change Order Email succesfully sent.");
            return true;
        }
    }


    function sendEmail($body)
    {

        // if we're in dev mode, there is a flag added to the email
        if ($this->production === true) {
            $devFlag = '';
        } else {
            $devFlag = '<h1 style="margin-top:0;margin-bottom:16px;font-size:26px;line-height:32px;font-weight:bold;letter-spacing:-0.02em;color:#c70039;">DEVELOPMENT MODE</h1>';
        }

        $body = str_replace('{{ development }}', $devFlag, $body);

        // send the mailing list and reservation to the ticket builder for each address in the mailing list

        // Load email configuration
        $config = []; // require_once(__DIR__ . '/../../../../../website-email-credentials.php');

        // build the email object
        $mail = new PHPMailer;

        $mail->IsSMTP();                                            // Set mailer to use SMTP
        $mail->Host         = $config['smtp_host'];                 // Specify main and backup server
        $mail->Port         = $config['smtp_port'];                 // Set the SMTP port
        $mail->SMTPAuth     = true;                                 // Enable SMTP authentication
        $mail->Username     = $config['smtp_username'];             // SMTP username
        $mail->Password     = $config['smtp_password'];             // SMTP password
        $mail->AddReplyTo('alphageek@cedarsprings.camp', 'The Digital Overlord');
        $mail->addBcc('ben.n+waitlist@cedarsprings.camp');

        if ($this->production === true) {
            $mail->AddAddress('camps@cedarsprings.camp');
        } else {
            // dev mode, send to myself
            $mail->AddAddress('ben.n@cedarsprings.camp');
        }

        $mail->SMTPDebug    = 0;
        $mail->From         = 'alphageek@cedarsprings.camp';
        $mail->IsHTML(true);                                        // Set email format to HTML
        $mail->FromName     = 'The Digital Overlord';

        $mail->Subject      = "Camper Queue: Reservation Change Order";
        $mail->Body         = $body;

        //if(false) {
        if (!$mail->Send()) {
            $this->logger->error("Unable to send the change order email: " . $mail->ErrorInfo);
            return false;
        }

        return true;
    }

    /**
     * Create a change order email for Play Pass modifications
     * 
     * @param array $originalData Original registration data
     * @param array $newData New registration data with modifications
     * @param array $camperInfo Camper information (name, etc.)
     * @return bool Success status
     */
    function createPlayPassChangeOrder($originalData, $newData, $camperInfo)
    {


        // Load the Play Pass change order template
        $email_template = file_get_contents(__DIR__ . "/../queue/templates/playPassChangeOrderTemplate.php");

        // Prepare data for the template
        $camperName = $camperInfo['FirstName'] . ' ' . $camperInfo['LastName'];
        $weekName = $this->CQModel->getWeek($newData['week']);

        // Format days lists
        $originalDays = $this->formatDaysList($originalData['days']);
        $newDays = $this->formatDaysList($newData['days']);

        // Format lunch lists
        $originalLunch = $this->formatDaysList($originalData['lunch'] ?? []);
        $newLunch = $this->formatDaysList($newData['lunch'] ?? []);

        // Format extended care
        $originalMorningCare = $this->formatDaysList($originalData['morning_care'] ?? []);
        $newMorningCare = $this->formatDaysList($newData['morning_care'] ?? []);
        $originalAfternoonCare = $this->formatDaysList($originalData['afternoon_care'] ?? []);
        $newAfternoonCare = $this->formatDaysList($newData['afternoon_care'] ?? []);

        // Replace template variables
        $email_template = str_replace('{{ camper_name }}', $camperName, $email_template);
        $email_template = str_replace('{{ week }}', $weekName, $email_template);
        $email_template = str_replace('{{ original_days }}', $originalDays, $email_template);
        $email_template = str_replace('{{ new_days }}', $newDays, $email_template);
        $email_template = str_replace('{{ original_lunch }}', $originalLunch, $email_template);
        $email_template = str_replace('{{ new_lunch }}', $newLunch, $email_template);
        $email_template = str_replace('{{ original_morning_care }}', $originalMorningCare, $email_template);
        $email_template = str_replace('{{ new_morning_care }}', $newMorningCare, $email_template);
        $email_template = str_replace('{{ original_afternoon_care }}', $originalAfternoonCare, $email_template);
        $email_template = str_replace('{{ new_afternoon_care }}', $newAfternoonCare, $email_template);
        $email_template = str_replace('{{ original_transportation }}', $originalData['transportation_window'], $email_template);
        $email_template = str_replace('{{ new_transportation }}', $newData['transportation_window'], $email_template);
        $email_template = str_replace('{{ reservation_ucid }}', $originalData['reservation_ucid'] ?? '', $email_template);

        // Send the email
        if ($this->sendEmail($email_template)) {
            PluginLogger::log("Play Pass Change Order Email successfully sent.");
            return true;
        }

        return false;
    }

    /**
     * Format a list of days into a human-readable string
     * 
     * @param array $days Array of day numbers
     * @return string Formatted list of days
     */
    private function formatDaysList($days)
    {
        if (empty($days)) {
            return 'None';
        }

        $dayNames = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday'
        ];

        $formattedDays = [];
        foreach ($days as $day) {
            if (isset($dayNames[$day])) {
                $formattedDays[] = $dayNames[$day];
            }
        }

        return implode(', ', $formattedDays);
    }

    // if the logger is coming in, use it, otherwise create a dummy logger so we don't error out when we attempt to call it
    function setLogger($logger = null)
    {

        if (is_object($logger)) {
            $this->logger = $logger;
            return true;
        }

        $this->logger = new EmailChangeOrderDummyLogger();
    }
}

class EmailChangeOrderDummyLogger
{

    function d_bug($arg1, $arg2 = null)
    {
        return true;
    }
    function debug($arg1, $arg2 = null)
    {
        return true;
    }
    function info($arg1, $arg2 = null)
    {
        return true;
    }
    function warning($arg1, $arg2 = null)
    {
        return true;
    }
    function error($arg1, $arg2 = null)
    {
        throw new Exception($arg1);
        return true;
    }
}
