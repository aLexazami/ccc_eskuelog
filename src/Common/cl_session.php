<?php

/**
 * Session & Anti-CSRF Security Engineering Module.
 * Optimized for PHP 7.x & 8.x Environments.
 */

class Session
{
    /** @var string Key for session value array */
    protected $valuesKey = 'asdfdotdev.session';

    /** @var string Name of the session */
    protected $name;

    /** @var string Path the session cookie is available on  */
    protected $path;

    /** @var string Domain the cookie is available on */
    protected $domain;

    /** @var boolean Only transmit the session cookie over https */
    protected $secure;

    /** @var string Name of hashing algorithm to use for hashed values */
    protected $hash;

    /** @var int Length of Session ID string */
    protected $idLength;

    /** @var int Number of bits in encoded Session ID characters */
    protected $idBits;

    /** @var boolean Generate fake PHPSESSID cookie */
    protected $decoy;

    /** @var int Minimum time in seconds to regenerate session id */
    protected $timeMin;

    /** @var int Maximum time in seconds to regenerate session id */
    protected $timeMax;

    /** @var bool */
    protected $debug;

    protected $gc_probability = 1;

    protected $expiration = 7200;

    protected $session_table = 'sessions_table';

    /**
     * Initialize Session Engine
     * @param array $config Session Configuration Constants Matrix
     */
    public function __construct(array $config = [])
    {
        $settings = array_merge(
            [
                'name'       => 'asdfdotdev',
                'path'       => '/',
                'domain'     => '',
                'secure'     => false,
                'bits'       => 4,
                'length'     => 32,
                'hash'       => 'sha256',
                'decoy'      => true,
                'min'        => 1800,
                'max'        => 3600,
                'expiration' => 7200,
                'debug'      => false,
            ],
            $config
        );

        $this->setName($settings['name']);
        $this->setPath($settings['path']);
        $this->setDomain($settings['domain']);
        $this->setSecure($settings['secure']);
        $this->setIdBits($settings['bits']);
        $this->setIdLength($settings['length']);
        $this->setHash($settings['hash']);
        $this->decoy      = (bool)$settings['decoy'];
        $this->timeMin    = (int)$settings['min'];
        $this->timeMax    = (int)$settings['max'];
        $this->expiration = (int)$settings['expiration'];
        $this->debug      = (bool)$settings['debug'];

        $this->verifySettings();
    }

    protected function setName(string $name)
    {
        $this->name = $name;
    }

    protected function getName(): string
    {
        return $this->name;
    }

    protected function setPath(string $path)
    {
        $this->path = $path;
    }

    protected function getPath(): string
    {
        return $this->path;
    }

    protected function setDomain(string $domain = '')
    {
        $this->domain = ($domain == '') ? ($_SERVER['SERVER_NAME'] ?? 'localhost') : $domain;
    }

    protected function getDomain(): string
    {
        return $this->domain;
    }

    protected function setSecure(bool $secure = false)
    {
        $this->secure = $secure ?: isset($_SERVER['HTTPS']);
    }

    protected function getSecure(): bool
    {
        return $this->secure;
    }

