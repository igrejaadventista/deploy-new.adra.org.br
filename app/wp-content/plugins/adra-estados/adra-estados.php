<?php
/**
 * Plugin Name: Adra – Estados do Brasil (CPT + Modal)
 * Description: Cria um CPT para estados do Brasil e um shortcode que carrega um SVG do mapa. Ao clicar em um <g id="UF ou nome"> do SVG, abre um modal com os dados do CPT vinculados por meta (map_group) ou UF.
 * Version: 1.1.0
 * Author: Você
 * Text Domain: adra-estados
 */

if (!defined('ABSPATH')) { exit; }

class Adra_Estados_Brasil_Plugin {
    const CPT       = 'estado_brasil';
    const TAX       = 'regiao_brasil';
    const META_UF   = 'uf';
    const META_GRP  = 'map_group';
    const META_LINK = 'link_externo';

    public function __construct() {
        add_action('init', [$this, 'register_cpt_and_tax']);
        add_action('init', [$this, 'register_meta']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode('mapa_estados_brasil', [$this, 'shortcode_mapa']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::CPT, [$this, 'save_meta'], 10, 2);
        add_filter('manage_' . self::CPT . '_posts_columns', [$this, 'admin_cols']);
        add_action('manage_' . self::CPT . '_posts_custom_column', [$this, 'admin_col_content'], 10, 2);
    }

    public function register_cpt_and_tax() {
        register_post_type(self::CPT, [
            'labels' => [
                'name'          => __('Estados do Brasil', 'adra-estados'),
                'singular_name' => __('Estado', 'adra-estados'),
                'add_new_item'  => __('Adicionar novo Estado', 'adra-estados'),
                'edit_item'     => __('Editar Estado', 'adra-estados'),
            ],
            'public'       => true,
            'show_in_rest' => true,
            'menu_icon'    => 'dashicons-location-alt',
            'supports'     => ['title', 'editor', 'thumbnail', 'excerpt'],
            'has_archive'  => false,
            'rewrite'      => false,
        ]);

        register_taxonomy(self::TAX, [self::CPT], [
            'labels' => [
                'name'          => __('Regiões', 'adra-estados'),
                'singular_name' => __('Região', 'adra-estados'),
            ],
            'public'       => true,
            'show_in_rest' => true,
            'hierarchical' => true,
            'rewrite'      => false,
        ]);
    }

    public function register_meta() {
        register_post_meta(self::CPT, self::META_UF, [
            'single'            => true,
            'type'              => 'string',
            'sanitize_callback' => function($val){ return strtoupper(substr(sanitize_text_field($val), 0, 2)); },
            'show_in_rest'      => true,
            'auth_callback'     => function(){ return current_user_can('edit_posts'); }
        ]);
        register_post_meta(self::CPT, self::META_GRP, [
            'single'            => true,
            'type'              => 'string',
            'sanitize_callback' => function($val){
                $val = remove_accents($val);
                return sanitize_title($val);
            },
            'show_in_rest'      => true,
            'auth_callback'     => function(){ return current_user_can('edit_posts'); }
        ]);
        register_post_meta(self::CPT, self::META_LINK, [
            'single'            => true,
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'show_in_rest'      => true,
            'auth_callback'     => function(){ return current_user_can('edit_posts'); }
        ]);
    }

    /** ---------- UI ---------- */
    public function add_meta_boxes() {
        add_meta_box(
            'adra_estado_meta',
            __('Dados do Estado', 'adra-estados'),
            [$this, 'render_meta_box'],
            self::CPT,
            'side',
            'default'
        );
    }

    public function render_meta_box($post) {
        $uf   = get_post_meta($post->ID, self::META_UF, true);
        $grp  = get_post_meta($post->ID, self::META_GRP, true);
        $link = get_post_meta($post->ID, self::META_LINK, true);

        wp_nonce_field('adra_estado_meta_nonce', 'adra_estado_meta_nonce');
        ?>
        <style>
            #adra_estado_meta p { margin: 0 0 10px; }
            #adra_estado_meta input[type="text"],
            #adra_estado_meta input[type="url"] { width:100%; }
            #adra_estado_meta small { display:block; opacity:.75; }
        </style>
        <div id="adra_estado_meta">
            <p>
                <label for="adra_uf"><strong><?php _e('UF (2 letras)', 'adra-estados'); ?></strong></label>
                <input type="text" id="adra_uf" name="adra_uf" maxlength="2" value="<?php echo esc_attr($uf); ?>" />
                <small><?php _e('Ex.: SP, RJ, MS…', 'adra-estados'); ?></small>
            </p>
            <p>
                <label for="adra_map_group"><strong><?php _e('Map Group (id do <g> do SVG)', 'adra-estados'); ?></strong></label>
                <input type="text" id="adra_map_group" name="adra_map_group" value="<?php echo esc_attr($grp); ?>" />
                <small><?php _e('Use o id do <g id="..."> em slug: minúsculas, sem acentos, com hífens. Ex.: mato-grosso-do-sul', 'adra-estados'); ?></small>
            </p>
            <p>
                <label for="adra_link_externo"><strong><?php _e('Link Externo (opcional)', 'adra-estados'); ?></strong></label>
                <input type="url" id="adra_link_externo" name="adra_link_externo" value="<?php echo esc_attr($link); ?>" />
                <small><?php _e('Se preenchido, ao clicar no estado será redirecionado para este link ao invés de abrir o modal.', 'adra-estados'); ?></small>
            </p>
        </div>
        <?php
    }

    public function save_meta($post_id, $post) {
        if (!isset($_POST['adra_estado_meta_nonce']) || !wp_verify_nonce($_POST['adra_estado_meta_nonce'], 'adra_estado_meta_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['adra_uf'])) {
            $uf = strtoupper(substr(sanitize_text_field(wp_unslash($_POST['adra_uf'])), 0, 2));
            update_post_meta($post_id, self::META_UF, $uf);
        }
        if (isset($_POST['adra_map_group'])) {
            $grp = remove_accents(wp_unslash($_POST['adra_map_group']));
            $grp = sanitize_title($grp);
            update_post_meta($post_id, self::META_GRP, $grp);
        }
        if (isset($_POST['adra_link_externo'])) {
            $link = esc_url_raw(wp_unslash($_POST['adra_link_externo']));
            update_post_meta($post_id, self::META_LINK, $link);
        }
    }

    /** ---------- Colunas no CPT ---------- */
    public function admin_cols($cols) {
        $cols['uf']  = __('UF', 'adra-estados');
        $cols['grp'] = __('Map Group', 'adra-estados');
        return $cols;
    }
    public function admin_col_content($col, $post_id) {
        if ($col === 'uf')  echo esc_html(get_post_meta($post_id, self::META_UF, true));
        if ($col === 'grp') echo esc_html(get_post_meta($post_id, self::META_GRP, true));
    }

    public function register_assets() {
        $ver  = '1.3.1';
        $base = plugin_dir_url(__FILE__);

        wp_register_script('adra-estados-js', $base . 'assets/js/mapa-estados.js', [], $ver, true);

        $clickables = [];
        $ids = get_posts([
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        ]);
        foreach ($ids as $pid) {
            $grp = get_post_meta($pid, self::META_GRP, true);
            if (!empty($grp)) {
                $clickables[] = sanitize_title(remove_accents($grp));
            }
        }
        $clickables = array_values(array_unique($clickables));

        wp_localize_script('adra-estados-js', 'ADRA_MAPA', [
            'restUrl'        => esc_url_raw(rest_url('adra-estados/v1/estado')),
            'clickableGroups'=> $clickables,
        ]);

        wp_register_style('adra-estados-css', $base . 'assets/css/mapa-estados.css', [], $ver);
    }

    public function shortcode_mapa($atts = [], $content = null) {
        $atts = shortcode_atts([
            'height' => '',
            'width'  => '100%'
        ], $atts, 'mapa_estados_brasil');

        wp_enqueue_script('adra-estados-js');
        wp_enqueue_style('adra-estados-css');

        $container_id = 'adra-mapa-' . wp_generate_uuid4();
        $svg = do_shortcode($content ?? '');

        ob_start(); ?>
        <div class="adra-mapa-wrapper" id="<?php echo esc_attr($container_id); ?>"
             data-height="<?php echo esc_attr($atts['height']); ?>"
             data-width="<?php echo esc_attr($atts['width']); ?>">
            <div class="adra-mapa-svg">
                <?php echo $svg;?>
            </div>
            <!-- Modal opcional por shortcode, seu JS cria outro se precisar -->
        </div>
        <?php
        return ob_get_clean();
    }

    public function register_rest_routes() {
        register_rest_route('adra-estados/v1', '/estado', [
            'methods'  => 'GET',
            'callback' => [$this, 'rest_get_estado'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Endpoint: /wp-json/adra-estados/v1/estado?id= | uf= | group=
     */
    public function rest_get_estado(\WP_REST_Request $req) {
        $id    = absint($req->get_param('id'));
        $uf    = strtoupper(sanitize_text_field($req->get_param('uf')));
        $group = sanitize_title(remove_accents($req->get_param('group')));

        $post = null;
        if ($id) {
            $post = get_post($id);
            if (!$post || $post->post_type !== self::CPT) {
                return new \WP_Error('not_found', __('Estado não encontrado pelo ID.', 'adra-estados'), ['status' => 404]);
            }
        } elseif (!empty($group)) {
            $q = new \WP_Query([
                'post_type'      => self::CPT,
                'posts_per_page' => 1,
                'meta_key'       => self::META_GRP,
                'meta_value'     => $group,
                'post_status'    => 'publish',
                'no_found_rows'  => true,
            ]);
            $post = $q->have_posts() ? $q->posts[0] : null;

            if (!$post) {
                $q2 = new \WP_Query([
                    'post_type'      => self::CPT,
                    'name'           => $group,
                    'posts_per_page' => 1,
                    'post_status'    => 'publish',
                    'no_found_rows'  => true,
                ]);
                $post = $q2->have_posts() ? $q2->posts[0] : null;
            }
        } elseif (!empty($uf)) {
            $q = new \WP_Query([
                'post_type'      => self::CPT,
                'posts_per_page' => 1,
                'meta_key'       => self::META_UF,
                'meta_value'     => $uf,
                'post_status'    => 'publish',
                'no_found_rows'  => true,
            ]);
            $post = $q->have_posts() ? $q->posts[0] : null;
        }

        if (!$post) {
            return new \WP_Error('not_found', __('Estado não encontrado.', 'adra-estados'), ['status' => 404]);
        }

        $thumb = get_the_post_thumbnail_url($post, 'large');
        $link_externo = get_post_meta($post->ID, self::META_LINK, true);

        return [
            'success'      => true,
            'id'           => $post->ID,
            'title'        => get_the_title($post),
            'content'      => apply_filters('the_content', $post->post_content),
            'excerpt'      => $post->post_excerpt,
            'thumbnail'    => $thumb ? esc_url($thumb) : null,
            'uf'           => get_post_meta($post->ID, self::META_UF, true),
            'group'        => get_post_meta($post->ID, self::META_GRP, true),
            'regions'      => wp_get_post_terms($post->ID, self::TAX, ['fields' => 'names']),
            'link'         => get_permalink($post),
            'link_externo' => $link_externo ? esc_url($link_externo) : null,
        ];
    }
}

new Adra_Estados_Brasil_Plugin();
