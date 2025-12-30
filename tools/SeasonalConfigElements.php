<?php

/**
 * SeasonalConfigElements
 * config items for data base tables, seasonal values, camp pod sizes, and other variable elements
 * so that I don't have to hunt them down all over the code base
 * Built 4/13/2023 - adopted as the waitlist is upgraded to be camper queue
 *  * Updated for season variables on 12/14/23 (BN)
 * 
 * Migrated to Reservations API (Option 1)
 * Document Date: December 2025
 */

date_default_timezone_set('America/Los_Angeles');

require_once(__DIR__ . '/../db/conn.php');
require_once __DIR__ . '/../db/db-reservations.php';
class SeasonalConfigElements
{
    /* =========================================================
     * API CONFIG
     * ======================================================= */


    private const API_ENDPOINT =
    'https://reservations.cedarsprings.camp/api.php?action=season-config';

    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_FILE = 'csc_season_config.json';
    private const CACHE_DIR = 'cache';



    private $apiKey;

    /* =========================================================
     * LEGACY PUBLIC PROPERTIES (DO NOT RENAME)
     * ======================================================= */

    public $currentYear;
    public $friends_table;
    public $reservations;
    public $daily_reservations;
    public $offseason_reservations;

    public $registrationStartDate;
    public $rebistrationEndDate; // typo preserved for compatibility

    public $july4thSessionId;
    public $july4thDay;

    public $transportationCost;
    public $playPassDailyCost;
    public $sessionDepositAmount;

    public $dailyOptionTemplates = [];
    public $podSizes = [];

    // Static tables (unchanged year-to-year)
    public $campers = 'campers';
    public $camp_options = 'camp_options';
    public $summer_weeks = 'summer_weeks';
    public $accelerate_weeks = 'accelerate_weeks';
    public $waitlist = 'waitlist';
    public $report_summer = 'report_summer';
    public $changelog = 'changelog_summer';

    // Friend Finder
    public $conflictTable = 'friend_conflicts';
    public $exceptionsTable = 'friend_exceptions';
    public $ultracampTable = 'ultracamp_friend_requests';

    // Feature flags
    public $basecampConfirmationEmail = true;

    // Calculated
    public $numberOfWeeks;

    /* =========================================================
     * INTERNALS
     * ======================================================= */

    private $db;
    public $logger;

    /* =========================================================
     * CONSTRUCTOR
     * ======================================================= */

    public function __construct($logger = null)
    {

        $this->setLogger($logger);

        // Load API key securely
        $this->apiKey = "Y0uC@ntH@ndl3Th3Truth!"; //getenv('CSC_SEASON_CONFIG_API_KEY');

        if (empty($this->apiKey)) {
            PluginLogger::log('Missing CSC_SEASON_CONFIG_API_KEY');
            throw new Exception('Missing CSC_SEASON_CONFIG_API_KEY');
        }

        $config = $this->getCachedSeasonConfig();

        $this->mapApiConfig($config);

        $this->numberOfWeeks = $this->getNumberOfWeeks();
    }

    /* =========================================================
     * LOGGER
     * ======================================================= */

    private function setLogger($logger = null): void
    {
        $this->logger = is_object($logger) ? $logger : new ConfigDummyLogger();
    }

    /* =========================================================
     * API + CACHE
     * ======================================================= */

    private function getCachedSeasonConfig(): array
    {
        $cacheDir = __DIR__ . '/' . self::CACHE_DIR;

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cachePath = $cacheDir . '/' . self::CACHE_FILE;

        if (file_exists($cachePath)) {
            $cached = json_decode(file_get_contents($cachePath), true);

            if (
                is_array($cached) &&
                isset($cached['fetched_at'], $cached['data']) &&
                (time() - $cached['fetched_at']) < self::CACHE_TTL
            ) {
                PluginLogger::log('Season config loaded from plugin cache');
                return $cached['data'];
            }
        }

        $data = $this->fetchSeasonConfigFromApi();

        $written = file_put_contents(
            $cachePath,
            json_encode([
                'data' => $data,
                'fetched_at' => time()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );

        if ($written === false) {
            PluginLogger::log('ERROR:: Failed to write plugin cache file', [
                'path' => $cachePath
            ]);
        }

        return $data;
    }



    private function fetchSeasonConfigFromApi(): array
    {
        PluginLogger::log("fetchSeasonConfigFromApi:: API Calling ...... ");
        $ch = curl_init(self::API_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $this->apiKey,
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            PluginLogger::log("Season config API failed ({$httpCode})");
            throw new Exception("Season config API failed ({$httpCode})");
        }

        $json = json_decode($response, true);

        if (!$json || empty($json['success'])) {
            PluginLogger::log("Invalid season config API response");
            throw new Exception('Invalid season config API response');
        }

        return $json['data'];
    }

    /* =========================================================
     * API → LEGACY PROPERTY MAPPING
     * ======================================================= */

    private function mapApiConfig(array $c): void
    {
        $this->currentYear = $c['current_year'];
        $this->reservations = $c['reservations_table'];
        $this->daily_reservations = $c['daily_table'];
        $this->friends_table = $c['friends_table'];
        $this->offseason_reservations = $c['offseason_table'];

        $this->registrationStartDate = $c['registration_start_date'];
        $this->rebistrationEndDate = $c['registration_end_date'];

        $this->july4thSessionId = $c['july_4th_session_id'];
        $this->july4thDay = $c['july_4th_day'];

        $this->transportationCost = $c['transportation_cost'];
        $this->playPassDailyCost = $c['play_pass_daily_cost'];
        $this->sessionDepositAmount = $c['session_deposit_amount'];

        $this->dailyOptionTemplates = $c['daily_option_templates'] ?? [];
        $this->podSizes = $c['pod_size_overrides'] ?? [];

        PluginLogger::log('Season config mapped successfully', $c);
    }

    /* =========================================================
     * DATABASE-BASED VALUES (UNCHANGED)
     * ======================================================= */

    protected function getNumberOfWeeks(): int
    {
        if (empty($this->db)) {
            $this->db = new reservationsDb();
        }

        try {
            $result = $this->db->runBaseQuery(
                'SELECT week_num FROM ' . $this->summer_weeks .
                    ' ORDER BY week_num DESC LIMIT 1'
            );

            return !empty($result) ? (int)$result[0]['week_num'] : 12;
        } catch (Exception $e) {
            PluginLogger::log('warning:: Unable to fetch number of weeks');
            return 12;
        }
    }

    /* =========================================================
     * REGISTRATION WINDOW CHECK
     * ======================================================= */

    public function isRegistrationOpen(): bool
    {
        $now = new DateTime();

        return !(
            $now < new DateTime($this->registrationStartDate) ||
            $now > new DateTime($this->rebistrationEndDate)
        );
    }
}

/* =============================================================
 * DUMMY LOGGER
 * =========================================================== */

class ConfigDummyLogger
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