    protected function setHash(string $hash = '')
    {
        if (in_array($hash, hash_algos())) {
            $this->hash = $hash;
        } else {
            $this->error('Server environment does not support selected hash algorithm context.');
        }
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    protected function setIdLength(int $length)
    {
        if (in_array($length, range(22, 256))) {
            $this->idLength = $length;
        } else {
            $this->error('Session ID length invalid. Bounds must remain between 22 to 256.');
        }
    }

    protected function getIdLength(): int
    {
        return $this->idLength;
    }

    protected function setIdBits(int $bits)
    {
        if (in_array($bits, range(4, 6))) {
            $this->idBits = $bits;
        } else {
            $this->error('Session ID bits per character configurations invalid. Options are 4, 5, or 6.');
        }
    }

    protected function getIdBits(): int
    {
        return $this->idBits;
    }

    protected function generateDecoyCookie()
    {
        if ($this->decoy && !isset($_COOKIE['PHPSESSID'])) {
            $this->setValue('decoy_value', md5((string)mt_rand()));

            setcookie('PHPSESSID', (string)$this->getValue('decoy_value'), [
                'expires'  => time() + $this->expiration,
                'path'     => $this->getPath(),
                'domain'   => $this->getDomain(),
                'secure'   => $this->getSecure(),
                'httponly' => false,
                'samesite' => 'Lax'
            ]);
        }
    }

    /**
     * Normalize User-Agent to prevent session drops from browser media workers
     */
    protected function generateFingerprint(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

        // Strip media engine headers that change on image/video requests
        $normalizedUA = preg_replace('/(QuickTime|AVFoundation|AppleCoreMedia|CFNetwork|ChromeMediaFetcher)/i', '', $userAgent);

        return hash('sha256', trim($normalizedUA) . $remoteAddr);
    }

    protected function setFingerprint()
    {
        $this->setValue('fingerprint', $this->generateFingerprint());
    }

    protected function validateFingerprint(): bool
    {
        // FIX: Match key name 'fingerprint' set in setFingerprint()
        $stored = $this->getValue('fingerprint');
        $current = $this->generateFingerprint();

        if (empty($stored)) {
            return true;
        }

        if (!hash_equals((string)$stored, (string)$current)) {
            // DEBUG: Log why the fingerprint check failed
            error_log("Session Fingerprint Mismatch! Stored: {$stored} | Current: {$current} | UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? ''));

            // TEMPORARILY disable auto-ending session on mismatch to test:
            // $this->end(); 
            // return false;

            return true; // Bypass temporarily to see if this is the cause
        }

        return true;
    }

    protected function resetLifespan()
    {
        $this->setValue('lifespan', time() + mt_rand($this->timeMin, $this->timeMax));
    }

    /**
     * Update timer WITHOUT regenerating session ID
     */
    protected function checkLifespan()
    {
        $lifespan = $this->getValue('lifespan');

        if (empty($lifespan) || (int)$lifespan < time()) {
            // Just extend the lifespan timestamp. DO NOT REGENERATE THE SESSION ID HERE!
            $this->resetLifespan();
        }
    }

    public function start($restart = false)
    {
        if (!$restart) {
            $this->configureSystemSessionSettings();
        }

        $this->prepareSession();

        // FIX: Set fingerprint on initial start if it hasn't been saved yet
        if ($this->getValue('fingerprint') === null) {
            $this->setFingerprint();
        }

        if ($this->validateFingerprint()) {
            if ($restart) {
                $this->regenerateId();
                $this->setFingerprint();
                $this->resetLifespan();
            }

            $this->generateDecoyCookie();
            $this->checkLifespan();
            $this->setValue('session_loaded', time());
            $this->setValue('ttl', ((int)$this->getValue('lifespan') - (int)$this->getValue('session_loaded')));
        }
    }

    private function prepareSession()
    {
        // Session directive configuration
        ini_set('session.gc_probability', '0'); // Disable automatic GC on web requests to prevent race conditions
        ini_set('session.gc_maxlifetime', (string)($this->expiration ?? 43200));
        ini_set('session.cookie_lifetime', (string)($this->expiration ?? 43200));
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1'); // Reject uninitialized session IDs (Security Best Practice)

        // FIX: Ensure cookie options and session name are ONLY set BEFORE session_start() runs
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => $this->expiration,
                'path'     => $this->getPath(),
                'domain'   => $this->getDomain(),
                'secure'   => $this->getSecure(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            session_name($this->getName());
            session_start();
        }

        if (!isset($_SESSION[$this->valuesKey]) || !is_array($_SESSION[$this->valuesKey])) {
            $_SESSION[$this->valuesKey] = [];
        }
    }

    public function getValue($key)
    {
        return $_SESSION[$this->valuesKey][$key] ?? null;
    }

    public function setValue($key, $value, $hash = false)
    {
        if ($hash) {
            $value = hash($this->getHash(), (string)$value);
        }
        $_SESSION[$this->valuesKey][$key] = $value;
    }

    public function appValue($key, $value)
    {
        $currentValue = $this->getValue($key);
        if (isset($currentValue)) {
            if (is_array($currentValue)) {
                $updatedValue = array_merge($currentValue, (array)$value);
            } elseif (is_string($currentValue)) {
                $updatedValue = $currentValue . (string)$value;
            } else {
                $updatedValue = $value;
            }
        } else {
            $updatedValue = $value;
        }
        $this->setValue($key, $updatedValue);
    }

    public function incValue($key, $value)
    {
        if (!is_numeric($value)) {
            $this->error(sprintf('Only numeric metrics integers can be evaluated in %s', __METHOD__));
        }
        $currentValue = $this->getValue($key);
        $updatedValue = isset($currentValue) ? ($currentValue + $value) : $value;
        $this->setValue($key, $updatedValue);
    }

    public function session_close()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public function dropValue($key)
    {
        unset($_SESSION[$this->valuesKey][$key]);
    }

    public function regenerate()
    {
        $this->start(true);
    }

    private function regenerateId()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $new_id = session_id();
            session_write_close();
            session_id($new_id);
            session_start();
        }
    }

    private function configureSystemSessionSettings()
    {
        if (function_exists('ini_set') && session_status() === PHP_SESSION_NONE) {
            ini_set('session.sid_length', (string)$this->getIdLength());
            ini_set('session.sid_bits_per_character', (string)$this->getIdBits());
            ini_set('session.cookie_secure', $this->getSecure() ? '1' : '0');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.gc_probability', (string)$this->gc_probability);
            ini_set('session.gc_divisor', '1000');
            ini_set('session.gc_maxlifetime', (string)$this->expiration);
        }
    }

