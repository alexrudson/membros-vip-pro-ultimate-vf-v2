<?php
if ( ! defined( 'WPINC' ) ) die;

class MVPU_CPT {
    public function __construct() {
        add_action( 'init', [ $this, 'register_all_cpts' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_group_metaboxes' ] );
        add_action( 'save_post_membro_vip_grupo', [ $this, 'save_group_metaboxes' ] );
    }
    public function register_all_cpts() {
        $this->register_grupos_cpt();
        $this->register_arquivos_cpt();
    }
    public function register_grupos_cpt() {
        $labels = [ 'name' => 'Grupos VIP', 'singular_name' => 'Grupo VIP', 'menu_name' => 'Grupos VIP', 'all_items' => 'Todos os Grupos', 'add_new_item' => 'Adicionar Novo Grupo', 'add_new' => 'Adicionar Novo', 'edit_item' => 'Editar Grupo', 'update_item' => 'Atualizar Grupo' ];
        $args = [ 'label' => 'Grupo VIP', 'labels' => $labels, 'supports' => [ 'title', 'editor' ], 'hierarchical' => false, 'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'menu_position' => 25, 'menu_icon' => 'dashicons-groups', 'can_export' => true, 'has_archive' => false, 'exclude_from_search' => true, 'publicly_queryable' => false, 'capability_type' => 'post', 'rewrite' => false ];
        register_post_type( 'membro_vip_grupo', $args );
    }
    public function register_arquivos_cpt() {
        $labels = [ 'name' => 'Arquivos VIP', 'singular_name' => 'Arquivo VIP', 'menu_name' => 'Arquivos VIP', 'all_items' => 'Todos os Arquivos', 'add_new_item' => 'Adicionar Novo Arquivo', 'add_new' => 'Adicionar Novo', 'edit_item' => 'Editar Arquivo', 'update_item' => 'Atualizar Arquivo' ];
        $args = [ 'label' => 'Arquivo VIP', 'description' => 'Arquivos com acesso restrito por grupo.', 'labels' => $labels, 'supports' => ['title', 'editor'], 'hierarchical' => false, 'public' => false, 'show_ui' => true, 'show_in_menu' => 'edit.php?post_type=membro_vip_grupo', 'show_in_admin_bar' => true, 'can_export' => true, 'exclude_from_search' => true, 'publicly_queryable' => false, 'capability_type' => 'post' ];
        register_post_type( 'membro_vip_arquivo', $args );
    }
    public function add_group_metaboxes() {
        add_meta_box('mvpu_invitation_settings', 'Configurações de Convite', [ $this, 'render_invitation_metabox' ], 'membro_vip_grupo', 'side', 'high');
    }
    
    // *** FUNÇÃO CORRIGIDA ***
    public function render_invitation_metabox( $post ) {
        wp_nonce_field( 'mvpu_save_invitation_data', 'mvpu_invitation_nonce' );
        
        $validity = get_post_meta( $post->ID, '_mvpu_access_validity', true );
        $validity = $validity ? (int) $validity : 365;
        
        // Lê as configurações para encontrar a página de registro
        $settings = get_option('mvpu_settings');
        $registration_page_id = $settings['registration_page_id'] ?? 0;
        
        if ( !$registration_page_id ) {
            echo '<p style="color:red;"><strong>Aviso:</strong> Por favor, selecione uma "Página de Registro de Convite" nas <a href="edit.php?post_type=membro_vip_grupo&page=mvpu-settings">configurações do plugin</a> para que o link de convite seja gerado corretamente.</p>';
            return;
        }

        // Gera o link usando a URL da página selecionada
        $base_url = get_permalink($registration_page_id);
        $invitation_link = add_query_arg('grupo_id', $post->ID, $base_url);
        ?>
        <p>
            <label for="mvpu_access_validity"><strong>Validade do Acesso (dias):</strong></label><br>
            <input type="number" id="mvpu_access_validity" name="mvpu_access_validity" value="<?php echo esc_attr( $validity ); ?>" min="1" step="1" style="width:100%;">
        </p>
        <p>
            <strong>Link de Convite:</strong><br>
            <input type="text" value="<?php echo esc_url( $invitation_link ); ?>" readonly style="width:100%;" onfocus="this.select();">
            <small>Use este link para o cadastro de novos membros neste grupo.</small>
        </p>
        <?php
    }

    public function save_group_metaboxes( $post_id ) {
        if ( ! isset( $_POST['mvpu_invitation_nonce'] ) || ! wp_verify_nonce( $_POST['mvpu_invitation_nonce'], 'mvpu_save_invitation_data' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        if ( isset( $_POST['mvpu_access_validity'] ) ) {
            update_post_meta( $post_id, '_mvpu_access_validity', absint( $_POST['mvpu_access_validity'] ) );
        }
    }
}