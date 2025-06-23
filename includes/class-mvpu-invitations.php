<?php
if ( ! defined( 'WPINC' ) ) die;

/**
 * Gerencia o fluxo de convites, registro (via shortcode) e expiração.
 */
class MVPU_Invitations {
    
    public function __construct() {
        // Registra o shortcode
        add_shortcode('convite_pro_vip_form', [ $this, 'render_registration_shortcode' ]);
        
        // Hook para processar o formulário
        add_action('init', [ $this, 'process_registration_submission' ]);

        // Hook do Cron para expiração
        add_action( 'mvpu_daily_expiration_check', [ $this, 'run_expiration_check' ] );
    }

    /**
     * Renderiza o formulário de registro através do shortcode.
     */
    public function render_registration_shortcode() {
        // O shortcode só funciona se um grupo_id for passado na URL
        if ( ! isset( $_GET['grupo_id'] ) ) {
            return '<p class="woocommerce-info">' . __( 'Link de convite inválido. O ID do grupo não foi encontrado.', 'membros-vip-pro' ) . '</p>';
        }

        $group_id = absint( $_GET['grupo_id'] );
        if ( ! $group_id || get_post_type( $group_id ) !== 'membro_vip_grupo' ) {
            return '<p class="woocommerce-error">' . __( 'Link de convite inválido ou expirado.', 'membros-vip-pro' ) . '</p>';
        }

        // Inicia o buffer de saída para capturar o HTML
        ob_start();

        if ( isset( $_GET['reg_error'] ) ) {
            echo '<div class="woocommerce-error" role="alert">';
            echo '<ul><li>' . esc_html( self::get_error_message( $_GET['reg_error'] ) ) . '</li></ul>';
            echo '</div>';
        }

        echo '<h3>' . __( 'Crie sua conta para solicitar acesso', 'membros-vip-pro' ) . '</h3>';
        echo '<p>' . __( 'Sua inscrição será revisada por um administrador.', 'membros-vip-pro' ) . '</p>';
        ?>
        <form method="POST" class="register" action="">
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="reg_username"><?php _e( 'Nome de usuário', 'membros-vip-pro' ); ?>&nbsp;<span class="required">*</span></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" required>
            </p>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="reg_email"><?php _e( 'E-mail', 'membros-vip-pro' ); ?>&nbsp;<span class="required">*</span></label>
                <input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" required>
            </p>
            <input type="hidden" name="group_id" value="<?php echo esc_attr( $group_id ); ?>">
            <?php wp_nonce_field( 'mvpu_register_action', 'mvpu_register_nonce' ); ?>
            <p class="woocommerce-form-row form-row">
                <input type="submit" class="woocommerce-Button button" name="mvpu_register" value="<?php _e( 'Registrar', 'membros-vip-pro' ); ?>">
            </p>
        </form>
        <?php
        
        // Retorna o conteúdo do buffer
        return ob_get_clean();
    }
    
    /**
     * Processa os dados do formulário de registro.
     */
    public function process_registration_submission() {
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['mvpu_register_nonce'] ) || ! wp_verify_nonce( $_POST['mvpu_register_nonce'], 'mvpu_register_action' ) ) {
            return;
        }

        $username = isset( $_POST['username'] ) ? sanitize_user( $_POST['username'] ) : '';
        $email    = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
        $group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
        
        $errors = [];
        if ( empty( $username ) || empty( $email ) || empty( $group_id ) ) $errors[] = 'empty_fields';
        if ( username_exists( $username ) ) $errors[] = 'username_exists';
        if ( email_exists( $email ) ) $errors[] = 'email_exists';
        if ( ! is_email( $email ) ) $errors[] = 'invalid_email';
        if ( get_post_type( $group_id ) !== 'membro_vip_grupo' ) $errors[] = 'invalid_group';

        $redirect_url = wp_get_referer() ? wp_get_referer() : home_url();

        if ( ! empty( $errors ) ) {
            wp_redirect( add_query_arg( ['reg_error' => $errors[0]], $redirect_url ) );
            exit;
        }

        $password = wp_generate_password();
        $user_id = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            wp_redirect( add_query_arg( ['reg_error' => $user_id->get_error_code()], $redirect_url ) );
            exit;
        }

        update_user_meta( $user_id, '_mvpu_status', 'pending' );
        update_user_meta( $user_id, '_mvpu_pending_group', $group_id );
        wp_new_user_notification( $user_id, null, 'both' );

        $login_url = wp_login_url();
        wp_redirect( add_query_arg( 'registration_success', 'true', $login_url ) );
        exit;
    }
    
    /**
     * Tarefa diária do Cron para verificar e expirar membros.
     */
    public function run_expiration_check() {
        $users = get_users([
            'meta_key'   => '_mvpu_status',
            'meta_value' => 'confirmed',
            'fields'     => 'ID',
        ]);
        
        if ( empty( $users ) ) return;

        $today = new DateTime();
        $today->setTime( 0, 0, 0 );

        foreach ( $users as $user_id ) {
            $expiration_date_str = get_user_meta( $user_id, '_mvpu_expiration_date', true );
            if ( empty( $expiration_date_str ) ) continue;

            $expiration_date = new DateTime( $expiration_date_str );
            $expiration_date->setTime( 0, 0, 0 );

            if ( $today > $expiration_date ) {
                update_user_meta( $user_id, '_mvpu_status', 'expired' );
                delete_user_meta( $user_id, '_membros_vip_pro_grupos' );
            }
        }
    }

    private static function get_error_message( $code ) {
        $messages = [
            'empty_fields'    => __( 'Todos os campos são obrigatórios.', 'membros-vip-pro' ),
            'username_exists' => __( 'Este nome de usuário já está em uso. Por favor, escolha outro.', 'membros-vip-pro' ),
            'email_exists'    => __( 'Este e-mail já está cadastrado. Você pode tentar fazer login.', 'membros-vip-pro' ),
            'invalid_email'   => __( 'Por favor, insira um endereço de e-mail válido.', 'membros-vip-pro' ),
            'invalid_group'   => __( 'O grupo de convite é inválido.', 'membros-vip-pro' ),
        ];
        return $messages[ $code ] ?? __( 'Ocorreu um erro desconhecido durante o registro.', 'membros-vip-pro' );
    }
}