<?php

namespace Src\Common;

use Exception;

/**
 * Class AppContext
 *
 * Manages state, authentication checks, session attributes, and cached profile details
 * for the currently logged-in user session.
 */
class AppContext
{
    private $dataFields = [
        'user_id'             => 'user_id',
        'system_role'         => 'system_role',
        'browser_fingerprint' => 'browser_fingerprint',
    ];

    protected $db;
    protected $session;
    protected $dataHelper = null;

    protected $userData = null;
    protected $user_id = null;
    protected $system_role = null;
    protected $browser_fingerprint = null;
    protected $token_id = null;
    protected $device = null;
    protected $ip = null;
    protected $user_role = null;
    protected $user_role_id = null;

    /**
     * AppContext Constructor.
     *
     * @param \mysqli|null    $db_connect    Database connection.
     * @param object|null     $session_class Active session handling object.
     * @param DataHelper|null $dataHelper    Injected DataHelper instance.
     */
    public function __construct($db_connect = null, $session_class = null, ?DataHelper $dataHelper = null)
    {
        $this->db = $db_connect;
        $this->session = $session_class;

        // Safely assign or instantiate DataHelper
        if ($dataHelper !== null) {
            $this->dataHelper = $dataHelper;
        } elseif (class_exists('\Src\Common\DataHelper')) {
            $this->dataHelper = new \Src\Common\DataHelper($db_connect);
        }

        // Populate object properties from active session
        $allSessionData = (is_object($session_class) && method_exists($session_class, 'getAll'))
            ? ($session_class->getAll() ?? [])
            : [];

        if (is_array($allSessionData)) {
            foreach ($allSessionData as $key => $value) {
                if ($value !== null && $value !== '') {
                    $this->$key = $value;
                }
            }
        }

        // Ensure key authentication fields are initialized
        if (!empty($this->dataFields) && is_array($this->dataFields)) {
            foreach ($this->dataFields as $sessionKey => $variable) {
                if (empty($this->$variable)) {
                    $temp = (is_object($session_class) && method_exists($session_class, 'getValue'))
                        ? $session_class->getValue($sessionKey)
                        : ($_SESSION[$sessionKey] ?? null);

                    if ($temp !== null && $temp !== '') {
                        $this->$variable = $temp;
                    }
                }
            }
        }

        // Automatically fetch database user details if userID exists
        if (!empty($this->user_id) && method_exists($this, 'fetchAllUserData')) {
            $this->fetchAllUserData($this->user_id);
        }
    }

    /**
     * Magic getter fallback to lazily retrieve property values from session storage.
     *
     * @param string $name Property key name.
     * @return mixed Value or null if not found.
     */
    public function __get($name)
    {
        if (isset($this->$name)) {
            return $this->$name;
        }

        if (is_object($this->session) && method_exists($this->session, 'getValue')) {
            $sessionValue = $this->session->getValue($name);
            if ($sessionValue !== null && $sessionValue !== '') {
                $this->$name = $sessionValue;
                return $this->$name;
            }
        }

        return null;
    }

    /**
     * Gets an internal object property or returns an empty string fallback.
     */
    public function get($key)
    {
        return $this->$key ?? '';
    }

    // =======================================================================================================
    // AUTHENTICATION LAYER
    // =======================================================================================================

    /**
     * Verifies if the current request session is authenticated.
     */
    public function isLoggedIn(): bool
    {
        return !empty($this->user_id) && !empty($this->system_role);
    }

    /**
     * Enforces page-level authentication requirement, redirecting unauthenticated users.
     */
    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            if (is_object($this->session) && method_exists($this->session, 'setValue')) {
                $this->session->setValue('msg_error', 'Access denied. Please login first.');
            }
            $baseUrl = defined('BASE_URL') ? BASE_URL : '/';
            header("Location: " . $baseUrl);
            exit();
        }
    }

    // =======================================================================================================
    // CACHED PROFILE DATA TIER
    // =======================================================================================================

    /**
     * Queries database to pre-fetch profile, login credentials, and privilege options into cache memory.
     *
     * @param mixed $user_id Logged-in user identifier.
     * @throws Exception On database query failures.
     * @return void
     */
    private function fetchAllUserData($user_id): void
    {
        if (!is_numeric($user_id)) {
            return;
        }

        $query = "SELECT tbl_user.*, tbl_login.*, tbl_access.* 
                  FROM users AS tbl_user 
                  LEFT JOIN login AS tbl_login ON tbl_login.user_id = tbl_user.id 
                  LEFT JOIN system_access AS tbl_access ON tbl_access.user_id = tbl_user.id 
                  WHERE tbl_user.id = ? 
                  LIMIT 1";

        if ($stmt = mysqli_prepare($this->db, $query)) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($data = mysqli_fetch_assoc($result)) {
                $this->userData = $data;
            }
            mysqli_stmt_close($stmt);
        } else {
            throw new Exception("ApplicationContextDatabaseFailure: " . mysqli_error($this->db));
        }
    }

    // Get API Data
    public function get_token_id()
    {
        return $this->token_id;
    }

    public function get_device()
    {
        return $this->device;
    }

    public function get_ip()
    {
        return $this->ip;
    }

    public function get_information(): ?array
    {
        return $this->userData;
    }

    public function get_user_id()
    {
        return $this->user_id;
    }

    public function get_user_role(): string
    {
        return $this->user_role ?? '';
    }

    public function get_system_role(): string
    {
        return $this->system_role ?? '';
    }

    public function get_user_role_id(): int
    {
        return (int)($this->user_role_id ?? 0);
    }

    public function get_fingerprint()
    {
        return $this->browser_fingerprint;
    }

    public function get_email_address(): string
    {
        return $this->userData['email'] ?? '';
    }

    public function get_recovery_email(): string
    {
        return $this->userData['recovery_email'] ?? '';
    }

    public function get_username(): string
    {
        return $this->userData['username'] ?? '';
    }

    public function get_photo(): string
    {
        return $this->userData['profile_pic'] ?? '';
    }

    public function get_initials(): string
    {
        $firstName = $this->userData['first_name'] ?? '';
        $lastName = $this->userData['last_name'] ?? '';
        
        return $this->dataHelper ? $this->dataHelper->getNameInitials($firstName . " " . $lastName) : '';
    }

    /**
     * Formats full name string in natural standard ordering (First Middle Last Suffix).
     */
    public function get_full_name(): string
    {
        if (!$this->userData || !$this->dataHelper) {
            return "";
        }

        return $this->dataHelper->formatFullName(
            $this->dataHelper->toProperCase($this->userData['first_name'] ?? ''),
            $this->dataHelper->toProperCase($this->userData['middle_name'] ?? ''),
            $this->dataHelper->toProperCase($this->userData['last_name'] ?? ''),
            $this->dataHelper->toProperCase($this->userData['suffix'] ?? '')
        );
    }

    /**
     * Formats name string in official administrative order (LAST, FIRST M. SUFFIX).
     */
    public function getOfficialName(): string
    {
        if (!$this->userData || !$this->dataHelper) {
            return "";
        }

        return $this->dataHelper->formatLastNameFirst(
            $this->userData['first_name'] ?? '',
            $this->userData['middle_name'] ?? '',
            $this->userData['last_name'] ?? '',
            $this->userData['suffix'] ?? ''
        );
    }
}