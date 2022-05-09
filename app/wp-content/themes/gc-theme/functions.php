<?php

// Register Custom Post Type
function timeline()
{

    $labels = array(
        'name'                  => _x('Timeline', 'Post Type General Name', 'gc'),
        'singular_name'         => _x('Timeline', 'Post Type Singular Name', 'gc'),
        'menu_name'             => __('Timeline', 'gc'),
        'name_admin_bar'        => __('Timeline', 'gc'),
        'archives'              => __('Timeline Archives', 'gc'),
        'attributes'            => __('Timeline Attributes', 'gc'),
        'parent_item_colon'     => __('Parent Item:', 'gc'),
        'all_items'             => __('All Itens', 'gc'),
        'add_new_item'          => __('Add New', 'gc'),
        'add_new'               => __('Add New', 'gc'),
        'new_item'              => __('New', 'gc'),
        'edit_item'             => __('Edit', 'gc'),
        'update_item'           => __('Update', 'gc'),
        'view_item'             => __('View', 'gc'),
        'view_items'            => __('View', 'gc'),
        'search_items'          => __('Search', 'gc'),
        'not_found'             => __('Not found', 'gc'),
        'not_found_in_trash'    => __('Not found in Trash', 'gc'),
    );
    $args = array(
        'label'                 => __('Item', 'gc'),
        'labels'                => $labels,
        'supports'              => array('title'),
        'taxonomies'            => array('category'),
        'hierarchical'          => false,
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => true,
        'rewrite'               => false,
        'capability_type'       => 'page',
        'show_in_rest'          => true,
    );
    register_post_type('timeline', $args);
}
add_action('init', 'timeline', 0);

// WP REST Headless
add_filter('wp_headless_rest__enable_rest_cleanup', '__return_true');
add_filter('wp_headless_rest__disable_front_end', '__return_false');

add_filter('wp_headless_rest__rest_endpoints_to_remove', 'wp_rest_headless_disable_endpoints');
function wp_rest_headless_disable_endpoints($endpoints_to_remove)
{

    $endpoints_to_remove = array(
        '/wp/v2/post',
        '/wp/v2/media',
        '/wp/v2/types',
        '/wp/v2/statuses',
        '/wp/v2/taxonomies',
        '/wp/v2/tags',
        '/wp/v2/users',
        '/wp/v2/comments',
        '/wp/v2/themes',
        '/wp/v2/blocks',
        '/wp/v2/block-renderer',
        '/oembed/',
        '/wp/v2/pages',
        '/wp/v2/menu-items',
        '/wp/v2/template-parts',
        '/wp/v2/navigation',
        '/wp/v2/menus',
        '/wp/v2/global-styles/',
        '/wp/v2/menu-locations',
        '/wp/v2/block-types',

        // CUSTOM
        '/wp/v2/categories',
        '/wp/v2/search',
        '/wp/v2/plugins',
        '/wp/v2/block-directory',
        '/wp/v2/settings',
        '/wp/v2/templates',
        '/wp/v2/pattern-directory/patterns',
        '/wp/v2/widgets',
        '/wp/v2/widget-types',
        '/wp/v2/sidebars'
    );


    return $endpoints_to_remove;
}

add_filter('wp_headless_rest__rest_object_remove_nodes', 'wp_rest_headless_clean_response_nodes');
function wp_rest_headless_clean_response_nodes($items_to_remove)
{

    $items_to_remove = array(
        'guid',
        '_links',
        'ping_status'
    );

    return $items_to_remove;
}

// add_filter('wp_headless_rest__cors_rules', 'wp_rest_headless_header_rules');
// function wp_rest_headless_header_rules($rules)
// {

//     $rules = array(
//         'Access-Control-Allow-Origin'      => $origin,
//         'Access-Control-Allow-Methods'     => 'GET',
//         'Access-Control-Allow-Credentials' => 'true',
//         'Access-Control-Allow-Headers'     => 'Access-Control-Allow-Headers, Content-Type, origin',
//         'Access-Control-Expose-Headers'    => array('Link', false), //Use array if replace param is required
//     );

//     return $rules;
// }

add_action('after_setup_theme', 'wpdocs_theme_setup');
function wpdocs_theme_setup()
{
    load_theme_textdomain('gc', get_template_directory() . '/languages');
}

add_action('acf/save_post', 'my_acf_save_post');
function my_acf_save_post($post_id)
{

    if (get_post_type($post_id) == 'timeline') {
        // $values = get_fields($post_id);
        // die(var_dump($values));

        while (have_rows('ct', $post_id)) {
            the_row();
            $layout = get_row_layout();
            if (get_row_layout() == 'ct-site') {
                $url = get_sub_field('ct-site-url');
            }
        }

        $tags = getSiteOG($url);
        $metas = [
            'url' => $url,
            'title' => !empty($tags['title']) ? $tags['title'] : '',
            'description' => !empty($tags['description']) ? $tags['description'] : '',
            'image' => !empty($tags['image']) ? $tags['image'] : ''
        ];

        update_sub_field(array('ct', 1, 'ct-site-title'), !empty($tags['title']) ? $tags['title'] : '');
        update_sub_field(array('ct', 1, 'ct-site-description'), !empty($tags['description']) ? $tags['description'] : '');
        update_sub_field(array('ct', 1, 'ct-site-image_url'), !empty($tags['image']) ? $tags['image'] : '');

        update_post_meta($post_id, 'url_meta_tags', $metas);
        $saida = get_post_meta($post_id, 'url_meta_tags');

        // die(var_dump($saida[0]));
    }
}

function getSiteOG($url)
{
    $doc = new DOMDocument();
    @$doc->loadHTML(file_get_contents($url));
    $res['title'] = $doc->getElementsByTagName('title')->item(0)->nodeValue;

    foreach ($doc->getElementsByTagName('meta') as $m) {
        $tag = $m->getAttribute('name') ?: $m->getAttribute('property');
        if (in_array($tag, ['description', 'keywords']) || strpos($tag, 'og:') === 0) $res[str_replace('og:', '', $tag)] = $m->getAttribute('content');
    }
    return $res;
}
