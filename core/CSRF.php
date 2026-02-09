<?php
/**
 * AltNET Ecount ERP - CSRF Token Manager
 */
class CSRF {
    public static function generate() {
        if (!Session::has('csrf_token')) {
            Session::set('csrf_token', bin2hex(random_bytes(32)));
        }
        return Session::get('csrf_token');
    }

    public static function field() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::generate()) . '">';
    }

    public static function verify($token = null) {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }
        $valid = hash_equals(Session::get('csrf_token', ''), $token);
        // Regenerate token after verification
        Session::set('csrf_token', bin2hex(random_bytes(32)));
        return $valid;
    }

    public static function meta() {
        return '<meta name="csrf-token" content="' . htmlspecialchars(self::generate()) . '">';
    }
}
