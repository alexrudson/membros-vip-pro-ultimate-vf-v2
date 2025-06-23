<?php
if ( ! defined( 'WPINC' ) ) die;

// Inclui a classe WP_List_Table se ela não estiver disponível no contexto
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Cria a tabela de lista para a página de Relatório de Membros.
 */
class MVPU_Members_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => __( 'Membro', 'membros-vip-pro' ),
            'plural'   => __( 'Membros', 'membros-vip-pro' ),
            'ajax'     => false
        ] );
    }

    /**
     * Define as colunas da tabela.
     */
    public function get_columns() {
        return [
            'username'         => __( 'Nome de Usuário', 'membros-vip-pro' ),
            'email'            => __( 'E-mail', 'membros-vip-pro' ),
            'active_groups'    => __( 'Grupos Ativos', 'membros-vip-pro' ),
            'join_date'        => __( 'Data de Ingresso', 'membros-vip-pro' ),
            'expiration_date'  => __( 'Data de Expiração', 'membros-vip-pro' ),
            'status'           => __( 'Status VIP', 'membros-vip-pro' ),
        ];
    }

    /**
     * Define como cada coluna será renderizada.
     */
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'username':
                return '<strong><a href="' . get_edit_user_link( $item->ID ) . '">' . esc_html( $item->user_login ) . '</a></strong>';
            
            case 'email':
                return esc_html( $item->user_email );

            case 'active_groups':
                $groups_meta = get_user_meta( $item->ID, '_membros_vip_pro_grupos', true );
                if ( empty( $groups_meta ) ) return '—';
                $group_names = array_map( 'get_the_title', $groups_meta );
                return implode( ', ', $group_names );

            case 'join_date':
                $groups = get_user_meta( $item->ID, '_membros_vip_pro_grupos', true );
                if (!empty($groups)) {
                    // Pega a data de ingresso do primeiro grupo para simplificar.
                    // Uma lógica mais complexa poderia mostrar datas para cada grupo.
                    $join_date_str = get_user_meta($item->ID, '_mvpu_join_date_' . $groups[0], true);
                    return $join_date_str ? date_i18n( get_option( 'date_format' ), strtotime( $join_date_str ) ) : '—';
                }
                return '—';

            case 'expiration_date':
                $exp_date_str = get_user_meta( $item->ID, '_mvpu_expiration_date', true );
                return $exp_date_str ? date_i18n( get_option( 'date_format' ), strtotime( $exp_date_str ) ) : '—';

            case 'status':
                $status = get_user_meta( $item->ID, '_mvpu_status', true );
                // Chama a função estática da outra classe
                return MVPU_User_Profile::get_status_text( $status );

            default:
                return '—';
        }
    }
    
    /**
     * Prepara os itens para serem exibidos (busca no banco de dados).
     */
    public function prepare_items() {
        $this->_column_headers = [ $this->get_columns(), [], [] ];
        
        $per_page = 20;
        $current_page = $this->get_pagenum();

        $args = [
            'meta_query' => [
                'relation' => 'OR',
                [ 'key' => '_mvpu_status', 'value' => 'confirmed', 'compare' => '=' ],
                [ 'key' => '_mvpu_status', 'value' => 'expired', 'compare' => '=' ]
            ],
            'number' => $per_page,
            'offset' => ( $current_page - 1 ) * $per_page
        ];

        // Aplica o filtro por grupo, se selecionado
        if ( !empty($_REQUEST['group_filter']) ) {
            $group_id = absint($_REQUEST['group_filter']);
            $args['meta_query'][] = [
                'key' => '_membros_vip_pro_grupos',
                'value' => '"' . $group_id . '"',
                'compare' => 'LIKE'
            ];
        }

        $user_query = new WP_User_Query( $args );
        $this->items = $user_query->get_results();

        $this->set_pagination_args( [
            'total_items' => $user_query->get_total(),
            'per_page'    => $per_page
        ] );
    }

    /**
     * Adiciona o dropdown de filtro por grupo acima da tabela.
     */
    public function extra_tablenav( $which ) {
        if ( 'top' !== $which ) return;
        
        $all_groups = new WP_Query(['post_type' => 'membro_vip_grupo', 'posts_per_page' => -1]);
        $current_filter = !empty($_REQUEST['group_filter']) ? absint($_REQUEST['group_filter']) : '';
        ?>
        <div class="alignleft actions">
            <label for="group_filter" class="screen-reader-text">Filtrar por grupo</label>
            <select name="group_filter" id="group_filter">
                <option value="">Todos os grupos</option>
                <?php
                if ($all_groups->have_posts()) {
                    while($all_groups->have_posts()) {
                        $all_groups->the_post();
                        echo '<option value="' . get_the_ID() . '" ' . selected($current_filter, get_the_ID(), false) . '>' . get_the_title() . '</option>';
                    }
                    wp_reset_postdata();
                }
                ?>
            </select>
            <?php submit_button( 'Filtrar', 'secondary', 'do_filter_action', false ); ?>
        </div>
        <?php
    }
}


/**
 * Classe principal para registrar e renderizar a página de relatórios.
 */
class MVPU_Reports {
    public function __construct() {
        add_action('admin_menu', [ $this, 'add_reports_page' ]);
    }

    public function add_reports_page() {
        add_submenu_page(
            'edit.php?post_type=membro_vip_grupo',
            'Relatório de Membros',
            'Relatório de Membros',
            'manage_options',
            'mvpu-reports',
            [ $this, 'render_reports_page' ]
        );
    }

    public function render_reports_page() {
        $list_table = new MVPU_Members_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Relatório de Membros VIP</h1>
            <hr class="wp-header-end">
            <form id="members-filter" method="get">
                <input type="hidden" name="post_type" value="<?php echo esc_attr($_REQUEST['post_type'] ?? 'membro_vip_grupo') ?>" />
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page'] ?? 'mvpu-reports') ?>" />
                <?php
                $list_table->display();
                ?>
            </form>
        </div>
        <?php
    }
}