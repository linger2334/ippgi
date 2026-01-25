<?php

class SWPM_SL_Auth_Google extends SWPM_SL_Auth {

    public function __construct() {
        $enable_google_login = SWPM_SL_Utils::get_settings('enable_google_login');
        if (!empty($enable_google_login)) {
            $this->handle_google_login();
        }
    }

    public function handle_google_login() {
        // Check if any error (access permission denied).
        if (isset($_GET['error'])) {
            if ($_GET['error'] == 'access_denied') {
                SWPM_SL_Auth_Response::set(__('Access denied from google!', 'simple-membership'), false);
            } else {
                SWPM_SL_Auth_Response::set(__('Google authentication failed!', 'simple-membership'), false);
            }

            $this->redirect_back();
        }

        if (! isset($_GET['code'])) {
            return;
        }

        if (! isset($_GET['state'])) {
            return;
        }

        $additional_data_encoded = $_GET['state'];
        $additional_data = json_decode(base64_decode($additional_data_encoded), true);
        if (! isset($additional_data['nonce']) || ! wp_verify_nonce($additional_data['nonce'], 'swpm_sl_google_auth')) {
            wp_die(__('Nonce Verification Failed', 'simple-membership'));
            return;
        }

        $referer_url = '';
        // Get the url from where the login request was initiated.
        if (isset($additional_data['referer_url'])) {
            $referer_url = $additional_data['referer_url'];
        }

        try {
            $user_info = $this->retrieve_google_user_info($_GET['code']);
        } catch (\Exception $e) {
            SWPM_SL_Utils::log_auth_debug($e->getMessage());
            SWPM_SL_Auth_Response::set($e->getMessage(), SWPM_SL_Auth_Response::ERROR);
            $this->redirect_back();
        }

        $email = isset($user_info->email) ? $user_info->email : '';
        if (empty($email) || ! is_email($email)) {
            SWPM_SL_Utils::log_auth_debug(__METHOD__ . " - invalid email provided: " . $email);
            return;
        }

        $username = explode('@', $email)[0];
        $name = isset($user_info->name) ? $user_info->name : '';

        $remember = false;
        if (isset($additional_data['remember_me'])) {
            $remember = boolval($additional_data['remember_me']);
        }

        $user_id = isset($user_info->sub) ? $user_info->sub : '';

        $this->auth_data = new SWPM_SL_Auth_Data();
        $this->auth_data->set_provider_user_id($user_id);
        $this->auth_data->set_provider('google');
        $this->auth_data->set_email($email);
        $this->auth_data->set_name($name);
        $this->auth_data->set_user_name($username);
        $this->auth_data->set_remember_me($remember);
        $this->auth_data->set_referer_url($referer_url);
        
        $this->authenticate();
    }

    public function retrieve_google_user_info($code) {
        $client_id     = SWPM_SL_Utils::get_settings('google_client_id');
        $client_secret = SWPM_SL_Utils::get_settings('google_client_secret');
        $redirect_uri  = self::auth_redirect_url();

        /**
         * Exchange the authorization code for an access token
         */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
            'code'          => $code,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => $redirect_uri,
            'grant_type'    => 'authorization_code',
        )));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $token_response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);

        $token_data = json_decode($token_response, true);

        if (! isset($token_data['access_token']) || isset($token_data['error'])) {
            $error_msg = isset($token_data['error_description']) ? $token_data['error_description'] : __('Google access token could not be retrieved.', 'simple-membership');
            SWPM_SL_Utils::log_auth_debug($error_msg, false);
            throw new \Exception($error_msg);
        }

        $access_token = $token_data['access_token'];

        /**
         * Use the access token to get user info
         */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v3/userinfo');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $access_token,
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $user_info_json = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);

        $user_info = json_decode($user_info_json);

        return $user_info;
    }

    /**
     * Constructs authentication url for google
     *
     * @param boolean $remember_me 
     * @param string $referer_url The url of form where the authentication request was initiated.
     * 
     * @return string The authentication url.
     */
    public static function generate_authentication_url($remember_me = false, $referer_url = '') {
        $client_id     = SWPM_SL_Utils::get_settings('google_client_id');
        $redirect_uri  = self::auth_redirect_url();

        $state = [
            'nonce'       => wp_create_nonce('swpm_sl_google_auth'),
            'referer_url' => $referer_url,
            'remember_me' => $remember_me,
        ];

        // Encode state
        $state_param = base64_encode(json_encode($state));

        // Scopes
        $scope = urlencode('email profile');

        // Authorization endpoint
        $auth_url = "https://accounts.google.com/o/oauth2/v2/auth" .
            "?response_type=code" .
            "&client_id=" . urlencode($client_id) .
            "&redirect_uri=" . urlencode($redirect_uri) .
            "&scope={$scope}" .
            "&state=" . urlencode($state_param) .
            "&prompt=select_account". // to force account selection
            "&access_type=online"; // or offline if you need refresh token

        return $auth_url;
    }

    public static function auth_redirect_url() {
        $redirect_url = SwpmSettings::get_instance()->get_value('login-page-url', '');

        if (!empty($redirect_url)) {
            $redirect_url = add_query_arg(
                array(
                    'swpm-google-login' => 1,
                ),
                trailingslashit(sanitize_url($redirect_url))
            );
        }

        return $redirect_url;
    }
}
