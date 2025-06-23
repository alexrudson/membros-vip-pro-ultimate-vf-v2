<?php
if ( ! defined( 'WPINC' ) ) die;

/**
 * Adiciona e gerencia os campos e ações no perfil do usuário.
 */
class MVPU_User_Profile {

    public function __construct() {
        // Hooks do Perfil de Usuário
        add_action( 'show_user_profile', [ $this, 'render_user_metaboxes' ] );
        add_action( 'edit_user_profile', [ $this, 'render_user_metaboxes' ] );
        add_action( 'personal_options_update', [ $this, 'save_user_groups_metabox' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_user_groups_metabox' ] );
        add_action( 'admin_init', [ $this, 'handle_approval_action' ] );

        // Hooks da Lista de Usuários (Colunas)
        add_filter( 'manage_users_columns', [ $this, 'add_status_column' ] );
        add_filter( 'manage_users_custom_column', [ $this, 'render_status_column' ], 10, 3 );
        
        // Hooks de Notificação
        add_action( 'admin_notices', [ $this, 'show_approval_notice' ] );
        add_action( 'admin_notices', [ $this, 'show_pending_users_notice' ] ); // Reintegrado
        add_action( 'admin_menu', [ $this, 'add_pending_count_to_menu' ] );
    }

    /**
     * Exibe um aviso no painel se houver usuários pendentes.
     */
    public function show_pending_users_notice() {
        $current_screen = get_current_screen();
        if ( !current_user_can('manage_options') || $current_screen->id !== 'users' ) {
            return; // Mostra o aviso apenas na página de usuários
        }

        $pending_count = $this->get_pending_users_count();

        if ($pending_count > 0) {
            $message = sprintf(
                _n(
                    'Você tem <strong>%d</strong> novo membro aguardando aprovação.',
                    'Você tem <strong>%d</strong> novos membros aguardando aprovação.',
                    $pending_count, 'membros-vip-pro'
                ), $pending_count
            );
            $users_page_link = admin_url('users.php?role=subscriber&meta_key=_mvpu_status&meta_value=pending'); // Link mais específico
            printf(
                '<div class="notice notice-info is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
                $message, esc_url($users_page_link), __('Verificar agora &raquo;', 'membros-vip-pro')
            );
        }
    }

    /**
     * Adiciona um balão com a contagem de usuários pendentes ao menu "Usuários".
     */
    public function add_pending_count_to_menu() {
        if ( !current_user_can('manage_options') ) return;

        $pending_count = $this->get_pending_users_count();

        if ($pending_count > 0) {
            global $menu;
            foreach ($menu as $key => $value) {
                if ($value[2] == 'users.php') {
                    $menu[$key][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . $pending_count . '</span></span>';
                    return;
                }
            }
        }
    }

    /**
     * Renderiza a coluna de Status VIP com cores.
     */
    public function render_status_column( $value, $column_name, $user_id ) {
        if ( 'mvpu_status' === $column_name ) {
            $status = get_user_meta( $user_id, '_mvpu_status', true );
            $status_text = self::get_status_text( $status );

            $color = '#333'; // Cor padrão
            if ($status === 'pending') {
                $color = '#dc3232'; // Vermelho do WordPress
            } elseif ($status === 'confirmed') {
                $color = '#46b450'; // Verde do WordPress
            }

            return '<strong style="color: ' . $color . ';">' . esc_html($status_text) . '</strong>';
        }
        return $value;
    }

    /**
     * Função auxiliar para contar usuários pendentes e evitar repetição de código.
     */
    private function get_pending_users_count() {
        // Usa cache estático para evitar múltiplas queries na mesma página
        static $count;
        if (isset($count)) {
            return $count;
        }

        $user_query = new WP_User_Query([
            'meta_key' => '_mvpu_status',
            'meta_value' => 'pending',
            'count_total' => true,
        ]);
        $count = $user_query->get_total();
        return $count;
    }

    // --- Outros métodos da classe (sem alterações) ---

    public function render_user_metaboxes( $user ) {
        if ( ! current_user_can( 'edit_users' ) ) return;
        $this->render_access_management_metabox( $user );
        $this->render_user_groups_metabox( $user );
    }

    private function render_access_management_metabox( $user ) {
        $status = get_user_meta( $user->ID, '_mvpu_status', true );
        $status_text = self::get_status_text( $status );
        $expiration_date = get_user_meta( $user->ID, '_mvpu_expiration_date', true );
        ?>
        <h2><?php _e( 'Membros VIP Pro - Gerenciamento', 'membros-vip-pro' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label><?php _e( 'Status do Acesso', 'membros-vip-pro' ); ?></label></th>
                <td>
                    <p><strong><?php echo esc_html( $status_text ); ?></strong></p>
                    <?php if ( $status === 'confirmed' && $expiration_date ) : ?>
                        <p><?php _e( 'Expira em:', 'membros-vip-pro' ); ?> <?php echo date_i18n( get_option( 'date_format' ), strtotime( $expiration_date ) ); ?></p>
                    <?php endif; ?>
                    <?php if ( $status === 'pending' ) : 
                        $pending_group_id = get_user_meta( $user->ID, '_mvpu_pending_group', true );
                        if ($pending_group_id) {
                            $pending_group_name = get_the_title($pending_group_id);
                            echo '<p><strong>' . __('Pendente de aprovação para o grupo:', 'membros-vip-pro') . '</strong> ' . esc_html($pending_group_name) . '</p>';
                        }
                        $approval_link = wp_nonce_url(
                            add_query_arg( [ 'user_id' => $user->ID, 'mvpu_action' => 'approve' ], admin_url( 'user-edit.php' ) ),
                            'mvpu_approve_user_' . $user->ID
                        );
                    ?>
                        <a href="<?php echo esc_url( $approval_link ); ?>" class="button button-primary"><?php _e( 'Aprovar Acesso', 'membros-vip-pro' ); ?></a>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    private function render_user_groups_metabox( $user ) {
        $all_groups_query = new WP_Query(['post_type' => 'membro_vip_grupo', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC']);
        $user_groups = get_user_meta( $user->ID, '_membros_vip_pro_grupos', true );
        $user_groups = is_array( $user_groups ) ? $user_groups : [];
        ?>
        <h3><?php _e( 'Grupos VIP do Usuário', 'membros-vip-pro' ); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="mvpu_user_groups"><?php _e( 'Associações', 'membros-vip-pro' ); ?></label></th>
                <td>
                    <?php
                    wp_nonce_field( 'mvpu_save_user_groups', 'mvpu_user_groups_nonce' );
                    if ( $all_groups_query->have_posts() ) {
                        while ( $all_groups_query->have_posts() ) {
                            $all_groups_query->the_post();
                            $group_id = get_the_ID();
                            $checked = in_array( $group_id, $user_groups ) ? 'checked="checked"' : '';
                            echo '<label><input type="checkbox" name="mvpu_user_groups[]" value="' . esc_attr( $group_id ) . '" ' . $checked . '> ' . esc_html( get_the_title() ) . '</label><br>';
                        }
                        wp_reset_postdata();
                    } else {
                        _e( 'Nenhum grupo VIP foi criado ainda.', 'membros-vip-pro' );
                    }
                    ?>
                    <p class="description"><?php _e( 'Gerencie manualmente os grupos do usuário.', 'membros-vip-pro' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_user_groups_metabox( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) return;
        if ( ! isset( $_POST['mvpu_user_groups_nonce'] ) || ! wp_verify_nonce( $_POST['mvpu_user_groups_nonce'], 'mvpu_save_user_groups' ) ) return;
        $selected_groups = isset( $_POST['mvpu_user_groups'] ) ? (array) $_POST['mvpu_user_groups'] : [];
        update_user_meta( $user_id, '_membros_vip_pro_grupos', array_map( 'intval', $selected_groups ) );
    }

    public function handle_approval_action() {
        if ( ! current_user_can( 'edit_users' ) || ! isset( $_GET['mvpu_action'] ) || $_GET['mvpu_action'] !== 'approve' || ! isset( $_GET['user_id'] ) || ! isset( $_GET['_wpnonce'] ) ) return;
        $user_id = absint( $_GET['user_id'] );
        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'mvpu_approve_user_' . $user_id ) ) wp_die( 'Falha de segurança.' );
        $pending_group_id = get_user_meta( $user_id, '_mvpu_pending_group', true );
        if ( ! $pending_group_id ) return;
        $user_groups = get_user_meta( $user_id, '_membros_vip_pro_grupos', true );
        $user_groups = is_array( $user_groups ) ? $user_groups : [];
        if ( ! in_array( $pending_group_id, $user_groups ) ) {
            $user_groups[] = $pending_group_id;
            update_user_meta( $user_id, '_membros_vip_pro_grupos', $user_groups );
        }
        update_user_meta( $user_id, '_mvpu_status', 'confirmed' );
        $validity = get_post_meta( $pending_group_id, '_mvpu_access_validity', true );
        $validity_days = $validity ? absint( $validity ) : 365;
        $expiration_date = date( 'Y-m-d H:i:s', strtotime( "+{$validity_days} days" ) );
        update_user_meta( $user_id, '_mvpu_expiration_date', $expiration_date );
        update_user_meta( $user_id, '_mvpu_join_date_' . $pending_group_id, date( 'Y-m-d H:i:s' ) );
        delete_user_meta( $user_id, '_mvpu_pending_group' );
        wp_redirect( add_query_arg( 'mvpu_approved', 'true', get_edit_user_link( $user_id ) ) );
        exit;
    }

    public function add_status_column( $columns ) {
        $columns['mvpu_status'] = __( 'Status VIP', 'membros-vip-pro' );
        return $columns;
    }

    public function show_approval_notice() {
        if ( isset( $_GET['mvpu_approved'] ) && $_GET['mvpu_approved'] === 'true' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Usuário aprovado com sucesso! Acesso concedido.', 'membros-vip-pro' ) . '</p></div>';
        }
    }
    
    public static function get_status_text( $status_key ) {
        switch ( $status_key ) {
            case 'pending': return __( 'Pendente', 'membros-vip-pro' );
            case 'confirmed': return __( 'Confirmado', 'membros-vip-pro' );
            case 'expired': return __( 'Expirado', 'membros-vip-pro' );
            default: return '—';
        }
    }
}