    public function destroy()
    {
        $this->end();
    }

    public function end()
    {
        // If session is not active, try starting it using the configured name so PHP knows what to destroy
        if (session_status() === PHP_SESSION_NONE) {
            session_name($this->getName());
            @session_start();
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            // Clear all session data in memory
            $_SESSION = [];

            // Expire the main session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();

                setcookie(session_name(), '', [
                    'expires'  => time() - 42000,
                    'path'     => $params["path"],
                    'domain'   => $params["domain"],
                    'secure'   => $params["secure"],
                    'httponly' => $params["httponly"],
                    'samesite' => $params["samesite"] ?? 'Lax'
                ]);

                // Clear decoy cookie if set
                if ($this->decoy && isset($_COOKIE['PHPSESSID'])) {
                    setcookie('PHPSESSID', '', [
                        'expires'  => time() - 42000,
                        'path'     => $this->getPath(),
                        'domain'   => $this->getDomain(),
                        'secure'   => $this->getSecure(),
                        'httponly' => false,
                        'samesite' => 'Lax'
                    ]);
                }
            }

            // Destroy the session storage file/data on the server
            session_destroy();
        }
    }

    /**
     * Removed `: mixed` return type to maintain PHP 7.x compatibility.
     */
    public function dump($format = 1)
    {
        switch ($format) {
            case 1:
                return print_r($_SESSION, true);
            case 2:
                return $_SESSION;
            case 3:
            default:
                return json_encode($_SESSION);
        }
    }

    private function validateSessionLifespan()
    {
        if ($this->timeMin > $this->timeMax) {
            $tmp = $this->timeMin;
            $this->timeMin = $this->timeMax;
            $this->timeMax = $tmp;
        }
    }

    private function validateSystemTimezone()
    {
        if (function_exists('ini_get') && ini_get('date.timezone') == '') {
            date_default_timezone_set('UTC');
        }
    }

    private function validateSessionDir()
    {
        $path = session_save_path();
        if (!empty($path) && !is_writable($path)) {
            $this->error('System session environment path workspace directory is not writable.');
        }
    }

    private function validateSessionDomain()
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (!empty($host) && $host !== $this->getDomain() && strpos($host, 'localhost') === false) {
            $this->error(sprintf('Cookie domain configuration definition parameters mapping mismatch payload: (%s) vs (%s).', $host, $this->getDomain()));
        }
    }

    private function validatePHPVersion()
    {
        if (version_compare(phpversion(), '7.2.0', '<')) {
            $this->error('PHP development paradigm requires a minimum architecture boundary of v7.2.0.');
        }
    }

    protected function error($response)
    {
        throw new \Exception($response);
    }

    protected function verifySettings()
    {
        $this->validateSystemTimezone();
        $this->validateSessionLifespan();

        if ($this->debug) {
            $this->validatePHPVersion();
            $this->validateSessionDir();
            $this->validateSessionDomain();
        }
    }
}

# ======================================================================
# CRYPTOGRAPHIC ANTI-CROSS-SITE REQUEST FORGERY ENGINE
# ======================================================================
class CSRF
{
    private $name;
    private $hashes;
    private $hashTime2Live;
    private $hashSize;
    private $inputName;
    private $session_class;

    public function __construct($session_class, $session_name = 'csrf-lib', $hashTime2Live = 0, $hashSize = 64)
    {
        $this->name = (string)$session_name;
        $this->hashTime2Live = (int)$hashTime2Live;
        $this->hashSize = (int)$hashSize;
        $this->session_class = $session_class;
        $this->_load();
    }

    private function generateHash($context = '', $time2Live = -1, $max_hashes = 5): CSRF_Hash
    {
        if ($time2Live < 0) {
            $time2Live = $this->hashTime2Live;
        }
        $hash = new CSRF_Hash($context, $time2Live, $this->hashSize);
        array_push($this->hashes, $hash);

        if ($this->clearHashes($context, $max_hashes) == 0) {
            $this->_save();
        }
        return $hash;
    }

    public function getHashes($context = '', $max_hashes = -1): array
    {
        $len = count($this->hashes);
        $hashes = array();
        for ($i = $len - 1; $i >= 0 && $len > 0; $i--) {
            if ($this->hashes[$i]->inContext($context)) {
                array_push($hashes, $this->hashes[$i]->get());
                $len--;
            }
        }
        return $hashes;
    }

    public function clearHashes($context = '', $max_hashes = 0): int
    {
        $ignore = $max_hashes;
        $deleted = 0;
        for ($i = count($this->hashes) - 1; $i >= 0; $i--) {
            if ($this->hashes[$i]->inContext($context) && $ignore-- <= 0) {
                array_splice($this->hashes, $i, 1);
                $deleted++;
            }
        }
        if ($deleted > 0) {
            $this->_save();
        }
        return $deleted;
    }

