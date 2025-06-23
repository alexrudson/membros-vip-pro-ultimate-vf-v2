<?php
if (!defined('WPINC')) {
    die;
}

/**
 * Gerencia a restrição de conteúdo por categoria, incluindo páginas de arquivo.
 */
class MVPU_Content_Restriction {

    private $all_restricted_categories_cache;

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_category_restriction_metabox']);
        add_action('save_post_membro_vip_grupo', [$this, 'save_category_restriction_data']);
        add_action('template_redirect', [$this, 'check_content_access']);
    }

    public function add_category_restriction_metabox() {
        add_meta_box(
            'mvpu_category_restriction_mb',
            __('Restringir Acesso a Categorias', 'membros-vip-pro'),
            [$this, 'render_category_restriction_metabox'],
            'membro_vip_grupo',
            'advanced',
            'high'
        );
    }

    public function render_category_restriction_metabox($post) {
        wp_nonce_field('mvpu_save_category_restriction', 'mvpu_category_restriction_nonce');
        $saved_categories = get_post_meta($post->ID, '_mvpu_restricted_categories', true) ?: [];
        $all_categories = get_categories(['hide_empty' => false]);
        echo '<p>' . __('Marque as categorias de posts que serão exclusivas para membros deste grupo.', 'membros-vip-pro') . '</p>';
        echo '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">';
        foreach ($all_categories as $category) {
            $checked = in_array($category->term_id, $saved_categories) ? 'checked' : '';
            echo '<label><input type="checkbox" name="mvpu_restricted_categories[]" value="' . esc_attr($category->term_id) . '" ' . $checked . '> ' . esc_html($category->name) . '</label><br>';
        }
        echo '</div>';
    }

    public function save_category_restriction_data($post_id) {
        if (!isset($_POST['mvpu_category_restriction_nonce']) || !wp_verify_nonce($_POST['mvpu_category_restriction_nonce'], 'mvpu_save_category_restriction')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $cats = isset($_POST['mvpu_restricted_categories']) ? array_map('intval', (array)$_POST['mvpu_restricted_categories']) : [];
        update_post_meta($post_id, '_mvpu_restricted_categories', $cats);
    }

    /**
     * Lógica de verificação de acesso atualizada para incluir páginas de categoria.
     */
    public function check_content_access() {
        if (is_admin() || current_user_can('manage_options')) {
            return;
        }

        $user_id = get_current_user_id();
        $all_restricted = $this->get_all_restricted_categories();

        // Verificação para posts individuais
        if (is_singular('post')) {
            $post_categories = wp_get_post_categories(get_the_ID());
            if (empty($post_categories) || empty(array_intersect($post_categories, $all_restricted))) {
                return; // Post não está em categoria restrita
            }
            if (!$user_id || !$this->user_has_category_access($user_id, $post_categories)) {
                $this->redirect_user();
            }
        }
        
        // *** NOVA VERIFICAÇÃO PARA PÁGINAS DE ARQUIVO DE CATEGORIA ***
        if (is_category()) {
            $current_cat_id = get_queried_object_id();
            
            // Se a categoria atual não está na lista de todas as categorias restritas, permite o acesso.
            if (!in_array($current_cat_id, $all_restricted)) {
                return;
            }

            // A categoria é restrita, agora verifica a permissão do usuário.
            // O segundo parâmetro é um array com apenas o ID da categoria atual.
            if (!$user_id || !$this->user_has_category_access($user_id, [$current_cat_id])) {
                $this->redirect_user();
            }
        }
    }
    
    private function user_has_category_access($user_id, $post_or_page_categories) {
        $user_groups = get_user_meta($user_id, '_membros_vip_pro_grupos', true) ?: [];
        if (empty($user_groups)) {
            return false;
        }
        foreach ($user_groups as $group_id) {
            $group_allowed_cats = get_post_meta($group_id, '_mvpu_restricted_categories', true) ?: [];
            if (!empty(array_intersect($post_or_page_categories, $group_allowed_cats))) {
                return true;
            }
        }
        return false;
    }

    private function get_all_restricted_categories() {
        if (isset($this->all_restricted_categories_cache)) {
            return $this->all_restricted_categories_cache;
        }
        $cats = [];
        $query = new WP_Query(['post_type' => 'membro_vip_grupo', 'posts_per_page' => -1, 'fields' => 'ids']);
        if (!empty($query->posts)) {
            foreach ($query->posts as $id) {
                if (is_array($meta = get_post_meta($id, '_mvpu_restricted_categories', true))) {
                    $cats = array_merge($cats, $meta);
                }
            }
        }
        $this->all_restricted_categories_cache = array_unique($cats);
        return $this->all_restricted_categories_cache;
    }

    public function redirect_user() {
        $settings = get_option('mvpu_settings');
        $redirect_page_id = $settings['access_denied_page_id'] ?? 0;
        $redirect_url = $redirect_page_id ? get_permalink($redirect_page_id) : home_url();
        wp_redirect(add_query_arg('restricted_access', 'true', $redirect_url));
        exit();
    }
}