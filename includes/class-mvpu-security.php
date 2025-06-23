<?php
if ( ! defined( 'WPINC' ) ) die;

/**
 * Gerencia a segurança de acesso: Force Login.
 */
class MVPU_Security {

    public function __construct() {
        $options = get_option('mvpu_settings');
        
        // Ativa a funcionalidade apenas se a opção estiver marcada nas configurações
        if (!empty($options['force_login'])) {
            add_action('template_redirect', [ $this, 'force_login_redirect' ]);
            add_filter('rest_authentication_errors', [ $this, 'restrict_rest_api' ]);
        }
    }

    /**
     * Lógica de redirecionamento.
     */
    public function force_login_redirect() {
        if (is_user_logged_in() || (defined('DOING_AJAX') && DOING_AJAX) || (defined('DOING_CRON') && DOING_CRON) || (defined('WP_CLI') && WP_CLI)) {
            return;
        }
        
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

        // Exceção 1: A própria página de login.
        if (preg_replace('/\?.*/', '', wp_login_url()) === preg_replace('/\?.*/', '', $current_url)) {
            return;
        }

        // Exceção 2: A página de registro de convite selecionada nas configurações.
        $options = get_option('mvpu_settings');
        $registration_page_id = $options['registration_page_id'] ?? 0;
        if ($registration_page_id && is_page($registration_page_id)) {
            return;
        }
        
        // Exceção 3: A página de acesso negado.
        $access_denied_page_id = $options['access_denied_page_id'] ?? 0;
        if ($access_denied_page_id && is_page($access_denied_page_id)) {
            return;
        }

        // Se não for nenhuma exceção, redireciona para a página de login.
        wp_safe_redirect(wp_login_url($current_url), 302);
        exit;
    }

    /**
     * Restringe o acesso à REST API para usuários não logados.
     */
    public function restrict_rest_api( $result ) {
        if ( null === $result && ! is_user_logged_in() ) {
            return new WP_Error( 
                'rest_not_logged_in', 
                __( 'Você não está logado.', 'membros-vip-pro' ), 
                [ 'status' => 401 ]
            );
        }
        return $result;
    }
}