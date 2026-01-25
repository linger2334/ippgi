<?php

class SWPM_SL_Auth_Facebook extends SWPM_SL_Auth {

    public function __construct() {
        $enable_facebook_login = SWPM_SL_Utils::get_settings('enable_facebook_login');
        if (!empty($enable_facebook_login)) {
            $this->handle_facebook_login();
        }
    }

    public function handle_facebook_login() {
        // Check if any error (access permission denied).
        if (isset($_GET['error'])) {
            if ($_GET['error'] == 'access_denied') {
                SWPM_SL_Auth_Response::set(__('Access denied from facebook!', 'simple-membership'), SWPM_SL_Auth_Response::ERROR);
            } else {
                SWPM_SL_Auth_Response::set(__('Facebook authentication failed!', 'simple-membership'), SWPM_SL_Auth_Response::ERROR);
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
            $user_info = $this->retrieve_facebook_user_info($_GET['code']);
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

        $user_id = isset($user_info->id) ? $user_info->id : '';

        $this->auth_data = new SWPM_SL_Auth_Data();
        $this->auth_data->set_provider_user_id($user_id);
        $this->auth_data->set_provider('facebook');
        $this->auth_data->set_email($email);
        $this->auth_data->set_name($name);
        $this->auth_data->set_user_name($username);
        $this->auth_data->set_remember_me($remember);
        $this->auth_data->set_referer_url($referer_url);

        $this->authenticate();
    }

    public function retrieve_facebook_user_info($code) {
        $app_id     = SWPM_SL_Utils::get_settings('facebook_app_id');
        $app_secret = SWPM_SL_Utils::get_settings('facebook_app_secret');
        $redirect_uri = self::auth_redirect_url();

        /**
         * Exchange the authorization code for an access token
         */

        $token_url = "https://graph.facebook.com/v23.0/oauth/access_token?"
            . "client_id={$app_id}"
            . "&redirect_uri=" . urlencode($redirect_uri)
            . "&client_secret={$app_secret}"
            . "&code={$code}";

        $ch = curl_init($token_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);

        $params = json_decode($response, true);

        if (isset($params['error'])) {
            throw new \Exception($params['error']['message'], $params['error']['code']);
        }

        if (!isset($params['access_token'])) {
            throw new \Exception("No access token received from Facebook.");
        }

        $access_token = $params['access_token'];

        /**
         * Use the access token to get user info
         */

        $graph_url = "https://graph.facebook.com/me?fields=id,name,email&access_token={$access_token}";
        
        $ch = curl_init($graph_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $user_response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);

        $user = json_decode($user_response);

        if (isset($user->error)) {
            throw new \Exception($user->error['message']);
        }

        return $user;
    }

    /**
     * Constructs authentication url for facebook
     *
     * @param boolean $remember_me 
     * @param string $referer_url The url of form where the authentication request was initiated.
     * 
     * @return string The authentication url.
     */
    public static function generate_authentication_url( $remember_me = false, $referer_url = '' ) {
        $app_id     = SWPM_SL_Utils::get_settings('facebook_app_id');
        $redirect_uri = urlencode(self::auth_redirect_url());

        $state = base64_encode(json_encode(array(
            'nonce'       => wp_create_nonce('swpm_sl_google_auth'),
            'referer_url' => $referer_url,
            'remember_me' => $remember_me,
        )));

        $scope = "email,public_profile"; // requested permissions

        $login_url = "https://www.facebook.com/v23.0/dialog/oauth?client_id={$app_id}&redirect_uri={$redirect_uri}&state={$state}&scope={$scope}&auth_type=reauthenticate";

        return $login_url;
    }

    public static function auth_redirect_url() {
        $redirect_url = SwpmSettings::get_instance()->get_value('login-page-url', '');

        if (!empty($redirect_url)) {
            $redirect_url = add_query_arg(
                array(
                    'swpm-facebook-login' => 1,
                ),
                trailingslashit( sanitize_url($redirect_url) ) // the trailing slash is important for facebook.
            );
        }
        
        return $redirect_url;
    }
}
