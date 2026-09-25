<?php

namespace Src\Common;

use Exception;
use DateTime;

/**
 * Class DataHelper
 * Provides stateless utility functions for data sanitization, formatting,
 * string manipulation, validation, password handling, database CRUD operations,
 * media file processing, and UID generation.
 */
class DataHelper
{
    private $db;
    private string $encoding = 'UTF-8';

    /**
     * DataHelper Constructor.
     * @param \mysqli $db_connect Active database connection.
     */
    public function __construct($db_connect)
    {
        $this->db = $db_connect;
    }

    #=======================================================================================================
    # CLEANING & SANITIZATION

    /**
     * Recursively sanitizes scalar values, strings, arrays, or stringable objects by trimming whitespace.
     * @param mixed $value Value to be cleaned.
     * @return mixed Cleaned value.
     */
    public function clean($value)
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (is_array($value)) {
            return array_map([$this, 'clean'], $value);
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return trim((string)$value);
        }
        return $value;
    }

    /**
     * Maps the clean() method across a flat or associative array.
     * @param array $data Input array.
     * @return array Array with trimmed values.
     */
    public function cleanArray(array $data): array
    {
        return array_map([$this, 'clean'], $data);
    }

    /**
     * Custom trim operation on strings or arrays while preserving primitive types.
     * @param mixed $value Value to be trimmed.
     * @return mixed Trimmed result.
     */
    public function custom_trim($value)
    {
        if (is_string($value)) return trim($value);
        if (is_array($value)) return array_map([$this, 'custom_trim'], $value);
        if (is_object($value) && method_exists($value, '__toString')) return trim((string)$value);
        return $value;
    }

    /**
     * Sanitizes a person's name string by stripping non-alphabetic/punctuation characters and extra spaces.
     * @param string $value Name string to sanitize.
     * @return string Sanitized name.
     */
    public function cleanName(string $value): string
    {
        $value = trim($value);
        $value = preg_replace("/[^\p{L}\s\-\.\']/u", "", $value);
        return preg_replace('/\s+/', ' ', $value);
    }

    #=======================================================================================================
    # ENCODING, FORMATTING & STRING UTILITIES

    /**
     * Detects string character encoding from a list of standard encodings.
     * @param string $value Input string.
     * @return string Detected encoding (defaults to UTF-8).
     */
    public function detect_encoding(string $value): string
    {
        return mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'UTF-8';
    }

    /**
     * Trims and converts array elements to UTF-8 encoding if required.
     * @param array $column Array of string elements to process.
     * @return array UTF-8 encoded array.
     */
    public function array_encoding(array $column = []): array
    {
        $result = [];
        foreach ($column as $index => $value) {
            $result[$index] = $this->custom_trim($value);
            if (is_string($value) && mb_detect_encoding($value)) {
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $result[$index] = mb_convert_encoding($value, "UTF-8", mb_detect_encoding($value));
                }
            }
        }
        return $result;
    }

    /**
     * Converts a string to uppercase supporting multibyte characters.
     * @param mixed $value String or value to convert.
     * @return string Uppercased string.
     */
    public function upper($value): string
    {
        if (!is_string($value)) return (string)$value;
        return mb_strtoupper($this->clean($value), $this->encoding);
    }

    /**
     * Converts a string to lowercase supporting multibyte characters.
     * @param mixed $value String or value to convert.
     * @return string Lowercased string.
     */
    public function lower($value): string
    {
        if (!is_string($value)) return (string)$value;
        return mb_strtolower($this->clean($value), $this->encoding);
    }

    /**
     * Converts a string to title case (capitalizes each word).
     * @param mixed $value Input string.
     * @return string Title-cased string.
     */
    public function title_case($value): string
    {
        if (!is_string($value)) return (string)$value;
        return mb_convert_case($this->lower($value), MB_CASE_TITLE, $this->encoding);
    }

    /**
     * Converts a string to proper sentence casing.
     * @param string $string Input string.
     * @return string Proper-cased string.
     */
    public function toProperCase(string $string): string
    {
        $string = mb_strtolower(trim($string), $this->encoding);
        $words = explode(' ', $string);
        foreach ($words as &$word) {
            $word = mb_strtoupper(mb_substr($word, 0, 1, $this->encoding), $this->encoding) . mb_substr($word, 1, null, $this->encoding);
        }
        return implode(' ', $words);
    }

    /**
     * Generates initials from a full name (e.g., "John Doe" -> "JD").
     * @param string $value Full name.
     * @return string Generated two-letter initials or "??" if invalid.
     */
    public function getNameInitials(string $value): string
    {
        $value = trim($value ?? '');
        if (empty($value)) {
            return '??';
        }
        $cleanName = preg_replace('/[^\w\s-]/', '', $value);
        $words = array_values(array_filter(preg_split('/[\s-]+/', $cleanName)));
        if (empty($words)) {
            return '??';
        }
        if (count($words) === 1) {
            return strtoupper(substr($words[0], 0, 2));
        }
        $first = $words[0][0] ?? '';
        $last = $words[count($words) - 1][0] ?? '';
        return strtoupper($first . $last);
    }

    /**
     * Dynamic Casing Helper (UPPERCASE, lowercase, Title Case)
     */
    private function applyCasing(string $text, string $case = 'title'): string
    {
        $encoding = $this->encoding ?? 'UTF-8';
        switch (strtolower($case)) {
            case 'upper':
                return mb_strtoupper($text, $encoding);
            case 'lower':
                return mb_strtolower($text, $encoding);
            case 'title':
                return mb_convert_case($text, MB_CASE_TITLE, $encoding);
            default:
                return $text;
        }
    }
    /**
     * Formats a full name string in standard order: First Middle Last Suffix, PostNominal.
     * @param string $first First Name
     * @param string $middle Middle Name
     * @param string $last Last Name
     * @param string $suffix Suffix (e.g., Jr., III)
     * @param string $postNominal Post-Nominals (e.g., PhD, RN, LPT)
     * @param string $case Casing option: 'title' (DEFAULT), 'upper', 'lower'
     * @return string Formatted full name.
     */
    public function formatFullName(string $first, string $middle, string $last, string $suffix = '', string $postNominal = '', string $case = 'title'): string
    {
        $parts = array_filter([$first, $middle, $last, $suffix], function ($val) {
            return trim($val) !== '';
        });
        $fullName = $this->applyCasing(implode(' ', $parts), $case);

        if (!empty(trim($postNominal))) {
            $fullName .= ', ' . trim($postNominal);
        }

        return $fullName;
    }

    /**
     * Formats name as: FIRST M. LAST SUFFIX, POSTNOMINAL (FirstName, MiddleInitial, LastName, Suffix, PostNominal)
     * @param string $first First Name
     * @param string $middle Middle Name
     * @param string $last Last Name
     * @param string $suffix Suffix
     * @param string $postNominal Post-Nominals
     * @param string $case Casing option: 'upper' (DEFAULT), 'lower', 'title'
     * @return string Formatted initial name.
     */
    public function formatInitialName(string $first, string $middle, string $last, string $suffix = '', string $postNominal = '', string $case = 'upper'): string
    {
        $encoding = $this->encoding ?? 'UTF-8';
        $middleInitial = '';
        if (!empty(trim($middle))) {
            $middleInitial = mb_substr(trim($middle), 0, 1, $encoding) . '.';
        }
        $parts = array_filter([$first, $middleInitial, $last, $suffix], function ($val) {
            return trim($val) !== '';
        });
        $formattedName = $this->applyCasing(implode(' ', $parts), $case);

        if (!empty(trim($postNominal))) {
            $formattedName .= ', ' . trim($postNominal);
        }

        return $formattedName;
    }

    /**
     * Formats a name string in official order: LAST, FIRST M. SUFFIX, POSTNOMINAL.
     * @param string $first First Name
     * @param string $middle Middle Name
     * @param string $last Last Name
     * @param string $suffix Suffix
     * @param string $postNominal Post-Nominals
     * @param string $case Casing option: 'upper' (DEFAULT), 'lower', 'title'
     * @return string Formatted official name.
     */
    public function formatLastNameFirst(string $first, string $middle, string $last, string $suffix = '', string $postNominal = '', string $case = 'upper'): string
    {
        $encoding = $this->encoding ?? 'UTF-8';
        $middleInitial = '';
        if (!empty(trim($middle))) {
            $middleInitial = mb_substr(trim($middle), 0, 1, $encoding) . '.';
        }
        $firstPart = trim($last);
        $secondPart = implode(' ', array_filter([$first, $middleInitial, $suffix], function ($val) {
            return trim($val) !== '';
        }));
        $name = !empty($firstPart) ? $firstPart . ', ' . $secondPart : $secondPart;
        $name = $this->applyCasing($name, $case);

        if (!empty(trim($postNominal))) {
            $name .= ', ' . trim($postNominal);
        }

        return $name;
    }

    /**
     * Helper utility to extract values from nested associative arrays using dot notation.
     * @param array $data Data array.
     * @param string $path Dot notation key path (e.g., 'user.profile.name').
     * @return mixed Value or null if not set.
     */
    private function getValue(array $data, string $path)
    {
        $keys = explode('.', $path);
        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                return null;
            }
            $data = $data[$key];
        }
        return $data;
    }

    #=======================================================================================================
    # CARD & IDENTIFIER GENERATORS

    /**
     * Generates a unique Card UID string suitable for RFID, NFC, or Smart Cards.
     * 
     * @param int $byteLength Number of bytes for UID (4 bytes = 8 hex chars, 7 bytes = 14 hex chars).
     * @param string $delimiter Character separator between bytes (e.g., ':', '-', or empty string '').
     * @param string|null $table Optional database table to verify unique UID constraint.
     * @param string $column Column name to check uniqueness against if table is provided.
     * @return string Generated unique card UID.
     */
    public function generateCardUid(int $byteLength = 4, string $delimiter = '', ?string $table = null, string $column = 'card_uid'): string
    {
        $maxAttempts = 100;
        $attempt = 0;

        do {
            $bytes = random_bytes($byteLength);
            $hexString = strtoupper(bin2hex($bytes));

            if ($delimiter !== '') {
                $parts = str_split($hexString, 2);
                $cardUid = implode($delimiter, $parts);
            } else {
                $cardUid = $hexString;
            }

            if ($table === null) {
                return $cardUid;
            }

            $exists = $this->selectDuplicate($table, [$column => $cardUid]);
            $attempt++;

            if (!$exists) {
                return $cardUid;
            }
        } while ($attempt < $maxAttempts);

        throw new Exception("Unable to generate a unique card_uid after maximum attempts.");
    }

    #=======================================================================================================
    # VALIDATION UTILITIES

    /**
     * Validates that specified required keys/fields are present in a data array and non-empty.
     * @param array $data Input dataset.
     * @param array $fields Array of field keys to validate.
     * @return array Array of error messages.
     */
    public function required(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $key => $field) {
            $label = is_string($key) ? $key : $field;
            $value = $this->getValue($data, $field);
            if ($value === null || trim((string)$value) === '') {
                $errors[] = strtoupper($label) . " IS REQUIRED";
            }
        }
        return $errors;
    }

    /**
     * Checks if a variable or array structure is empty.
     * @param mixed $data Data to test.
     * @return bool True if empty, false otherwise.
     */
    public function isEmpty($data): bool
    {
        if ($data === null) return true;
        if (is_string($data)) return trim($data) === '';
        if (is_int($data) || is_float($data) || is_bool($data)) return false;
        if (is_array($data)) return $this->isArrayEmpty($data);
        return empty($data);
    }

    /**
     * Recursively verifies if an array is completely empty of values.
     * @param array $arr Array to check.
     * @return bool True if all nested elements are empty.
     */
    public function isArrayEmpty(array $arr): bool
    {
        foreach ($arr as $value) {
            if (!$this->isEmpty($value)) return false;
        }
        return true;
    }

    /**
     * Verifies if a given scalar value consists only of numeric digits.
     * @param mixed $value Input value.
     * @return bool True if digits only.
     */
    public function isDigit($value): bool
    {
        $value = $this->clean($value);
        if (!is_scalar($value) || is_bool($value) || is_float($value)) {
            return false;
        }
        $stringVal = (string)$value;
        return $stringVal !== '' && ctype_digit($stringVal);
    }

    /**
     * Validates if a string contains only letters and spaces.
     * @param string $value Input string.
     * @return bool True if valid letters/spaces.
     */
    public function isLetter(string $value): bool
    {
        return preg_match('/^[\p{L}\s]+$/u', $this->clean($value)) === 1;
    }

    /**
     * Validates if a string is a standard valid email format.
     * @param string $value Email string.
     * @return bool True if valid.
     */
    public function isEmail(string $value): bool
    {
        if ($this->isEmpty($value)) return false;
        $value = $this->lower($this->clean($value));
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validates if a string is an email ending with a specific institutional domain (@ccc.edu.ph).
     * @param string $value Email string.
     * @return bool True if email belongs to domain.
     */
    public function isEmailDomain(string $value): bool
    {
        if ($this->isEmpty($value)) return false;
        $value = $this->lower($this->clean($value));
        return filter_var($value, FILTER_VALIDATE_EMAIL) && preg_match('/@ccc\.edu\.ph$/', $value);
    }

    /**
     * Validates if a phone number matches standard Philippine mobile formats.
     * @param string $value Mobile number string.
     * @return bool True if matches valid phone format.
     */
    public function isPhone(string $value): bool
    {
        $value = $this->clean($value);
        return preg_match('/^(09\d{9}|\+639\d{9}|9\d{9})$/', $value) === 1;
    }

    /**
     * Validates a date string against an expected format.
     * @param mixed $value Date value.
     * @param string $format Expected format (default: 'Y-m-d H:i:s').
     * @param bool $allowISO Allow standard ISO timestamps parsed by strtotime.
     * @return bool True if valid date string.
     */
    public function validateDate($value, string $format = 'Y-m-d H:i:s', bool $allowISO = false): bool
    {
        if ($this->isEmpty($value)) return false;
        $value = $this->clean($value);
        if ($allowISO && strtotime($value) !== false) {
            return true;
        }
        $d = DateTime::createFromFormat($format, $value);
        return $d && $d->format($format) === $value;
    }

    /**
     * Validates username string length boundaries.
     * @param string $username Username string.
     * @param int $minLength Minimum character length.
     * @param int $maxLength Maximum character length.
     * @return bool True if within limits.
     */
    public function validateUsername(string $username, int $minLength = 6, int $maxLength = 30): bool
    {
        $trimmedUsername = $this->clean($username);
        $length = mb_strlen($trimmedUsername);
        return ($length >= $minLength && $length <= $maxLength);
    }

    /**
     * Filters a system access array map by whitelisting specific allowed roles per system component module.
     *
     * @param array $system_access Full multi-dimensional access config map
     * @param array $rules Specified role access filters
     * @return array The filtered system access payload
     */
    public function filterSystemAccess(array $system_access, array $rules): array
    {
        $filtered = [];
        foreach ($rules as $systemKey => $allowedRoles) {
            if (isset($system_access[$systemKey]) && !empty($allowedRoles)) {
                $systemData = $system_access[$systemKey];

                if ($allowedRoles === 'all') {
                    // Replace underscores with spaces in the role values if they exist
                    if (isset($systemData['role']) && is_array($systemData['role'])) {
                        $systemData['role'] = array_map(function ($role) {
                            return str_replace('_', ' ', $role);
                        }, $systemData['role']);
                    }
                    $filtered[$systemKey] = $systemData;
                    continue;
                }

                $systemData['role'] = array_intersect_key($systemData['role'], array_flip(array_map('strval', $allowedRoles)));

                if (!empty($systemData['role'])) {
                    $systemData['role'] = array_map(function ($role) {
                        return str_replace('_', ' ', $role);
                    }, $systemData['role']);

                    $filtered[$systemKey] = $systemData;
                }
            }
        }
        return $filtered;
    }

    #=======================================================================================================
    # SECURITY & PASSWORD HELPERS

    /**
     * Generates a secure random hyphenated password string.
     * @param int $length Character length.
     * @return string Formatted password (e.g., "a1b2-c3d4-e5f6").
     */
    public function generate_password(int $length = 16): string
    {
        $chars = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
        $maxIndex = strlen($chars) - 1;
        $rawPassword = '';
        for ($i = 0; $i < $length; $i++) {
            $rawPassword .= $chars[random_int(0, $maxIndex)];
        }
        return implode('-', str_split($rawPassword, 4));
    }

    /**
     * Creates a secure bcrypt password hash using standard default algorithm settings.
     * @param string $text Plaintext password.
     * @return string Hashed string.
     */
    public function set_password(string $text): string
    {
        $trimmed = trim($text);
        if ($trimmed === "") {
            return '';
        }
        return password_hash($trimmed, PASSWORD_DEFAULT);
    }

    /**
     * Verifies a plaintext password against a stored password hash.
     * @param string $text Plaintext password.
     * @param string $hash Hashed password string.
     * @return bool True if valid match.
     */
    public function verify_password(string $text, string $hash): bool
    {
        return password_verify(trim($text), $hash);
    }

    #=======================================================================================================
    # DATABASE FETCH/SELECT METHODS

    /**
     * Dynamically builds and executes a SELECT query for a database table with parameterized filters.
     * @param string $table Database table name.
     * @param array $conditions Array of WHERE conditions.
     * @param string $columns Select target columns (default '*').
     * @param mixed $orderBy Order string or associative array column/direction.
     * @param mixed $limit Integer limit or offset clause string.
     * @throws Exception If table name or column inputs are invalid.
     * @return array Result rows as associative arrays.
     */
    public function getTableData(string $table, array $conditions = [], string $columns = '*', $orderBy = null, $limit = null): array
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new Exception("Invalid table name");
        }

        // if ($columns !== '*') {
        //     // Split on commas and trim whitespace around each column name
        //     $cols = array_map('trim', explode(',', $columns));

        //     foreach ($cols as $c) {

        //         // Allows: column_name, `column_name`, table.column, or column AS alias
        //         if (!preg_match('/^(`?[a-zA-Z0-9_]+`?\.)?`?[a-zA-Z0-9_]+`?(\s+[aA][sS]\s+`?[a-zA-Z0-9_]+`?)?$/', $c)) {
        //             throw new Exception("Invalid column selection: " . htmlspecialchars($c));
        //         }
        //     }
        // }

        $fields = [];
        $values = [];
        $types = '';

        foreach ($conditions as $key => $condition) {
            $col = '';
            $op = '=';
            $val = null;

            if (!is_numeric($key) && !is_array($condition)) {
                $col = $key;
                $val = $condition;
            } elseif (is_array($condition) && count($condition) === 3) {
                [$col, $op, $val] = $condition;
            } elseif (is_array($condition) && count($condition) === 2) {
                [$col, $val] = $condition;
            } else {
                continue;
            }

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) continue;

            $op = strtoupper(trim($op));
            if (!in_array($op, ['=', '!=', '<>', 'LIKE', 'NOT LIKE', '>', '<', '>=', '<=', 'IN', 'NOT IN'], true)) {
                $op = '=';
            }

            if (is_array($val)) {
                if (empty($val)) {
                    if ($op === 'NOT IN') continue;
                    if ($op === 'IN') {
                        $fields[] = "1=0";
                        continue;
                    }
                }
                $placeholders = [];
                foreach ($val as $item) {
                    $placeholders[] = "?";
                    $values[] = $this->clean($item);
                    $types .= is_int($item) ? 'i' : (is_float($item) ? 'd' : 's');
                }
                $fields[] = "`$col` $op (" . implode(',', $placeholders) . ")";
            } elseif ($val === null) {
                $fields[] = ($op === '!=' || $op === '<>') ? "`$col` IS NOT NULL" : "`$col` IS NULL";
            } else {
                $val = $this->clean($val);
                $fields[] = "`$col` $op ?";
                $values[] = $val;
                $types .= is_int($val) ? 'i' : (is_float($val) ? 'd' : 's');
            }
        }

        $sql = "SELECT $columns FROM `$table`";
        if (!empty($fields)) {
            $sql .= " WHERE " . implode(' AND ', $fields);
        }

        if (!empty($orderBy)) {
            $orderClauses = [];
            if (is_array($orderBy)) {
                foreach ($orderBy as $orderCol => $direction) {
                    $direction = strtoupper(trim($direction)) === 'DESC' ? 'DESC' : 'ASC';
                    if (preg_match('/^[a-zA-Z0-9_]+$/', $orderCol)) {
                        $orderClauses[] = "`$orderCol` $direction";
                    }
                }
            } elseif (is_string($orderBy)) {
                $parts = explode(' ', trim($orderBy));
                $orderCol = $parts[0];
                $direction = (isset($parts[1]) && strtoupper(trim($parts[1])) === 'DESC') ? 'DESC' : 'ASC';
                if (preg_match('/^[a-zA-Z0-9_]+$/', $orderCol)) {
                    $orderClauses[] = "`$orderCol` $direction";
                }
            }
            if (!empty($orderClauses)) {
                $sql .= " ORDER BY " . implode(', ', $orderClauses);
            }
        }

        if ($limit !== null) {
            if (is_numeric($limit)) {
                $sql .= " LIMIT " . (int)$limit;
            } elseif (preg_match('/^\d+\s*,\s*\d+$/', trim($limit))) {
                $sql .= " LIMIT " . trim($limit);
            }
        }

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->db->error);
        }

        if (!empty($values)) {
            $stmt->bind_param($types, ...$values);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    /**
     * Executes a raw parameterized SELECT SQL statement.
     * @param string $sql Raw SQL command with placeholders.
     * @param array $params Parameter values bound to query placeholders.
     * @throws Exception On query preparation or execution failure.
     * @return array Result set array.
     */
    public function selectData(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new Exception("SQL Prepare failed: " . $this->db->error . " | SQL: " . $sql);
        }
        if (!empty($params)) {
            $values = [];
            $types = '';
            foreach ($params as $val) {
                $values[] = $this->clean($val);
                $types .= is_int($val) ? 'i' : (is_float($val) ? 'd' : 's');
            }
            $stmt->bind_param($types, ...$values);
        }
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Select query failed: " . $error);
        }
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    /**
     * Case-insensitively checks if a duplicate record exists in a table matching conditions.
     * @param string $table Database table name.
     * @param array $conditions Array of matching field conditions.
     * @throws Exception On statement failure.
     * @return bool True if duplicate record is found.
     */
    public function selectDuplicate(string $table, array $conditions): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }
        $fields = [];
        $values = [];
        $types = '';

        foreach ($conditions as $key => $condition) {
            if (!is_array($condition)) {
                $col = $key;
                $op = '=';
                $val = $condition;
            } elseif (count($condition) === 2) {
                [$col, $val] = $condition;
                $op = '=';
            } elseif (count($condition) === 3) {
                [$col, $op, $val] = $condition;
            } else {
                continue;
            }

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) continue;
            if (!in_array(strtoupper((string)$op), ['=', '!=', '<>', 'LIKE', 'NOT LIKE', '>', '<', '>=', '<='], true)) {
                $op = '=';
            }

            $val = mb_strtolower($this->clean($val), 'UTF-8');
            $fields[] = "LOWER(`$col`) $op ?";
            $values[] = $val;
            $types .= 's';
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "SELECT 1 FROM `$table` WHERE " . implode(' AND ', $fields) . " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $this->db->error . " | SQL: " . $sql);
        }
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->free_result();
        $stmt->close();
        return $exists;
    }

    /**
     * Checks if a matching row exists and retrieves specific columns if found.
     * @param string $table Database table name.
     * @param array $conditions Condition list.
     * @param array $selectColumns Array of column names to retrieve.
     * @throws Exception On query preparation error.
     * @return array|null Result record array or null if non-existent.
     */
    public function selectDataExists(string $table, array $conditions, array $selectColumns = ['*']): ?array
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return null;
        }
        $fields = [];
        $values = [];
        $types = '';

        foreach ($conditions as $key => $condition) {
            if (!is_array($condition)) {
                $col = $key;
                $op = '=';
                $val = $condition;
            } elseif (count($condition) === 2) {
                [$col, $val] = $condition;
                $op = '=';
            } elseif (count($condition) === 3) {
                [$col, $op, $val] = $condition;
            } else {
                continue;
            }

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) continue;
            if (!in_array(strtoupper((string)$op), ['=', '!=', '<>', 'LIKE', 'NOT LIKE', '>', '<', '>=', '<='], true)) {
                $op = '=';
            }

            $val = mb_strtolower($this->clean($val), 'UTF-8');
            $fields[] = "LOWER(`$col`) $op ?";
            $values[] = $val;
            $types .= 's';
        }

        if (empty($fields)) {
            return null;
        }

        $colsToSelect = [];
        foreach ($selectColumns as $c) {
            if ($c === '*') {
                $colsToSelect = ['*'];
                break;
            }
            if (preg_match('/^[a-zA-Z0-9_]+$/', $c)) {
                $colsToSelect[] = "`$c`";
            }
        }

        $selectStr = empty($colsToSelect) ? '*' : implode(', ', $colsToSelect);
        $sql = "SELECT {$selectStr} FROM `$table` WHERE " . implode(' AND ', $fields) . " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $this->db->error . " | SQL: " . $sql);
        }
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $data ?: null;
    }

    /**
     * Compares new data payload against an existing database record to detect specific field changes.
     * @param string $table Database table name.
     * @param string $primary_key Primary key column identifier.
     * @param mixed $id Primary key row value.
     * @param array $new_data Incoming row modification array.
     * @return array Audit comparison metadata detailing altered attributes.
     */
    public function detectChanges(string $table, $primary_key, $id, array $new_data): array
    {
        $response = ['changed' => false, 'changes' => [], 'old_data' => [], 'new_data' => $new_data];
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return ['changed' => false, 'message' => 'Invalid table'];
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $primary_key)) {
            return ['changed' => false, 'message' => 'Invalid primary key'];
        }

        $sql = "SELECT * FROM `$table` WHERE `$primary_key` = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            return ['changed' => false, 'message' => 'Query error'];
        }

        $idClean = $this->clean($id);
        $type = is_int($idClean) ? 'i' : 's';
        $stmt->bind_param($type, $idClean);
        $stmt->execute();
        $result = $stmt->get_result();
        $old_data = $result->fetch_assoc();
        $stmt->close();

        if (!$old_data) {
            return ['changed' => false, 'message' => 'Record not found'];
        }

        unset($old_data[$primary_key], $new_data[$primary_key]);
        $response['old_data'] = $old_data;
        $changes = [];

        foreach ($new_data as $field => $new_value) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $field) || !array_key_exists($field, $old_data)) {
                continue;
            }
            $old_value = $old_data[$field];
            if (trim((string)$old_value) !== trim((string)$new_value)) {
                $changes[$field] = ['old' => $old_value, 'new' => $new_value];
            }
        }

        if (empty($changes)) {
            return ['changed' => false, 'old_data' => $old_data, 'new_data' => $new_data, 'message' => "No changes detected"];
        }

        return ['changed' => true, 'old_data' => $old_data, 'new_data' => $new_data, 'changes' => $changes];
    }

    #=======================================================================================================
    # DATABASE MUTATION METHODS (INSERT, UPDATE, DELETE)

    /**
     * Inserts a single array row into a target table and returns the auto-increment ID.
     * @param string $table Database table name.
     * @param array $data Column key to value array.
     * @throws Exception On preparation or execution error.
     * @return int Inserted row ID.
     */
    public function insertData(string $table, array $data): int
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new Exception("Invalid table name");
        }
        $fields = [];
        $placeholders = [];
        $values = [];
        $types = '';

        foreach ($data as $col => $val) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) continue;
            $fields[] = "`$col`";
            $placeholders[] = "?";
            $val = $this->clean($val);
            $values[] = $val;
            $types .= is_int($val) ? 'i' : (is_float($val) ? 'd' : 's');
        }

        if (empty($fields)) {
            throw new Exception("No valid data to insert");
        }

        $sql = "INSERT INTO `$table` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new Exception("SQL Prepare failed: " . $this->db->error . " | SQL: " . $sql);
        }

        $stmt->bind_param($types, ...$values);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Insert failed [{$table}]: " . $error);
        }

        $newId = $stmt->insert_id ?: $this->db->insert_id;
        $stmt->close();
        return (int)$newId;
    }

    /**
     * Updates database table records matching given WHERE constraints.
     * @param string $table Database table name.
     * @param array $data Set key/value pairs.
     * @param array $where Condition key/value pairs.
     * @throws Exception On failure.
     * @return bool True on success.
     */
    public function updateData(string $table, array $data, array $where): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new Exception("Invalid table name");
        }
        $set = [];
        $values = [];
        $types = '';

        foreach ($data as $col => $val) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) continue;
            $set[] = "`$col` = ?";
            $val = $this->clean($val);
            $values[] = $val;
            $types .= is_int($val) ? 'i' : (is_float($val) ? 'd' : 's');
        }

        $whereSql = [];
        foreach ($where as $col => $val) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) continue;
            $whereSql[] = "`$col` = ?";
            $val = $this->clean($val);
            $values[] = $val;
            $types .= is_int($val) ? 'i' : (is_float($val) ? 'd' : 's');
        }

        if (empty($set) || empty($whereSql)) {
            throw new Exception("Invalid update data or where clause");
        }

        $sql = "UPDATE `$table` SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $whereSql);
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new Exception("SQL Prepare failed: " . $this->db->error . " | SQL: " . $sql);
        }

        $stmt->bind_param($types, ...$values);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Update failed: " . $error);
        }
        $stmt->close();
        return true;
    }

    /**
     * Performs a soft delete by setting a timestamp column (`deleted_at` by default).
     * @param string $table Database table name.
     * @param array $where Condition key/value pairs.
     * @param string $column Timestamp column name.
     * @throws Exception On failure.
     * @return bool True on soft delete success.
     */
    public function softDelete(string $table, array $where, string $column = 'deleted_at'): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new Exception("Invalid table name");
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new Exception("Invalid column name");
        }

        $whereSql = [];
        $values = [];
        $types = '';

        foreach ($where as $col => $val) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) continue;
            $whereSql[] = "`$col` = ?";
            $val = $this->clean($val);
            $values[] = $val;
            $types .= is_int($val) ? 'i' : (is_float($val) ? 'd' : 's');
        }

        if (empty($whereSql)) {
            throw new Exception("Invalid WHERE clause");
        }

        $sql = "UPDATE `$table` SET `$column` = NOW() WHERE " . implode(' AND ', $whereSql);
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->db->error);
        }

        $stmt->bind_param($types, ...$values);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Soft delete failed: " . $error);
        }
        $stmt->close();
        return true;
    }

    /**
     * Inserts multiple rows in a single batch query.
     * @param string $table Database table name.
     * @param array $rows Array of associative row arrays.
     * @throws Exception On field or query errors.
     * @return bool True if batch insert completed successfully.
     */
    public function bulkInsert(string $table, array $rows): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new Exception("Invalid table name");
        }
        if (empty($rows)) return false;

        $columns = array_keys(reset($rows));
        foreach ($columns as $col) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
                throw new Exception("Invalid column: $col");
            }
        }

        $placeholders = [];
        $values = [];
        $types = '';

        foreach ($rows as $row) {
            $ph = [];
            foreach ($columns as $col) {
                $val = $this->clean($row[$col] ?? null);
                $values[] = $val;
                $types .= is_int($val) ? 'i' : (is_float($val) ? 'd' : 's');
                $ph[] = "?";
            }
            $placeholders[] = "(" . implode(',', $ph) . ")";
        }

        $sql = "INSERT INTO `$table` (`" . implode('`,`', $columns) . "`) VALUES " . implode(',', $placeholders);
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->db->error);
        }

        $stmt->bind_param($types, ...$values);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Bulk insert failed: " . $error);
        }
        $stmt->close();
        return true;
    }

    #=======================================================================================================
    # FILE UPLOADS & CONVERSIONS

    /**
     * Uploads media assets safely directly into the target directory with automatic conversion to WebP or WebM.
     * @param array $fileArray Standard $_FILES uploaded item element structure.
     * @param string $fileDir Target storage directory path.
     * @param int $maxImageMb Image max size threshold in MB.
     * @param int $maxVideoMb Video max size threshold in MB.
     * @param int $maxDocMb Document max size threshold in MB.
     * @throws Exception On invalid files, directory creation, or size boundary limits.
     * @return array Saved relative filename/path and clean filename.
     */
    public function saveFileWithConversion(array $fileArray, string $fileDir, int $maxImageMb = 10, int $maxVideoMb = 30, int $maxDocMb = 20): array
    {
        if (!isset($fileArray['tmp_name']) || empty($fileArray['tmp_name']) || ($fileArray['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            if (isset($fileArray['error']) && $fileArray['error'] === UPLOAD_ERR_INI_SIZE) {
                throw new Exception("File upload failed: Exceeds php.ini upload_max_filesize limit.");
            }
            throw new Exception("File upload failed with error code: " . ($fileArray['error'] ?? 'unknown'));
        }

        $tempPath = $fileArray['tmp_name'];
        $origNameInfo = pathinfo($fileArray['name'] ?? 'file');
        $cleanFileName = preg_replace("/[^a-zA-Z0-9_-]/", "_", $origNameInfo['filename']);
        $extension = strtolower($origNameInfo['extension'] ?? '');

        $mimeType = function_exists('mime_content_type') ? (mime_content_type($tempPath) ?: '') : '';
        $imgExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        $vidExts = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'flv', 'wmv'];
        $docExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'txt', 'csv'];

        $isImage = (strpos($mimeType, 'image/') === 0) || in_array($extension, $imgExts, true);
        $isVideo = (strpos($mimeType, 'video/') === 0) || in_array($extension, $vidExts, true);
        $isDoc = in_array($extension, $docExts, true);

        if (!$isImage && !$isVideo && !$isDoc) {
            throw new Exception("Unsupported file type or extension: {$extension}");
        }

        $allowedMbLimit = $isImage ? $maxImageMb : ($isVideo ? $maxVideoMb : $maxDocMb);
        $maxBytes = $allowedMbLimit * 1024 * 1024;

        if (($fileArray['size'] ?? 0) > $maxBytes) {
            $assetLabel = $isImage ? 'Image' : ($isVideo ? 'Video' : 'Document');
            throw new Exception("{$assetLabel} file size (" . round($fileArray['size'] / 1048576, 2) . " MB) exceeds maximum limit of {$allowedMbLimit} MB.");
        }

        $baseDirectory = rtrim($fileDir, '/\\') . '/';
        if (!is_dir($baseDirectory) && !mkdir($baseDirectory, 0755, true) && !is_dir($baseDirectory)) {
            throw new Exception("Failed to create target upload directory.");
        }

        // 1. Image Processing & WebP Conversion
        if ($isImage) {
            $uniqueFileName = time() . '_' . uniqid() . '_' . $cleanFileName . '.webp';
            $destination = $baseDirectory . $uniqueFileName;
            $image = null;

            if (in_array($extension, ['jpg', 'jpeg'], true)) {
                $image = @imagecreatefromjpeg($tempPath);
            } elseif ($extension === 'png') {
                $image = @imagecreatefrompng($tempPath);
            } elseif ($extension === 'gif') {
                $image = @imagecreatefromgif($tempPath);
            } elseif ($extension === 'webp') {
                $image = @imagecreatefromwebp($tempPath);
            }

            if (!$image && function_exists('imagecreatefromstring')) {
                $fileData = @file_get_contents($tempPath);
                if ($fileData !== false) {
                    $image = @imagecreatefromstring($fileData);
                }
            }

            if ($image) {
                // Convert palette images to TrueColor
                if (!imageistruecolor($image)) {
                    imagepalettetotruecolor($image);
                }
                // Preserve alpha channel for transparent PNGs
                imagealphablending($image, true);
                imagesavealpha($image, true);

                if (function_exists('imagewebp') && imagewebp($image, $destination, 80)) {
                    imagedestroy($image);
                    return ['filePath' => $uniqueFileName, 'fileName' => $cleanFileName . '.webp'];
                }
                imagedestroy($image);
            }

            $fallbackFileName = time() . '_' . uniqid() . '_' . $cleanFileName . '.' . $extension;
            if (move_uploaded_file($tempPath, $baseDirectory . $fallbackFileName)) {
                return ['filePath' => $fallbackFileName, 'fileName' => $cleanFileName . '.' . $extension];
            }
        }

        // 2. Video Processing & WebM Conversion
        if ($isVideo) {
            $uniqueFileName = time() . '_' . uniqid() . '_' . $cleanFileName . '.webm';
            $destination = $baseDirectory . $uniqueFileName;

            if (function_exists('exec')) {
                $cmd = "ffmpeg -i " . escapeshellarg($tempPath) . " -c:v libvpx-vp9 -crf 30 -b:v 0 -c:a libopus " . escapeshellarg($destination) . " 2>&1";
                @exec($cmd, $output, $returnCode);
                if ($returnCode === 0 && file_exists($destination)) {
                    return ['filePath' => $uniqueFileName, 'fileName' => $cleanFileName . '.webm'];
                }
            }

            $rawFileName = time() . '_' . uniqid() . '_' . $cleanFileName . '.' . $extension;
            if (move_uploaded_file($tempPath, $baseDirectory . $rawFileName)) {
                return ['filePath' => $rawFileName, 'fileName' => $fileArray['name'] ?? ($cleanFileName . '.' . $extension)];
            }
        }

        // 3. Document / Generic File Fallback
        $uniqueFileName = time() . '_' . uniqid() . '_' . $cleanFileName . '.' . $extension;
        if (move_uploaded_file($tempPath, $baseDirectory . $uniqueFileName)) {
            return ['filePath' => $uniqueFileName, 'fileName' => $fileArray['name'] ?? ($cleanFileName . '.' . $extension)];
        }

        throw new Exception("Failed to save uploaded file to server storage.");
    }

    #=======================================================================================================
    # 

    /**
     * Generates HTML option tags dynamically from any array format.
     *
     * @param array $options The array to loop through.
     * @param bool $useKeysAsValue AsValue Set to true if the array keys should be the option values (e.g., for associative arrays).
     * @param string|int|null $selectedValue The value/key that should be pre-selected.
     */
    public function generateSelectOptions(array $options, bool $useKeysAsValue = false, $selectedValue = null)
    {
        foreach ($options as $key => $value) {
            $optionValue = $useKeysAsValue ? $key : $value;
            $selected = ($selectedValue !== null && $optionValue == $selectedValue) ? ' selected' : '';

            echo '<option value="' . htmlspecialchars($optionValue) . '"' . $selected . '>';
            echo htmlspecialchars($value);
            echo '</option>';
        }
    }
}