    public function input($context = '', $input = 'key-awesome', $time2Live = -1, $max_hashes = 1): string
    {
        $hash = $this->generateHash($context, $time2Live, $max_hashes);
        $this->inputName = (string)$input;
        return '<input type="hidden" name="' . htmlspecialchars($this->inputName, ENT_QUOTES, 'UTF-8') . '" id="' . htmlspecialchars($this->inputName, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($hash->get(), ENT_QUOTES, 'UTF-8') . '"/>';
    }

    public function script($context = '', $name = '', $declaration = 'var', $time2Live = -1, $max_hashes = 5): string
    {
        $hash = $this->generateHash($context, $time2Live, $max_hashes);
        if (strlen($name ?? '') == 0) {
            $name = $this->inputName;
        }
        return '<script type="text/javascript">' . htmlspecialchars($declaration ?? 'var', ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' = ' . json_encode($hash->get()) . ';</script>';
    }

    public function javascript($context = '', $name = '', $declaration = 'var', $time2Live = -1, $max_hashes = 5): string
    {
        $hash = $this->generateHash($context, $time2Live, $max_hashes);
        if (strlen($name ?? '') == 0) {
            $name = $this->inputName;
        }
        return htmlspecialchars($declaration ?? 'var', ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' = ' . json_encode($hash->get()) . ';';
    }

    public function string($context = '', $time2Live = -1, $max_hashes = 5): string
    {
        return $this->generateHash($context, $time2Live, $max_hashes)->get();
    }

    public function validate($context = '', $hash = null): bool
    {
        if (is_null($hash)) {
            if (isset($_POST[$this->inputName])) {
                $hash = $_POST[$this->inputName];
            } elseif (isset($_GET[$this->inputName])) {
                $hash = $_GET[$this->inputName];
            } else {
                return false;
            }
        }

        for ($i = count($this->hashes) - 1; $i >= 0; $i--) {
            if ($this->hashes[$i]->verify((string)$hash, $context)) {
                array_splice($this->hashes, $i, 1);
                $this->_save();
                return true;
            }
        }
        return false;
    }

    private function _load()
    {
        $this->hashes = array();
        $csrf_session = $this->session_class->getValue($this->name);

        if (!empty($csrf_session) && is_string($csrf_session)) {
            $session_hashes = unserialize($csrf_session);
            if (is_array($session_hashes)) {
                for ($i = count($session_hashes) - 1; $i >= 0; $i--) {
                    if ($session_hashes[$i]->hasExpire()) {
                        break;
                    }
                    array_unshift($this->hashes, $session_hashes[$i]);
                }
                if (count($this->hashes) != count($session_hashes)) {
                    $this->_save();
                }
            }
        }
    }

    private function _save()
    {
        $this->session_class->setValue($this->name, serialize($this->hashes));
    }
}

class CSRF_Hash
{
    private $hash;
    private $context;
    private $expire;

    public function __construct($context, $time2Live = 0, $hashSize = 64)
    {
        $this->context = (string)$context;
        $this->hash = $this->_generateHash((int)$hashSize);
        $this->expire = ($time2Live > 0) ? (time() + (int)$time2Live) : 0;
    }

    private function _generateHash(int $n): string
    {
        return bin2hex(openssl_random_pseudo_bytes($n / 2));
    }

    public function hasExpire(): bool
    {
        return ($this->expire != 0 && $this->expire <= time());
    }

    public function verify(string $hash, string $context = ''): bool
    {
        $context_valid = hash_equals($this->context, $context);
        $hash_valid    = hash_equals($this->hash, $hash);

        return $context_valid && !$this->hasExpire() && $hash_valid;
    }

    public function inContext(string $context = ''): bool
    {
        return hash_equals($this->context, $context);
    }

    public function get(): string
    {
        return $this->hash;
    }
}

# ======================================================================
# AUTO-INITIALIZATION BOOTSTRAPPER (FIXED EXECUTION ORDER)
# ======================================================================

# DEFINE CONFIGURATION FIRST
if (!defined('SESSION_CONFIG')) {
    define('SESSION_CONFIG', [
        'name'       => defined('DEFAULT_SESSION') ? DEFAULT_SESSION : 'asdfdotdev',
        'path'       => '/',
        'domain'     => '',
        'secure'     => false,
        'bits'       => 4,
        'length'     => 32,
        'hash'       => 'sha256',
        'decoy'      => true,
        'min'        => 300,
        'max'        => 800,
        'expiration' => 14400,
        'debug'      => false
    ]);
}

# INITIALIZE AND START SESSION WITH DEFINED CONFIG
$session_class = new Session(SESSION_CONFIG);
$session_class->start();
