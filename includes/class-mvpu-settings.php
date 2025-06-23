<?php
if ( ! defined( 'WPINC' ) ) die;

class MVPU_Settings {
    private $options;
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
        add_action( 'admin_init', [ $this, 'page_init' ] );
    }
    public function add_plugin_page() {
        add_submenu_page('edit.php?post_type=membro_vip_grupo', 'Configurações', 'Configurações', 'manage_options', 'mvpu-settings', [ $this, 'create_admin_page' ]);
    }
    public function create_admin_page() {
        $this->options = get_option( 'mvpu_settings' );
        echo '<div class="wrap"><h1>Configurações do Membros VIP Pro</h1><form method="post" action="options.php">';
        settings_fields( 'mvpu_option_group' );
        do_settings_sections( 'mvpu-setting-admin' );
        submit_button();
        echo '</form></div>';
    }
    public function page_init() {
        register_setting( 'mvpu_option_group', 'mvpu_settings', [ $this, 'sanitize' ] );
        add_settings_section( 'mvpu_setting_section_id', 'Configurações Gerais', null, 'mvpu-setting-admin' );
        add_settings_field( 'registration_page_id', 'Página de Registro de Convite', [ $this, 'registration_page_id_callback' ], 'mvpu-setting-admin', 'mvpu_setting_section_id' );
        add_settings_field( 'access_denied_page_id', 'Página de Acesso Negado', [ $this, 'access_denied_page_id_callback' ], 'mvpu-setting-admin', 'mvpu_setting_section_id' );
        add_settings_field( 'force_login', 'Forçar Login', [ $this, 'force_login_callback' ], 'mvpu-setting-admin', 'mvpu_setting_section_id' );
    }
    public function sanitize( $input ) {
        $new_input = [];
        if ( isset( $input['registration_page_id'] ) ) $new_input['registration_page_id'] = absint( $input['registration_page_id'] );
        if ( isset( $input['access_denied_page_id'] ) ) $new_input['access_denied_page_id'] = absint( $input['access_denied_page_id'] );
        $new_input['force_login'] = isset($input['force_login']) ? 1 : 0;
        return $new_input;
    }
    public function registration_page_id_callback() {
        $page_id = $this->options['registration_page_id'] ?? '';
        wp_dropdown_pages(['name' => 'mvpu_settings[registration_page_id]', 'selected' => $page_id, 'show_option_none' => '— Selecione a página com o shortcode —', 'option_none_value' => '0']);
        echo '<p class="description">Selecione a página onde você colocou o shortcode <code>[convite_pro_vip_form]</code>. Esta página será uma exceção ao "Forçar Login".</p>';
    }
    public function access_denied_page_id_callback() {
        $page_id = $this->options['access_denied_page_id'] ?? '';
        wp_dropdown_pages(['name' => 'mvpu_settings[access_denied_page_id]', 'selected' => $page_id, 'show_option_none' => '— Selecione uma página —', 'option_none_value' => '0']);
    }
    public function force_login_callback() {
        $checked = isset($this->options['force_login']) && $this->options['force_login'] ? 'checked' : '';
        echo "<input type='checkbox' name='mvpu_settings[force_login]' value='1' $checked />";
        echo '<p class="description">Torna todo o site (exceto a página de login e a de registro de convite) acessível apenas para usuários logados.</p>';
    }
}