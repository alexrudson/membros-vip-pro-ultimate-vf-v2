<?php
if ( ! defined( 'WPINC' ) ) die;

/**
 * Gerencia o carregamento do template de arquivo personalizado do plugin.
 */
class MVPU_Templates {

    public function __construct() {
        // Usa uma prioridade alta para garantir que seja um dos últimos filtros a rodar.
        add_filter('template_include', [$this, 'override_archive_template'], 99);
    }

    /**
     * Verifica se a visualização atual é a página inicial (que lista posts) ou
     * uma página de arquivo (categoria, tag, etc.) e força o carregamento 
     * do nosso template de resumo personalizado.
     *
     * @param string $template O caminho para o arquivo de template que o WordPress iria carregar.
     * @return string O caminho para o nosso template personalizado ou o template original.
     */
    public function override_archive_template($template) {
        
        // A condição chave: se for a home (blog) OU um arquivo...
        if ( (is_home() || is_archive()) && !is_admin() ) {
            
            // Define o caminho para o nosso ÚNICO template de arquivo.
            $new_template = MVPU_PLUGIN_DIR . 'templates/archive.php';
            
            // Se o nosso arquivo de template realmente existe, usa ele.
            if ( file_exists($new_template) ) {
                return $new_template;
            }
        }

        // Para todas as outras visualizações (posts individuais, páginas), 
        // retorna o template que o tema já iria usar, sem modificação.
        return $template;
    }
}