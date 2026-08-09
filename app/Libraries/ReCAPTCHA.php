<?php

namespace App\Libraries;


class ReCAPTCHA {

    private $re_captcha_secret_key;
    private $re_captcha_protocol;

    public function __construct() {
        $this->re_captcha_secret_key = trim((string) (
            getenv("PODC_RECAPTCHA_SECRET_KEY") ?: get_setting("re_captcha_secret_key")
        ));
        $this->re_captcha_protocol = strtolower(trim((string) (
            getenv("PODC_RECAPTCHA_PROTOCOL") ?: get_setting("re_captcha_protocol") ?: "v2"
        )));
    }

    public function validate_recaptcha($show_error = true) {
        if (!$this->re_captcha_secret_key) {
            if (defined("ENVIRONMENT") && ENVIRONMENT === "production") {
                $message = "Human verification is temporarily unavailable.";
                if ($show_error) {
                    echo json_encode(["success" => false, "message" => $message]);
                    exit();
                }
                return $message;
            }
            return true;
        }

        $request = \Config\Services::request();

        if ($this->re_captcha_protocol === "v3") {
            $re_captcha_token = $request->getPost("re_captcha_token");
            $response = $this->_is_valid_recaptcha_v3($re_captcha_token);
            return $this->_process_response($response, $show_error);
        } else {
            $response = $this->_is_valid_recaptcha_v2($request->getPost("g-recaptcha-response"));
            return $this->_process_response($response, $show_error);
        }
    }

    private function _process_response($response, $show_error) {
        if ($response !== true) {

            if ($this->re_captcha_protocol !== "v3") {
                $response = $response ? app_lang("re_captcha_error-" . $response) : app_lang("re_captcha_expired");
            }

            if ($show_error) {
                echo json_encode(array('success' => false, 'message' => $response));
                exit();
            } else {
                return $response;
            }
        } else {
            return true;
        }
    }

    private function _is_valid_recaptcha_v3($recaptcha_post_data) {
        $recaptcha_post_data = trim((string) $recaptcha_post_data);
        if ($recaptcha_post_data === "" || strlen($recaptcha_post_data) > 4096) {
            return app_lang("re_captcha_error-bad-request");
        }
        $responseKeys = $this->_verify_with_provider($recaptcha_post_data);
        if (!is_array($responseKeys)) {
            return app_lang("re_captcha_error-bad-request");
        }

        if (empty($responseKeys["success"]) || !$this->_hostname_matches($responseKeys["hostname"] ?? "")) {
            return app_lang("re_captcha_error-bad-request");
        }

        $expectedAction = trim((string)(getenv("PODC_RECAPTCHA_EXPECTED_ACTION") ?: "submit"));
        $actualAction = trim((string)($responseKeys["action"] ?? ""));
        if ($expectedAction === "" || $actualAction === "" || !hash_equals($expectedAction, $actualAction)) {
            return app_lang("re_captcha_error-bad-request");
        }

        $configuredThreshold = getenv("PODC_RECAPTCHA_MIN_SCORE");
        $threshold = $configuredThreshold !== false && $configuredThreshold !== ""
            ? (float)$configuredThreshold
            : 0.5;
        $threshold = min(1.0, max(0.1, $threshold));
        return (float)($responseKeys["score"] ?? 0) >= $threshold
            ? true
            : app_lang("re_captcha_suspicious_activity");
    }

    private function _is_valid_recaptcha_v2($recaptcha_post_data) {
        $recaptcha_post_data = trim((string)$recaptcha_post_data);
        if ($recaptcha_post_data === "" || strlen($recaptcha_post_data) > 4096) {
            return "missing-input-response";
        }
        $response = $this->_verify_with_provider($recaptcha_post_data);
        if (is_array($response) && !empty($response["success"]) && $this->_hostname_matches($response["hostname"] ?? "")) {
            return true;
        }

        $errors = is_array($response) && is_array($response["error-codes"] ?? null)
            ? $response["error-codes"]
            : [];
        return (string)(end($errors) ?: "bad-request");
    }

    private function _verify_with_provider(string $token): ?array {
        if (!function_exists("curl_init")) {
            return null;
        }
        $curl = curl_init('https://www.google.com/recaptcha/api/siteverify');
        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                "secret" => $this->re_captcha_secret_key,
                "response" => $token,
                "remoteip" => (string)\Config\Services::request()->getIPAddress(),
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_HTTPHEADER => ["Accept: application/json"],
        ];
        if (defined("CURLOPT_PROTOCOLS") && defined("CURLPROTO_HTTPS")) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }
        curl_setopt_array($curl, $options);
        $raw = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if (!is_string($raw) || $status !== 200 || strlen($raw) > 65536) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function _hostname_matches($providerHostname): bool {
        $actual = strtolower(rtrim(trim((string)$providerHostname), "."));
        $expected = strtolower(rtrim(trim((string)(getenv("PODC_RECAPTCHA_EXPECTED_HOSTNAME") ?: "")), "."));
        if ($expected === "") {
            $expected = strtolower(rtrim((string)\Config\Services::request()->getUri()->getHost(), "."));
        }
        return $actual !== "" && $expected !== "" && hash_equals($expected, $actual);
    }
}
