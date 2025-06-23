<?php
/**
 * Template personalizado para exibir a página inicial e os arquivos de categoria
 * em formato de resumo. Carregado pelo plugin Membros VIP Pro Ultimate.
 */

get_header(); // Carrega o cabeçalho do seu tema
?>

<div id="content" class="site-content">
    <div id="primary" class="content-area">
        <main id="main" class="site-main" role="main">

            <?php if ( have_posts() ) : ?>

                <header class="page-header">
                    <?php
                        // Mostra o título da categoria/tag se for uma página de arquivo
                        if ( is_archive() ) {
                            the_archive_title( '<h1 class="page-title">', '</h1>' );
                            the_archive_description( '<div class="taxonomy-description">', '</div>' );
                        }
                    ?>
                </header><!-- .page-header -->

                <?php
                // Início do Loop do WordPress
                while ( have_posts() ) :
                    the_post();
                    ?>
                    
                    <article id="post-<?php the_ID(); ?>" <?php post_class('post-summary'); ?>>
                        <header class="entry-header">
                            <?php the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' ); ?>
                        </header>

                        <?php if ( has_post_thumbnail() ) : ?>
                            <div class="post-thumbnail">
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_post_thumbnail('medium_large'); ?>
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="entry-summary">
                            <?php the_excerpt(); ?>
                        </div>

                        <footer class="entry-footer">
                            <a href="<?php the_permalink(); ?>" class="button more-link"><?php _e('Saiba mais...'); ?></a>
                        </footer>
                    </article>

                    <?php
                endwhile;

                the_posts_navigation(); // Navegação para "Posts mais antigos" / "Posts mais novos"

            else :
                // Se não houver posts, mostra a mensagem padrão do tema
                get_template_part( 'content', 'none' );
            endif;
            ?>

        </main><!-- #main -->
    </div><!-- #primary -->

    <?php get_sidebar(); // Carrega a sidebar do seu tema ?>
</div><!-- #content -->

<?php
get_footer(); // Carrega o rodapé do seu tema