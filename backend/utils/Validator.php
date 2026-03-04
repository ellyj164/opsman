<?php
/**
 * OpsMan – Input Validator
 */

class Validator {

    private array $errors = [];

    // ------------------------------------------------------------------
    // Fluent helpers
    // ------------------------------------------------------------------

    /**
     * Assert that $value is not empty.
     */
    public function required(string $field, mixed $value): static {
        if ($value === null || $value === '') {
            $this->errors[$field] = "{$field} is required";
        }
        return $this;
    }

    /**
     * Assert a valid e-mail address.
     */
    public function email(string $field, mixed $value): static {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "{$field} must be a valid email address";
        }
        return $this;
    }

    /**
     * Assert minimum string length.
     */
    public function minLength(string $field, mixed $value, int $min): static {
        if ($value !== null && mb_strlen((string) $value) < $min) {
            $this->errors[$field] = "{$field} must be at least {$min} characters";
        }
        return $this;
    }

    /**
     * Assert maximum string length.
     */
    public function maxLength(string $field, mixed $value, int $max): static {
        if ($value !== null && mb_strlen((string) $value) > $max) {
            $this->errors[$field] = "{$field} must not exceed {$max} characters";
        }
        return $this;
    }

    /**
     * Assert value is among an allowed set.
     */
    public function in(string $field, mixed $value, array $allowed): static {
        if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
            $this->errors[$field] = "{$field} must be one of: " . implode(', ', $allowed);
        }
        return $this;
    }

    /**
     * Assert value is numeric.
     */
    public function numeric(string $field, mixed $value): static {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->errors[$field] = "{$field} must be numeric";
        }
        return $this;
    }

    /**
     * Assert value is a valid datetime string.
     */
    public function datetime(string $field, mixed $value): static {
        if ($value !== null && $value !== '') {
            $d = DateTime::createFromFormat('Y-m-d H:i:s', $value)
              ?: DateTime::createFromFormat('Y-m-d', $value)
              ?: DateTime::createFromFormat(DateTime::ATOM, $value);
            if (!$d) {
                $this->errors[$field] = "{$field} must be a valid date/time";
            }
        }
        return $this;
    }

    // ------------------------------------------------------------------
    // File-upload validation (static, no chaining)
    // ------------------------------------------------------------------

    /**
     * Validate an uploaded file array ($_FILES entry).
     *
     * @param array  $file          Entry from $_FILES
     * @param array  $allowedExt    Allowed extensions (lowercase)
     * @param int    $maxBytes      Maximum file size in bytes
     * @return array{valid:bool, error:string|null}
     */
    public static function validateFile(
        array $file,
        array $allowedExt = ['jpg','jpeg','png','gif','pdf','doc','docx'],
        int   $maxBytes   = 10 * 1024 * 1024
    ): array {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'File upload failed or no file provided'];
        }

        if ($file['size'] > $maxBytes) {
            $mb = round($maxBytes / 1024 / 1024, 1);
            return ['valid' => false, 'error' => "File exceeds maximum size of {$mb} MB"];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            return ['valid' => false, 'error' => 'File type not allowed. Allowed: ' . implode(', ', $allowedExt)];
        }

        // MIME check
        $allowedMime = [
            'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png'  => 'image/png',  'gif'  => 'image/gif',
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (isset($allowedMime[$ext]) && $mime !== $allowedMime[$ext]) {
            return ['valid' => false, 'error' => 'File content does not match extension'];
        }

        return ['valid' => true, 'error' => null];
    }

    // ------------------------------------------------------------------
    // Result helpers
    // ------------------------------------------------------------------

    public function fails(): bool {
        return !empty($this->errors);
    }

    public function errors(): array {
        return $this->errors;
    }

    /**
     * Sanitize a string value.
     */
    public static function sanitize(mixed $value): string {
        return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
    }
}
