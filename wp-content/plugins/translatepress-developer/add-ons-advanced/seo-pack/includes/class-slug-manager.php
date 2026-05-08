<?php


// Exit if accessed directly
if ( !defined('ABSPATH' ) )
    exit();

class TRP_IN_SP_Slug_Manager {

    protected $settings;
    protected $human_translated_slug_meta;
    protected $automatic_translated_slug_meta;
    /** @var TRP_Url_Converter */
    protected $url_converter;
    protected $translation_manager;
    protected $option_based_strings;
    public $string_translation_api_tax_slug;
    public $string_translation_api_post_type_slug;
    protected $string_translation_api_term_slug;
    protected $slug_query;
    protected $redirect_to_url;
    protected $abs_home_path;

    public function __construct( $settings ){
        $this->settings = $settings;

        $trp = TRP_Translate_Press::get_trp_instance();
        $this->url_converter = $trp->get_component( 'url_converter' );
        $this->translation_manager = $trp->get_component( 'translation_manager' );

        $meta_based_strings   = new TRP_IN_SP_Meta_Based_Strings();
        $this->human_translated_slug_meta     = $meta_based_strings->get_human_translated_slug_meta();
        $this->automatic_translated_slug_meta = $meta_based_strings->get_automatic_translated_slug_meta();

        $this->option_based_strings = new TRP_IN_SP_Option_Based_Strings();

        $this->string_translation_api_tax_slug = new TRP_String_Translation_API_Taxonomy_Slug( $settings );
        $this->string_translation_api_post_type_slug = new TRP_String_Translation_API_Post_Type_Base_Slug( $settings );
        $this->string_translation_api_term_slug = new TRP_String_Translation_API_Term_Slug( $settings );
        $this->slug_query = new TRP_Slug_Query();

        add_filter( 'trp_is_admin_link_for_request_uri', array( $this, 'verify_if_is_admin_link_for_slug_translation' ), 10, 2);
        add_filter( 'trp_is_rest_api', array( $this, 'verify_if_is_rest_api_slug_translation' ));
    }

    /**
     * Echo page slug as meta tag in preview window.
     *
     * Hooked to wp_head
     */
    public function add_slug_as_meta_tag() {
        if ( isset( $_REQUEST['trp-edit-translation'] ) && ( $_REQUEST['trp-edit-translation'] === 'preview' ) ) {
            global $post;
            $trp = TRP_Translate_Press::get_trp_instance();
            if ( ! $this->translation_manager ) {
                $this->translation_manager = $trp->get_component( 'translation_manager' );
            }
            if ( method_exists ( $this->translation_manager, 'string_groups' ) ) {
                $string_groups = $this->translation_manager->string_groups();
                if ( isset( $post->ID ) && ! empty( $post->ID ) && isset( $post->post_name ) && ! empty( $post->post_name ) && ! is_home() && ! is_front_page() && ! is_archive() && ! is_search() ) {
                    echo '<meta data-trp-post-slug=' . (int) $post->ID . ' data-trp-node-type="' . esc_attr( $string_groups['slugs'] ) . '" data-trp-node-description="' . esc_attr__( 'Post Slug', 'translatepress-multilingual' ) . '"/>' . "\n";
                }
            }
        }
    }

    /*
     * Not used in TP
     */
    public function get_translated_slug_filter( $original, $post_id, $language ){
        return $this->get_translated_slug( $post_id, $language );
    }

    /**
     * Decide whether to redirect to the url with the translated slug
     *
     * Happens if the current url has original slugs that have translations
     *
     */
    public function is_redirect_needed( $current_url_with_domain ){
        global $TRP_LANGUAGE;

        $is_form_request = isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST';

        if ( apply_filters( 'trp_is_rest_api', false, $current_url_with_domain ) )
            return false;

        if ( apply_filters( 'trp_is_form_for_request_uri', $is_form_request, $current_url_with_domain ) )
            return false;

        if( $TRP_LANGUAGE != $this->settings['default-language'] ) {

            if ( !apply_filters( 'trp_allow_redirect_to_translated_url', true, $current_url_with_domain ) ) {
                return false;
            }

            $path_no_lang_slug = $this->get_path_no_lang_slug_from_url( $current_url_with_domain );
            $segments = explode( '/', untrailingslashit( $path_no_lang_slug ) );
            if ( 'feed' === end( $segments ) ) {
                // WordPress has this path reserved. Every url that ends with /feed
                return false;
            }

            $possible_missed_slugs_from_the_current_url = $this->get_translatable_slugs_from_url( $current_url_with_domain );
            if ( empty( $possible_missed_slugs_from_the_current_url ) ){
                return false;
            }

            $slug_pairs = $this->get_slugs_pairs_based_on_language( $possible_missed_slugs_from_the_current_url, $this->settings['default-language'], $TRP_LANGUAGE );

            // Add WooCommerce base slug translations using gettext (handles case where WooCommerce permalink uses English slug)
            $slug_pairs = $this->add_woocommerce_gettext_slug_pairs( $slug_pairs, $possible_missed_slugs_from_the_current_url, $TRP_LANGUAGE );

            if ( empty ( $slug_pairs ) ){
                return false;
            }

            // passing the $path_no_lang_slug ensures that the trailing slash remains the same. Not removed if exists and not added if it doesn't exist
            $translated_path = $this->replace_slugs_in_url_path( $path_no_lang_slug, $slug_pairs );

            $translated_url_with_domain = str_replace( $path_no_lang_slug, $translated_path, $current_url_with_domain );

            if ( strtolower( $current_url_with_domain ) != strtolower( $translated_url_with_domain ) ) {

                // It's important to not change the url's trailing slash. Leave it as it is because otherwise it can cause redirect loops
                $this->redirect_to_url = $translated_url_with_domain;

                // redirect later and not now in order to be compatible with WP Rocket pre-cache
                add_filter( 'template_redirect', array( $this, 'redirect_to_translated_slug' ), 1 );
                return true;
            }

        }
        return false;
    }

    /**
     * Hooked to 'template_redirect' inside is_redirect_needed() only if needed
     *
     * @return void
     */
    public function redirect_to_translated_slug(){
        $status = apply_filters( 'trp_redirect_status', 301, 'redirect_to_translated_slug' );
        $this->redirect_to_url = apply_filters( 'trp_redirect_to_translated_slug_url', $this->redirect_to_url );

        wp_safe_redirect( $this->redirect_to_url, $status );
        exit();
    }

    /**
     * @param $post the post object or post id
     * @param string $language optional parameter for language. if it's not present it will grab it from the $TRP_LANGUAGE global
     * @return mixed|string an empty string or the translated slug
     */
    public function get_translated_slug( $post, $language = null ){
        if( $language == null ){
            global $TRP_LANGUAGE;
            if( !empty( $TRP_LANGUAGE ) )
                $language = $TRP_LANGUAGE;
        }

        if( is_object( $post ) )
            $post = $post->ID;

        $translated_slug = get_post_meta( $post, $this->human_translated_slug_meta.$language, true );
        if( !empty( $translated_slug ) ) {
            return $translated_slug;
        }else {
            $translated_slug = get_post_meta( $post, $this->automatic_translated_slug_meta . $language, true );
            if ( !empty( $translated_slug ) ){
                return $translated_slug;
            }
        }
        return '';
    }

    /**
     * @param $slug the translated slug
     * @return string the original slug if we can find it
     */
    protected function get_original_slug( $slug, $post_type = '' ){
        global $TRP_LANGUAGE, $wpdb;

        $slug_decoded = urldecode($slug);
        $slug_encoded = urlencode($slug_decoded);

        if( !empty( $TRP_LANGUAGE ) ){

            $translated_slug = $wpdb->get_results($wpdb->prepare(
                "
                SELECT *
                FROM $wpdb->postmeta
                WHERE ( meta_key = '%s' OR meta_key = '%s' )
                    AND (meta_value = '%s' OR meta_value = '%s')
                ", $this->human_translated_slug_meta.$TRP_LANGUAGE, $this->automatic_translated_slug_meta.$TRP_LANGUAGE, $slug_decoded, $slug_encoded
            ) );

            if( !empty( $translated_slug ) ){
                $post_id = $translated_slug[0]->post_id;
                if( empty( $post_type ) ){
                    $post = get_post( $post_id );
                    if( !empty( $post ) )
                        $slug = $post->post_name;
                }
                elseif( $post_type == 'page' ){
                    if( get_post_type( $post_id ) == 'page' ){
                        $post = get_post( $post_id );
                        if( !empty( $post ) )
                            $slug = $post->post_name;
                    }
                }
            }
        }

        return $slug;
    }

    /**
     * @param $slug
     * @return bool
     *
     * Verifies if slug exists in the original slugs table for when at if off
     */
    public function verify_if_slug_original_exists_already_for_when_slug_machine_translation_is_off( $slug ) {

        $slug_array = array( $slug );

        $is_translated_slug_in_db = $this->slug_query->get_ids_from_original( $slug_array );

        if ( empty( $is_translated_slug_in_db ) ) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param $slug
     * @param $language_code
     * @return bool
     *
     * Verifies if the slug has a translation in the trp_slug_translation language for the current language
     */
    public function verify_if_the_slug_translation_already_exists_for_machine_translation( $slug, $language_code ){

        $slug_array = array( $slug );

        $is_translated_slug_in_db = $this->slug_query->get_translated_slugs_from_original( $slug_array, $language_code, true);

        if ( empty($is_translated_slug_in_db )){
            return false;
        }else{
            return true;
        }
    }

    /**
     * @param $slug
     * @param $language_code
     * @return bool
     *
     * Verifies if there is a translation for the other type slug found in db
     */
    public function verify_if_the_slug_is_a_translation_for_current_url_others( $slug, $language_code ){

        $slug_array = array( $slug );

        $is_translated_slug_in_db = $this->slug_query->get_original_slugs_from_translated( $slug_array, $language_code, true);

        if ( empty( $is_translated_slug_in_db ) ){
            return false;
        }else{
            return true;
        }

    }

    /**
     * Add slug into the array to run through process_strings and obtain a machine translation if a translation for the current language is not set
     *
     * It is later retrieved for saving in db in function save_machine_translated_slug
     *
     * Hooked to trp_translateable_strings
     *
     * @param $translateable_information
     * @param $html
     * @param $no_translate_attribute
     * @param $TRP_LANGUAGE
     * @param $language_code
     * @param $translation_render
     *
     * @return array
     */
    public function include_slug_for_machine_translation( $translateable_information = array(), $html = false, $no_translate_attribute = '', $TRP_LANGUAGE = null, $language_code = '', $translation_render = false ) {
        if ( !is_array($translateable_information) ) {
            $translateable_information = [
                'translateable_strings' => [],
                'nodes'                 => [],
            ];
        }

        // if automatic translation is not active ( or the automatically translate slugs checkbox is not checked), we should still insert the original slugs in the database
        //we add this parameter in $translatable_information['nodes'] to add them to an array that will not be sent in the request for automatic translation
        $skip_automatic_translation_for_slugs = false;
        $woo_english_slugs                    = array( 'product-category', 'product-tag', 'product' );
        if ( !apply_filters( 'trp_machine_translate_slug', false ) ) {
            $skip_automatic_translation_for_slugs                  = true;
            $translateable_information_to_return_if_slug_at_is_off = $translateable_information;
        }

        global $post;
        global $wp_query;

        global $TRP_LANGUAGE;
        // we need to check if the current url exists or it will have a 404 response
        // this is necessary because we are looking for slugs not only identifiable by wp functions but also slugs that are found in the URL for other slugs
        // and this will lead to automatically translating slugs that do not exist
        if ( !( is_404() ) ) {

            // @todo important for some reason the results offered by $wp_query->query do not work the same as they used to, we need to verify if the translation exists for eatch type of wp slug we want to add
            // this seemed to cause multiple instances of the same slug to be sent to automatic translation even if it already had a translation

            // verifies if the slug is already translated

//            if ( isset( $post->post_name ) && !empty( $post->post_name ) ) {
//                $is_slug_already_translated = $this->verify_if_the_slug_translation_already_exists_for_machine_translation( $post->post_name, $language_code );
//            } else {
//                $is_slug_already_translated = $this->verify_if_the_slug_translation_already_exists_for_machine_translation( $wp_query->query_vars['name'], $language_code );
//            }

            // post slugs
            if ( isset( $post->ID ) && !empty( $post->ID ) && isset( $post->post_name ) && !empty( $post->post_name ) && !is_home() && !is_front_page() && !is_archive() && !is_search() ) {

                if ( ( $skip_automatic_translation_for_slugs &&
                        !$this->verify_if_slug_original_exists_already_for_when_slug_machine_translation_is_off( $post->post_name ) ) ||
                    ( !$skip_automatic_translation_for_slugs && !$this->verify_if_the_slug_translation_already_exists_for_machine_translation( $post->post_name, $language_code ) ) ) {
                    $is_public = true;
                    $is_it_a_standard_post = true;

                    // i had to use a variable for $post->post_type to verify with isset because according to serach
                    //it's not allowed 'isset() cannot operate on the result of a function call and directly access a property in a single statement because PHP does not allow this'
                    $verify_if_the_post_type_is_public = isset( $post->post_type ) ? get_post_type_object( $post->post_type ) : false;
                    if ( !$verify_if_the_post_type_is_public || !isset( $verify_if_the_post_type_is_public->publicly_queryable ) ) {
                        $is_it_a_standard_post = false;
                    }elseif( isset( $verify_if_the_post_type_is_public->publicly_queryable ) && !$verify_if_the_post_type_is_public->publicly_queryable ){
                        $is_public = false;
                    }

                    if ( $is_public && $is_it_a_standard_post ) {
                        // added new argument ro not send to AT strings formed of only numbers ( ex: '88888', '44-54 )
                        // trp full trim eliminates white spaces
                        $post_trimmed = trp_full_trim($post->post_name, array('numerals' => 'no'));
                        if ( !empty( $post_trimmed ) ) {
                            $post_without_hyphens = str_replace('-', ' ', $post_trimmed);
                            $translateable_information['translateable_strings'][] = urldecode($post_without_hyphens);
                            $translateable_information['nodes'][] = array('type' => 'post', 'post_id' => $post->ID, 'original_slug_name' => $post->post_name, 'skip_automatic_translation' => $skip_automatic_translation_for_slugs);
                        }
                    }
                }
            }

            // archives
            if ( is_archive() ) {
                if ( !isset( $wp_query->query['post_type'] ) ) {
                    global $trp_all_taxonomies;
                    if ( !isset( $trp_all_taxonomies ) )
                        $trp_all_taxonomies = get_taxonomies();

                    foreach ( $wp_query->query as $taxonomy => $term_slug ) {
                        //normalize built in category and tag taxonomies which have special query vars
                        $actual_taxonomy = $this->trp_normalize_taxonomy_names( $taxonomy );

                        //check if it is actually a taxonomy we have
                        if ( in_array( $actual_taxonomy, $trp_all_taxonomies ) ) {

                            $term_object = get_term_by( 'slug', $term_slug, $actual_taxonomy );

                            if ( isset( $term_object->term_id ) ) {

                                if ( ( $skip_automatic_translation_for_slugs &&
                                        !$this->verify_if_slug_original_exists_already_for_when_slug_machine_translation_is_off( $term_slug ) ) ||
                                    ( !$skip_automatic_translation_for_slugs && !$this->verify_if_the_slug_translation_already_exists_for_machine_translation( $term_slug, $language_code ) &&
                                        !in_array( $term_slug, $woo_english_slugs ) ) ) {

                                    $term_slug_trimmed                                    = trp_full_trim( $term_slug, array('numerals' => 'no') );
                                    if ( !empty( $term_slug_trimmed ) ) {
                                        $term_slug_without_hyphens = str_replace('-', ' ', $term_slug_trimmed);
                                        $translateable_information['translateable_strings'][] = urldecode($term_slug_without_hyphens);
                                        $translateable_information['nodes'][] = array('type' => 'term', 'term_id' => $term_object->term_id, 'original_slug_name' => $term_slug, 'skip_automatic_translation' => $skip_automatic_translation_for_slugs);
                                    }
                                }
                            }

                            $tax_object         = get_taxonomy( $actual_taxonomy );
                            $original_base_slug = $this->get_rewrite_base_slug( $tax_object, $actual_taxonomy );

                            if ( $original_base_slug && strpos( trim( $original_base_slug, '/\\' ), '/' ) === false && strpos( $original_base_slug, '%' ) === false ) {

                                if ( ( $skip_automatic_translation_for_slugs &&
                                        !$this->verify_if_slug_original_exists_already_for_when_slug_machine_translation_is_off( $original_base_slug ) ) ||
                                    ( !$skip_automatic_translation_for_slugs && !$this->verify_if_the_slug_translation_already_exists_for_machine_translation( $original_base_slug, $language_code ) && !in_array( $original_base_slug, $woo_english_slugs ) ) ) {

                                    $original_base_slug_trimmed                           = trp_full_trim( $original_base_slug, array('numerals' => 'no') );
                                    if ( !empty( $original_base_slug_trimmed ) ) {
                                        $original_base_slug_without_hyphens = str_replace('-', ' ', $original_base_slug_trimmed);
                                        $translateable_information['translateable_strings'][] = urldecode($original_base_slug_without_hyphens);
                                        $translateable_information['nodes'][] = array('type' => 'taxonomy', 'original_slug_name' => $original_base_slug, 'skip_automatic_translation' => $skip_automatic_translation_for_slugs);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // add support for automatic translation of tax base slugs, cpt base slugs and term slugs
            if ( isset( $wp_query->query['post_type'] ) ) {

                //$wp_query->query['post_type'] can be a string or an array
                //next lines we are making sure to treat both cases
                $post_types = $wp_query->query['post_type'];
                if ( !empty( $post_types ) && !is_object( $post_types ) ) {
                    if ( is_string( $post_types ) ) {
                        $post_types = array( $post_types );
                    }
                    if ( is_array( $post_types ) ) {
                        foreach ( $post_types as $post_type_string ) {
                            if ( !is_string( $post_type_string ) ) {
                                continue;
                            }

                            if ( apply_filters( 'trp_filter_post_type_base_slugs_from_automatic_translation', true, $post_type_string ) ) {
                                $post_type_object = get_post_type_object( $post_type_string );

                                if ( $post_type_object && isset( $post_type_object->publicly_queryable ) && $post_type_object->publicly_queryable ) {
                                    $original_base_slug = $this->get_rewrite_base_slug( $post_type_object, $post_type_string );

                                    if ( ( $skip_automatic_translation_for_slugs &&
                                            !$this->verify_if_slug_original_exists_already_for_when_slug_machine_translation_is_off( $original_base_slug ) ) ||
                                        ( !$skip_automatic_translation_for_slugs && !$this->verify_if_the_slug_translation_already_exists_for_machine_translation( $original_base_slug, $language_code ) && !in_array( $original_base_slug, $woo_english_slugs ) ) ) {

                                        $original_base_slug_trimmed                           = trp_full_trim( $original_base_slug, array('numerals' => 'no') );

                                        if ( !empty( $original_base_slug_trimmed ) ) {
                                            $original_base_slug_without_hyphens = str_replace('-', ' ', $original_base_slug_trimmed);
                                            $translateable_information['translateable_strings'][] = urldecode($original_base_slug_without_hyphens);
                                            $translateable_information['nodes'][] = array('type' => 'post-type-base', 'original_slug_name' => $original_base_slug, 'skip_automatic_translation' => $skip_automatic_translation_for_slugs);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $current_url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_url( $_SERVER['REQUEST_URI'] ) : ''; //phpcs:ignore

            if ( !apply_filters( 'trp_is_admin_link_for_request_uri', false, $current_url ) && !apply_filters( 'trp_is_rest_api', false, $current_url ) && apply_filters( 'trp_translate_other_slugs', true, $current_url ) ) {

                $possible_missed_slugs_from_the_current_url = $this->get_translatable_slugs_from_url( $current_url );

                if ( isset( $possible_missed_slugs_from_the_current_url ) ) {
                    foreach ( $possible_missed_slugs_from_the_current_url as $slug_name ) {
                        $is_other_type_slug_found = true;
                        $the_slug_is_not_saved_in_original_or_it_does_not_have_a_translation = false;

                        // the slugs containing multiple words, have the hyphens replaced with spaces
                        $slug_name_trimmed = trp_full_trim($slug_name, array('numerals' => 'no'));
                        if (!empty($slug_name_trimmed)) {
                            $slug_without_hyphens = str_replace('-', ' ', $slug_name_trimmed);

                            if (!ctype_digit($slug_name)) {
                                if (($skip_automatic_translation_for_slugs &&
                                        !$this->verify_if_slug_original_exists_already_for_when_slug_machine_translation_is_off($slug_name)) ||
                                    (!$skip_automatic_translation_for_slugs && !($this->verify_if_the_slug_translation_already_exists_for_machine_translation($slug_name, $language_code)))) {

                                    $the_slug_is_not_saved_in_original_or_it_does_not_have_a_translation = true;

                                }
                            }

                            if ($the_slug_is_not_saved_in_original_or_it_does_not_have_a_translation) {
                                if (isset($translateable_information['translateable_strings']) && $array_keys_found = array_keys($translateable_information['translateable_strings'], $slug_without_hyphens)) {

                                    foreach ($array_keys_found as $array_key) {

                                        if (isset($translateable_information['nodes'][$array_key])) {

                                            $is_other_type_slug_found = false;
                                            break;
                                        }
                                    }
                                }

                                if ($is_other_type_slug_found) {

                                    $do_not_translate_these_slugs_of_type_other_with_at = apply_filters('trp_do_not_translate_these_slugs_of_type_other_with_at', array(
                                        'feed'
                                    ));

                                    // Also exclude WooCommerce base slugs - they are already handled as post-type-base via gettext
                                    if ( class_exists( 'WooCommerce' ) ) {
                                        $wc_base_slugs = array( 'product', 'product-category', 'product-tag' );
                                        foreach ( $wc_base_slugs as $wc_base_slug ) {
                                            $wc_permalink_slug = $this->get_woocommerce_permalink_base_slug( $wc_base_slug );
                                            if ( ! empty( $wc_permalink_slug ) && ! in_array( $wc_permalink_slug, $do_not_translate_these_slugs_of_type_other_with_at ) ) {
                                                $do_not_translate_these_slugs_of_type_other_with_at[] = $wc_permalink_slug;
                                            }
                                        }
                                    }

                                    if (!($this->verify_if_the_slug_is_a_translation_for_current_url_others($slug_name, $language_code)) && !in_array($slug_name, $do_not_translate_these_slugs_of_type_other_with_at)) {
                                        $translateable_information['translateable_strings'][] = urldecode($slug_without_hyphens);
                                        $translateable_information['nodes'][] = array('type' => 'other', 'original_slug_name' => $slug_name, 'skip_automatic_translation' => $skip_automatic_translation_for_slugs);
                                    }

                                }
                            }
                        }
                    }
                }
            }
        }

        if ( isset( $translateable_information_to_return_if_slug_at_is_off ) ) {

            if ( isset( $translateable_information) && isset( $translateable_information[ 'nodes' ] ) ) {
                $this->save_original_slugs_in_db_when_at_is_off( $translateable_information );
                return $translateable_information_to_return_if_slug_at_is_off;
            }
        } elseif ( $TRP_LANGUAGE == $this->settings['default-language'] && !$skip_automatic_translation_for_slugs ) {
            if ( isset( $translateable_information) && isset( $translateable_information[ 'nodes' ] ) ) {
                $this->save_machine_translated_slug( $translateable_information );
                return $translateable_information;
            }
        } else {
            return $translateable_information;
        }
    }

    /**
     * Function hooked on trp_translateable_information to save the slug translation.
     *
     * When Slug Automatic Translation if off do not do anything
     * Runs on every translated page
     * It's used to save the page slug from automatic translation into the proper structure for trp_slug_original and trp_slug_translation
     *
     * Works together with function include_slug_for_machine_translation
     *
     *
     * /////////IMPORTANT FOR FUTHER REFERENCE/////////////
     * The slugs in include_slugs_for_machine_translation are urlencoded (%d0%bf%d0%be%d0%b6%d0%b0%d0%bb%d1%83%d0%b9%d1%81%d1%82%d0%b0)
     * So the slugs in translatable_information are always encoded so what we sent to the machine tr engine
     * is decoded in the request with RAWURLDECODE
     *
     * What we get from Google Translation/Deeps seems to be also encoded, so we do not have to encode/decode on insert_slugs
     */
    public function save_machine_translated_slug($translateable_information = array(), $translated_strings = array(), $language_code = '') {

        if ( !apply_filters( 'trp_machine_translate_slug', false ) ) {
            return;
        }

        $trp       = TRP_Translate_Press::get_trp_instance();
        $trp_query = $trp->get_component( 'query' );

        $array_of_machine_translated_slugs_for_insertion = array();

        foreach ( $translateable_information['nodes'] as $key => $value ) {

            if ( !( isset( $value['original_slug_name'] ) ) ) {
                continue;
            }
            if ( $value['type'] === 'post' || $value['type'] === 'term' || $value['type'] === 'taxonomy' || $value['type'] === 'post-type-base' || $value['type'] === 'other' ) {
                $array_of_machine_translated_slugs_for_insertion[ $value['original_slug_name'] ]['type'] = $value['type'];
            }

            $array_of_machine_translated_slugs_for_insertion[ $value['original_slug_name'] ]['original'] = $value['original_slug_name'];

            $sanitized_slug = isset( $translated_strings[ $key ] ) ? sanitize_title( $translated_strings[ $key ] ) : "";
            if ( !empty( $sanitized_slug ) ) {

                $sanitized_slug = str_replace( ' ', '-', $sanitized_slug );
                //removed here the urldecode
                $array_of_machine_translated_slugs_for_insertion[ $value['original_slug_name'] ]['translated'] = $sanitized_slug;
            }

            $array_of_machine_translated_slugs_for_insertion[ $value['original_slug_name'] ]['status'] = isset( $translated_strings[ $key ] ) ? $trp_query->get_constant_machine_translated() : 0;

        }

        $this->slug_query->insert_slugs( $array_of_machine_translated_slugs_for_insertion, $language_code );

    }

    /**
     * @param $translateable_information
     * @param $translated_strings
     * @return void
     *
     * Function used to insert original slugs found in function include_slug_for_machine_translation
     * when Slug Automatic Translation is off
     *
     *Made a new function because there is no use for so mutch verification and we ca use only the function
     * insert_original_slugs when at is off
     */
    public function save_original_slugs_in_db_when_at_is_off( $translateable_information = array() ) {
        $array_of_slugs_for_insertion = array();
        foreach ( $translateable_information['nodes'] as $key => $value ) {
            if ( !( isset( $value['original_slug_name'] ) ) ) {
                continue;
            }
            if ( $value['type'] === 'post' || $value['type'] === 'term' || $value['type'] === 'taxonomy' || $value['type'] === 'post-type-base' || $value['type'] === 'other' ) {
                $array_of_slugs_for_insertion[ $value['original_slug_name'] ]['type'] = $value['type'];
            }
            $array_of_slugs_for_insertion[ $value['original_slug_name'] ]['original'] = $value['original_slug_name'];
        }
        $this->slug_query->insert_original_slugs( $array_of_slugs_for_insertion );

    }

    /**
     * Return a unique post slug.
     *
     * It should be unique against WP posts and slugs saved in the same language.
     * Numeral suffixes will be added if there is a conflict.
     * (ex. my-post-2 )
     *
     * @param $sanitized_slug
     * @param $post
     * @param $language
     * @return string|void
     */
    public function get_unique_post_slug( $sanitized_slug, $post, $language ){
        if ( ! in_array( $language, $this->settings['translation-languages'] ) ) {
            return;
        }
        $wp_unique_slug = wp_unique_post_slug($sanitized_slug, $post->ID, $post->post_status, $post->post_type, $post->post_parent);

        global $wpdb;
        $suffix = 2;
        $slug_base = $wp_unique_slug;

        $wp_unique_slug_decoded = urldecode($wp_unique_slug);
        $wp_unique_slug_encoded = urlencode($wp_unique_slug_decoded);
        do {
            $meta_value = $wpdb->get_var( "SELECT meta_value FROM " . $wpdb->postmeta . " WHERE (meta_value='" .
                sanitize_text_field( $wp_unique_slug_decoded ) . "'OR'" . sanitize_text_field($wp_unique_slug_encoded) . "') AND ( meta_key='" . sanitize_text_field( $this->human_translated_slug_meta . $language ) .
                "' OR meta_key='" . sanitize_text_field( $this->automatic_translated_slug_meta . $language ) . "')" );

            $slug_already_exists = !empty( $meta_value ) && $meta_value == $wp_unique_slug;
            if ( $slug_already_exists ){
                $wp_unique_slug = $slug_base . '-' . $suffix;
                $suffix++;
            }
        }while( $slug_already_exists );

        return $wp_unique_slug;
    }

    /**
     * Function to get the original rewrite slug from registering args
     * @param $args
     * @param $name
     * @return bool|mixed
     */
    public function get_rewrite_base_slug( $args, $name ){
        if(is_object($args))//this way we can pass a taxonomy object, not just an arguments array
            $args = (array) $args;

        if ( is_string( $name ) ) {
            //for woocommerce we have a special case, we need the original hardcoded slug and not the one passed through the translation functions (_x)
            if (class_exists('WooCommerce')) {

                if (!class_exists('TRP_Translation_Manager'))
                    require_once TRP_PLUGIN_DIR . 'includes/class-translation-manager.php';

                if (class_exists('TRP_Translation_Manager')) {
                    if ($name === 'product' || $name === 'product_cat' || $name === 'product_tag') {
                        if ($name === 'product' && trim($args['rewrite']['slug'], '\\/') === TRP_Translation_Manager::strip_gettext_tags(_x('product', 'slug', 'woocommerce'))) //phpcs:ignore
                            $name = trim($name, '/\\');
                        elseif ($name === 'product_cat' && trim($args['rewrite']['slug'], '\\/') === TRP_Translation_Manager::strip_gettext_tags(_x('product-category', 'slug', 'woocommerce'))) //phpcs:ignore
                            $name = 'product-category';
                        elseif ($name === 'product_tag' && trim($args['rewrite']['slug'], '\\/') === TRP_Translation_Manager::strip_gettext_tags(_x('product-tag', 'slug', 'woocommerce'))) //phpcs:ignore
                            $name = 'product-tag';

                        if ($name === 'product' || $name === 'product-category' || $name === 'product-tag') {
                            global $trp_wc_permalinks;//this should be defined in woocommerce_filter_permalink_option() function
                            if (isset($trp_wc_permalinks)) {
                                if ($name == 'product-category') {
                                    $option_index = 'category_base';
                                } elseif ($name == 'product-tag') {
                                    $option_index = 'tag_base';
                                } elseif ($name == 'product') {
                                    $option_index = 'product_base';
                                }

                                if (!empty($trp_wc_permalinks) && !empty($option_index) && isset($trp_wc_permalinks[$option_index]) && $name != $trp_wc_permalinks[$option_index]) {
                                    $name = trim($trp_wc_permalinks[$option_index], '\\/');
                                }
                            }

                            return $name;
                        }

                    }
                }
            }

            if (isset($args['rewrite'])) {
                if (is_array($args['rewrite']) && isset($args['rewrite']['slug']))
                    return trim($args['rewrite']['slug'], '\\/');
                else if ( $args['rewrite'] === true || ( ( false !== $args['rewrite'] ) && ( is_admin() || get_option( 'permalink_structure' ) ) ) )
                    return trim($name, '\\/');
                else
                    return false;
            } else
                return trim($name, '\\/');
        }

        return false;
    }

    /**
     * Function to get the translated rewrite slug based on the original rewrite slug
     * @param $name
     * @param $langauge
     * @param $is_tax
     * @return bool
     */
    public function get_translated_rewrite_base_slug( $name, $langauge, $is_tax ){
        global  $trp_taxonomy_slug_translation;

        //rebase $name for woocommerce. ex: default site language de_de then default slug for product cpt will be 'produkt' and that is how we save in the db. But in de_at (austrian) as there is no translation in the woocommerce mo $name will come as 'product' and we won't find the translation in the db
        global $trp_wc_permalinks;//this should be defined in woocommerce_filter_permalink_option() function
        if( isset($trp_wc_permalinks) ){
            if ($name == 'product-category') {
                $option_index = 'category_base';
            } elseif ($name == 'product-tag') {
                $option_index = 'tag_base';
            } elseif ($name == 'product') {
                $option_index = 'product_base';
            }

            if( !empty( $trp_wc_permalinks ) && !empty( $option_index ) && isset( $trp_wc_permalinks[$option_index] ) && $name != $trp_wc_permalinks[$option_index] ){
                $name = trim( $trp_wc_permalinks[ $option_index ], '\\/' );
            }
        }

        /* get the options from the database and store them in a global so we don't query the db on every call */
        if( $is_tax ){
            if( !isset($trp_taxonomy_slug_translation) )
                $trp_taxonomy_slug_translation = get_option( $this->string_translation_api_tax_slug->get_option_name(), '' );
        }
        else{
            $trp_cpt_slug_translation = get_option( $this->string_translation_api_post_type_slug->get_option_name(), '' );
        }


        if( $is_tax ){
            $slug_translations = $trp_taxonomy_slug_translation;
        }
        else {
            $slug_translations = $trp_cpt_slug_translation;
        }

        if (!empty($slug_translations)) {
            //remove any slashes from keys from saved translations in the database
            $trimmed_slug_translations = array();
            foreach($slug_translations as $key => $value ) {
                $trimmed_slug_translations[ trim( $key, '/\\' ) ] = $value;
            }

            $slug_translations = $trimmed_slug_translations;

            if( !empty( $slug_translations[$name] ) && !empty( $slug_translations[$name]['translationsArray'] ) && !empty( $slug_translations[$name]['translationsArray'][$langauge] ) && !empty( $slug_translations[$name]['translationsArray'][$langauge]['translated'] ) ){
                return remove_accents( trim( $slug_translations[$name]['translationsArray'][$langauge]['translated'], '/\\' ) );
            }

        }

        return false;

    }

    /**
     * Function to get pairs of translation slugs for taxonomies in an array (key => value pairs). If it is missing in a certain language it will return the original slug
     * @param $from_language
     * @param $to_langauge
     * @return array $from_slug will be the key and $to_slug the value
     */
    public function get_taxonomy_translated_slugs_pairs_for_languages( $from_language, $to_langauge ){
        global $trp_taxonomy_slug_translation;

        /* get the options from the database and store them in a global so we don't query the db on every call */
        if (!isset($trp_taxonomy_slug_translation))
            $trp_taxonomy_slug_translation = get_option( $this->string_translation_api_tax_slug->get_option_name(), '');

        $translation_pairs = $this->get_object_translated_slugs_pairs_for_languages( $trp_taxonomy_slug_translation, $from_language, $to_langauge );

        return $translation_pairs;
    }

    /**
     * Function to get pairs of translation base slugs for CPT in an array (key => value pairs). If it is missing in a certain language it will return the original slug
     * @param $from_language
     * @param $to_langauge
     * @return array $from_slug will be the key and $to_slug the value
     */
    public function get_cpt_translated_slugs_pairs_for_languages( $from_language, $to_langauge )    {
        $trp_cpt_slug_translation = get_option( $this->string_translation_api_post_type_slug->get_option_name(), '');

        $translation_pairs = $this->get_object_translated_slugs_pairs_for_languages( $trp_cpt_slug_translation, $from_language, $to_langauge );

        return $translation_pairs;

    }

    /**
     * Function to parse an array of either taxonomy or cpt slug translations and return pairs of slug translations
     * @param $trp_object_slug_translations
     * @param $from_language
     * @param $to_langauge
     * @return array
     */
    public function get_object_translated_slugs_pairs_for_languages( $trp_object_slug_translations, $from_language, $to_langauge ){
        $translation_pairs = array();
        if( !empty($trp_object_slug_translations) ){
            foreach( $trp_object_slug_translations as $original_slug => $transaltions ){
                $from_slug = $this->get_slug_from_translation_array($transaltions, $original_slug, $from_language);
                $to_slug =  $this->get_slug_from_translation_array($transaltions, $original_slug, $to_langauge);

                $translation_pairs[$from_slug] = $to_slug;
            }
        }
        return $translation_pairs;
    }

    /**
     * Function to get a speciffic value from the translations array of slugs. If it is for the default language or the value is not translated it will default to $original_slug
     * @param $transaltions
     * @param $original_slug
     * @param $language
     * @return mixed
     */
    public function get_slug_from_translation_array( $transaltions, $original_slug, $language ){
        if( $language === $this->settings['default-language'] )
            $slug = $original_slug;
        else if (!empty($transaltions['translationsArray'][$language]) && !empty($transaltions['translationsArray'][$language]['translated']))
            $slug = $transaltions['translationsArray'][$language]['translated'];
        else
            $slug = $original_slug;//default to original_slug so we always have a value

        return trim( $slug, '/\\' );
    }


    /**
     * Filter the links for the language switcher so it changes base slugs for taxonomies and post types. I think this can use improvements ?
     * @param $new_url
     * @param $url
     * @param $language
     * @return string|string[]
     */
    function filter_language_switcher_link( $new_url, $url, $language ){
        global $TRP_LANGUAGE;

        $new_url = urldecode($new_url);

        $tax_translated_slug_pairs = $this->get_taxonomy_translated_slugs_pairs_for_languages( $TRP_LANGUAGE, $language );
        $cpt_translated_slug_pairs = $this->get_cpt_translated_slugs_pairs_for_languages( $TRP_LANGUAGE, $language );
        $object_translated_slug_pairs = $tax_translated_slug_pairs + $cpt_translated_slug_pairs;

        //add compatibility with the product post type archive which takes the slug from the page that is set up as a Shop page in woocommerce settings
        if( class_exists( 'WooCommerce' ) ){

            $shop_page_from_slug = $this->get_woocommerce_shop_slug_in_language( $TRP_LANGUAGE );
            $shop_page_to_slug = $this->get_woocommerce_shop_slug_in_language( $language);

            if ( !is_null($shop_page_from_slug) && !is_null($shop_page_to_slug) && $shop_page_from_slug != $shop_page_to_slug ) {//we actually have a translation
                $url_parts = explode( '/' .$shop_page_from_slug . '/', $new_url );
                if( count( $url_parts ) > 1 ){//it is part of the url
                    //check that we are actually on the archive page for products (there should not be any / in the last parts )
                    if( strpos( end( $url_parts ), '/' ) === false ){
                        return $new_url = str_replace( '/' .$shop_page_from_slug . '/', '/' .$shop_page_to_slug . '/', $new_url );
                    }
                }
            }
        }

        foreach( $object_translated_slug_pairs as $from_slug => $to_slug ) {

            //handle translations for /%product_cat% slugs for products
            if( class_exists( 'WooCommerce' ) ) {
                if (preg_match('`(.+)(/%product_cat%)`', $from_slug, $matches) && preg_match('`(.+)(/%product_cat%)`', $to_slug, $to_matches)) {
                    $new_url = str_replace('/' . $matches[1] . '/', '/' . $to_matches[1] . '/', $new_url);
                }
            }

            $position = strpos($new_url, '/' . $from_slug . '/');
            if ($position !== false) {
                $new_url = substr_replace($new_url, '/' . $to_slug . '/', $position, strlen('/' . $from_slug . '/')); // replace just the first occurrence in the url, so we don't replace identical term slugs that can be positioned later in the url
            }

        }

        return $new_url;
    }

    /**
     * Function that replaces a term slug with its translation if it exists. It replaces only the last occurrence in the link to avoid replacing the taxonomy slug
     * @param $link
     * @param $term
     * @param $language
     * @return string|string[]
     */
    public function replace_last_occurrence_of_term_slug_in_link( $link, $term, $language ){
        if( is_object($term) ) {
            if(isset($term->term_id)) {
                $translated_slug = get_term_meta( $term->term_id, $this->human_translated_slug_meta . $language, true );
                if ( empty( $translated_slug ) )//if no human translated slug try to find an automatic translated slug
                    $translated_slug = get_term_meta( $term->term_id, $this->automatic_translated_slug_meta . $language, true );
                $translated_slug = trim( $translated_slug, '/\\' );
            }
            $original_slug = trim($term->slug, '/\\');

            if (!empty($translated_slug)) {
                $position = strrpos($link, '/' . urldecode( $original_slug ) . '/');
                if ($position !== false) {
                    $link = substr_replace($link, '/' . urldecode($translated_slug ) . '/', $position, strlen('/' . urldecode( $original_slug ) . '/')); // replace just the last occurrence in the url
                }
            }
        }

        return $link;
    }

    /**
     * Function that returns the original term slug based on a translation. it looks for terms in a certain taxonomy only
     * @param $slug
     * @param $taxonomy
     * @param $language
     * @return string
     */
    protected function get_original_term_slug( $slug, $taxonomy, $language ){
        global $wpdb;

        $all_possible_terms_ids = array();

        $term_args = apply_filters( 'trp_get_term_args', array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false
        ), $slug, $taxonomy, $language );

        $all_terms_in_taxonomy = get_terms( $term_args );

        $slug_decoded = urldecode($slug);
        $slug_encoded = urlencode($slug_decoded);
        if( !empty($all_terms_in_taxonomy) ) {

            foreach ( $all_terms_in_taxonomy as $term ){
                $all_possible_terms_ids[] = $wpdb->prepare( "%d", $term->term_id );
            }

            $translated_slug = $wpdb->get_results($wpdb->prepare(
                "
            SELECT *
            FROM $wpdb->termmeta
            WHERE ( meta_key = '%s' OR meta_key = '%s' )
                AND (meta_value = '%s' OR meta_value = '%s')
                AND term_id IN (".implode( ',', $all_possible_terms_ids ).")
            ", $this->human_translated_slug_meta . $language, $this->automatic_translated_slug_meta . $language, $slug_decoded, $slug_encoded
            ));

            if (!empty($translated_slug)) {
                $term_id = $translated_slug[0]->term_id;
                foreach ( $all_terms_in_taxonomy as $term ){
                    if( $term->term_id == $term_id ){
                        $slug = $term->slug;
                    }
                }

            }
        }

        return trim( $slug, '/\\' );
    }

    /**
     * Function that retrieves the lug of the shop page of woocomemerce in a language
     * @param $language
     * @return mixed|string|null
     */
    public function get_woocommerce_shop_slug_in_language( $language ){
        if( class_exists( 'WooCommerce' ) ){
            $shop_page_id = wc_get_page_id('shop');
            if( $shop_page_id ) {
                $shop_page_object = get_post($shop_page_id);
                if( $shop_page_object ) {
                    $shop_page_slug_in_language = $this->get_translated_slug($shop_page_object, $language);
                    if ($language === $this->settings['default-language'] || empty($shop_page_slug_in_language))
                        $shop_page_slug_in_language = $shop_page_object->post_name;

                    return $shop_page_slug_in_language;
                }
            }
        }
        return null;
    }

    /**
     * function that normalizez built in category and tag taxonomies which have special query vars
     * @param $taxonomy
     * @return string
     */
    public function trp_normalize_taxonomy_names( $taxonomy ){
        if( $taxonomy === 'category_name' )
            $actual_taxonomy = 'category';
        else if( $taxonomy === 'tag' )
            $actual_taxonomy = 'post_tag';
        else
            $actual_taxonomy = $taxonomy;

        return $actual_taxonomy;
    }

    /**
     * with the Buisness Directory Plugin
     * filter the wpbdp_category option that is used by the plugin directly to create links
     **/
    public function business_directory_plugin_compatibility( $value ){
        global $TRP_LANGUAGE;

        if( is_admin() || $TRP_LANGUAGE === $this->settings['default-language'] )
            return $value;

        $translated = $this->get_translated_rewrite_base_slug( $value, $TRP_LANGUAGE, true );
        if($translated)
            return $translated;

        return $value;
    }

    /**
     * Get the translated URL for a specific language based on source and target URLs.
     *
     * Takes a source URL, target URL and target language as parameters, and returns
     * the translated URL for the specified language.
     *
     * Supports ( default language -> secondary language ) | ( secondary language -> default language ) | ( secondary language -> secondary language ) translation
     *
     * @param string $original_url    The original URL as detected by translatepress in user content. Normally received via trp_get_url_for_language hook
     * @param string $url             The URL for which we search if a translation for slugs exists.
     * @param string $target_language The target language for which the translation is performed.
     *
     * @return string The translated URL for the specified language.
     */
    public function get_slug_translated_url_for_language($url, $original_url, $target_language ){

        $cache_key  = 'get_slug_translated_url_for_language_';
        $cached_url = $this->url_converter->check_if_url_is_valid_and_set_cache( $cache_key, $target_language, $original_url );

        if ( !isset( $cached_url['no_cache'] ) ) return $cached_url;

        $default_language = $this->settings['default-language'];
        $source_language  = $this->url_converter->get_lang_from_url_string( $original_url ) ? $this->url_converter->get_lang_from_url_string( $original_url ) : $default_language;

        $path_no_lang_slug = $this->get_path_no_lang_slug_from_url( $url );
        $translatable_slugs = $this->get_translatable_slugs_from_url( $url );
        $slug_pairs = $this->get_slugs_pairs_based_on_language( $translatable_slugs, $source_language, $target_language );

        // Add WooCommerce base slug translations using gettext (handles case where WooCommerce permalink uses English slug)
        $slug_pairs = $this->add_woocommerce_gettext_slug_pairs( $slug_pairs, $translatable_slugs, $target_language );

        $translated_path = $this->replace_slugs_in_url_path( $path_no_lang_slug, $slug_pairs );

        $final_url = str_replace( $path_no_lang_slug, $translated_path, $url );

        wp_cache_set( $cache_key . $cached_url['hash'], $final_url, 'trp' );

        return $final_url;
    }

    /**
     * Takes the URL as a parameters and returns its path with no language slug
     *
     * Duplicated function for SEO Pack
     *
     * @param $url
     * @return string
     */
    public function get_path_no_lang_slug_from_url( $url ) {
        $language      = $this->url_converter->get_lang_from_url_string( $url );
        $url_lang_slug = $this->url_converter->get_url_slug( $language );
        $url_object    = trp_cache_get( 'url_obj_' . hash( 'md4', $url ), 'trp' );

        if ( $url_object === false ) {
            $url_object = new \TranslatePress\Uri( $url );
            wp_cache_set( 'url_obj_' . hash( 'md4', $url ), $url_object, 'trp' );
        }

        // $path_no_lang_slug and $translated_path don't exist in $target_url with WordPress installed in sub folder.
        // Find the subfolder from abs_home (if it exists), strip it from both vars and then do the str replace.
        if ( $this->abs_home_path === null ) {
            $abs_home            = $this->url_converter->get_abs_home();
            $abs_home_object     = new \TranslatePress\Uri( $abs_home );
            $this->abs_home_path = untrailingslashit( strval( $abs_home_object->getPath() ) );
        }
        $url_path = trp_remove_prefix( $this->abs_home_path, strval( $url_object->getPath() ) );

        // null or empty string
        if ( empty( $url_lang_slug ) ) {
            $path_no_lang_slug = $url_path;
        } else {
            $path_no_lang_slug = preg_replace( '/^\/' . preg_quote( $url_lang_slug, '/' ) . '\/?/', '/', $url_path, 1 );
        }

        // Returning the path using strval() to avoid an empty check.
        return strval( $path_no_lang_slug );
    }

    /**
     * Takes the URL as a parameters and returns an array with all translatable slugs - supports comma separated values e.g. /my-path/term1,term2/
     *
     * @param $url
     * @return array
     */
    public function get_translatable_slugs_from_url( $url ){
        $path_no_lang_slug = $this->get_path_no_lang_slug_from_url( $url );

        $translatable_slugs = array_filter( explode( '/', $path_no_lang_slug ), function ( $slug ) {
            return !empty( $slug );
        });

        $translatable_slugs = array_reduce( $translatable_slugs, function ( $carry, $slug ) {
            // Handle comma-separated values present in slugs
            if ( strpos( $slug, ',' ) !== false || strpos( $slug, '+' ) !== false ) {
                $terms_commas = array();
                $terms = explode( '+', $slug );

                foreach ( $terms as $term ){
                    $terms_commas[] = explode( ',', $term);
                }

                foreach ( $terms_commas as $terms_comma){
                    $carry = array_merge( $carry, $terms_comma );
                }
            } else {
                // If no comma, simply add the slug
                $carry[] = $slug;
            }

            return $carry;
        }, []);

        return $translatable_slugs;
    }

    /**
     * Decides which method needs to be called depending on the language combination
     *
     * Returns false in case there are no translatable slugs or no translations were found
     *
     * @param $translatable_slugs
     * @param $source_language
     * @param $target_language
     * @return array|false
     */
    public function get_slugs_pairs_based_on_language( $translatable_slugs, $source_language, $target_language ){
        if ( empty( $translatable_slugs ) ) return false;

        $default_language = $this->settings['default-language'];
        $was_data_migration_completed = get_option( 'trp_migrate_old_slug_to_new_parent_and_translate_slug_table_term_meta_284', 'not_set' );

        $slug_pairs = [];
        if ( $target_language === $default_language ) {
            $slug_pairs = $this->slug_query->get_original_slugs_from_translated( $translatable_slugs, $source_language );

            if ( $was_data_migration_completed == 'no' ) {
                if ( !empty( $slug_pairs ) ){
                    foreach ( $slug_pairs as $original => $translated ){
                        $translatable_slugs = array_diff($translatable_slugs, array( strtolower( urlencode( urldecode( $original ) ) ) ) );

                    }
                }

                if ( !empty( $translatable_slugs )) {
                    $slug_pairs_old_db = $this->slug_query->get_original_slugs_from_translated_if_db_migration_was_not_completed( $translatable_slugs, $source_language );

                    if ( !empty( $slug_pairs_old_db ) )
                        $slug_pairs = array_merge( $slug_pairs, $slug_pairs_old_db );
                }
            }
        }
        else if ( $source_language === $default_language ) {
            $slug_pairs = $this->slug_query->get_translated_slugs_from_original( $translatable_slugs, $target_language );

            if ( $was_data_migration_completed == 'no' ) {
                if ( !empty( $slug_pairs ) ){
                    foreach ( $slug_pairs as $translated => $original ){

                        $translatable_slugs = array_diff($translatable_slugs, array( strtolower( urlencode( urldecode( $translated) ) ) ) );

                    }
                }

                if ( !empty( $translatable_slugs ) ) {
                    $slug_pairs_old_db = $this->slug_query->get_translated_slugs_from_original_if_db_migration_was_not_completed( $translatable_slugs, $target_language );

                    if ( !empty( $slug_pairs_old_db ) )
                        $slug_pairs = array_merge( $slug_pairs, $slug_pairs_old_db );
                }
            }
        }
        // Secondary language -> secondary language translation
        else {
            $original_slugs = $this->slug_query->get_original_slugs_from_translated( $translatable_slugs, $source_language );

            /** We expect an associative array containing default-slug => translated-slug. In case no original slugs are found, serve the original slugs under this form. */
            if ( empty( $original_slugs) ) {
                $original_slugs = array_combine( $translatable_slugs, $translatable_slugs );
            }else{
                foreach ($translatable_slugs as $translatable_slug ){
                    $translatable_slug = strtolower( $translatable_slug );
                    if ( !isset( $original_slugs[ $translatable_slug ] ) ) {
                        $original_slugs[$translatable_slug] = $translatable_slug;
                    }
                }
            }

            $translated_slugs = $this->slug_query->get_translated_slugs_from_original( $original_slugs, $target_language );

            foreach ( $original_slugs as $key => $value ){
                $slug_pairs[$key] = isset( $translated_slugs[$value] ) ? $translated_slugs[$value] : $value;
            }

            if ( $was_data_migration_completed == 'no' ) {
                if ( !empty( $slug_pairs ) ){
                    foreach ( $slug_pairs as $key => $translated ){
                        if ( $key !== $translated) {
                            $translatable_slugs = array_diff( $translatable_slugs, array( strtolower( urlencode( urldecode( $key ) ) ) ) );
                        }
                    }
                }

                if ( !empty( $translatable_slugs ) ) {
                    $original_slugs_old_db = $this->slug_query->get_original_slugs_from_translated_if_db_migration_was_not_completed( $translatable_slugs, $source_language );

                    if ( empty( $original_slugs_old_db ) ) $original_slugs_old_db = array_combine( $translatable_slugs, $translatable_slugs );

                    $translated_slugs_old_db = $this->slug_query->get_translated_slugs_from_original_if_db_migration_was_not_completed( $original_slugs_old_db, $target_language );

                    $slug_pairs_old_db = [];

                    foreach ( $original_slugs_old_db as $key => $value ) {
                        if ( isset( $translated_slugs_old_db[ $value ] ) )
                            $slug_pairs_old_db[ $key ] = $translated_slugs_old_db[ $value ];
                        else
                            $slug_pairs_old_db[ $key ] = $value;
                    }

                    if ( !empty( $slug_pairs_old_db ) )
                        $slug_pairs = array_merge( $slug_pairs, $slug_pairs_old_db );
                    else {
                        // todo delete if we do not return original_slugs anymore
                        $original_slugs = array_merge( $original_slugs, $original_slugs_old_db );
                    }
                }
            }

            if ( empty( $slug_pairs ) ) return $original_slugs;
        }

        return !empty( $slug_pairs ) ? $slug_pairs : false;
    }

    /**
     * It's important that $url_path contains a path, not a full url with scheme, domain, GET parameters etc.
     *
     * @param $url_path string URL path. Nothing else, no GET params, no domain
     * @param $slug_pairs
     * @return mixed|string
     */
    public function replace_slugs_in_url_path( $url_path, $slug_pairs ){
        if ( $slug_pairs === false )
            return $url_path;

        $segments = array_map( 'strtolower', explode( '/', $url_path ));

        // Replace only whole slugs within each segment
        $segments = array_map( function($segment) use ($slug_pairs) {

            //map slugs divided bt + in url
            if ( strpos( $segment, '+' ) !== false ) {

                $implode_on_plus = array();
                $segment_divided_by_plus = explode( '+', $segment );

                foreach ( $segment_divided_by_plus as $segment_plus ) {
                    $encoded_segment_divided_by_plus = strtolower( urlencode( $segment_plus) );

                    if ( isset( $slug_pairs[ $encoded_segment_divided_by_plus ] ) ){
                        $implode_on_plus[] = $slug_pairs[ $encoded_segment_divided_by_plus ];
                    }elseif ( isset( $slug_pairs[ $segment_plus]) ){
                        $implode_on_plus[] = strtolower( $slug_pairs[ $segment_plus ] );
                    }else{
                        $implode_on_plus[] = strtolower( $segment_plus );
                    }
                }

                return implode( '+', $implode_on_plus );
            }

            //map slugs divided by , in url
            if ( strpos( $segment, ',' ) !== false ) {

                $implode_on_comma = array();
                $segment_divided_by_comma = explode( ',', $segment );

                foreach ( $segment_divided_by_comma as $segment_comma ) {
                    $encoded_segment_divided_by_comma = strtolower( urlencode( $segment_comma) );

                    if ( isset( $slug_pairs[ $encoded_segment_divided_by_comma ] ) ){
                        $implode_on_comma[] = strtolower( $slug_pairs[ $encoded_segment_divided_by_comma ] );
                    }elseif ( isset( $slug_pairs[ $segment_comma ]) ){
                        $implode_on_comma[] = strtolower( $slug_pairs[ $segment_comma ] );
                    }else{
                        $implode_on_comma[] = strtolower( $segment_comma );
                    }
                }

                return implode( ',', $implode_on_comma );
            }

            $segment = strtolower( urlencode( urldecode( $segment ) ) );


            // Check each segment for a direct match with a slug and replace it if found
            return isset( $slug_pairs[$segment] ) ? strtolower( $slug_pairs[$segment]) : strtolower( $segment );
        }, $segments );

        // Reassemble the URL path from the updated segments
        $url_path = implode( '/', $segments );

        return $url_path;
    }

    /**
     * Translate slugs from url path back to original language. Keep the translated language slug
     *
     * This function should work only if we are not on the default language, if we are we should not modify the $_SERVER['REQUEST_URI']
     * @return void
     */
    public function translate_request_uri() {
        //we do not need the plugin in cron requests
        if( wp_doing_cron() )
            return;

        global $TRP_LANGUAGE, $TRP_ORIGINAL_REQUEST_URI;

        if ( $TRP_LANGUAGE === $this->settings['default-language'] )
            return;

        if ( empty( $_SERVER['REQUEST_URI'] ) )
            return;

        // it's very important to call cur_page_url here because it sets the cache for cur_page_url_translated_slugs
        $url = $this->url_converter->cur_page_url();

        if ( apply_filters( 'trp_is_admin_link_for_request_uri', false, $url ) )
            return;

        if ( $this->is_redirect_needed( $url ) )
            return; // redirect will happen on 'template_redirect'

        // Process the same way as get_lang_from_url_string() but without caching because we only change the slug, not the language in the URL
        // Previous implementation was calling get_lang_from_url_string() further down the line, triggering the same hash but without language param in certain cases
        // Not extracting it in another method, the flow is clear enough
        $default_language = $this->settings['default-language'];
        $source_language  = $this->url_converter->get_lang_from_url_string( $url ) ? $this->url_converter->get_lang_from_url_string( $url ) : $default_language;

        $path_no_lang_slug = $this->get_path_no_lang_slug_from_url( $url );
        $translatable_slugs = $this->get_translatable_slugs_from_url( $url );
        $slug_pairs = $this->get_slugs_pairs_based_on_language( $translatable_slugs, $source_language, $default_language );

        // Filter out WooCommerce base slugs if the target doesn't match the actual permalink configuration.
        // This prevents translating e.g. "product" -> "produs" when WooCommerce uses "/product/" in permalinks.
        $slug_pairs = $this->filter_woocommerce_base_slugs_for_request_uri( $slug_pairs );

        $translated_path = $this->replace_slugs_in_url_path( $path_no_lang_slug, $slug_pairs );

        $translated_url = str_replace( $path_no_lang_slug, $translated_path, $url );

        // Extract the path, query, and fragment from the translated URL (everything after the domain)
        $translated_url_obj = new \TranslatePress\Uri( $translated_url );
        $new_request_uri = sanitize_url( strval( $translated_url_obj->getPath() ) );
        if ( $query = $translated_url_obj->getQuery() ) {
            $new_request_uri .= '?' . $query;
        }
        if ( $fragment = $translated_url_obj->getFragment() ) {
            $new_request_uri .= '#' . $fragment;
        }

        $TRP_ORIGINAL_REQUEST_URI = sanitize_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ) );

        do_action('trp_translated_request_uri', $new_request_uri, $url );

        $_SERVER['REQUEST_URI'] = $new_request_uri;

        /** cur_page_url() function from class-url-converter may have been called with false before this point
         * and set the cache incorrectly. This ensures that from this point on, it actually returns the
         * untranslated version of the current url
         */
        wp_cache_delete( 'cur_page_url_untranslated_slugs', 'trp' );


        // It's also very important to call cur_page_url here because it sets the cache for cur_page_url_untranslated_slugs
        $this->url_converter->cur_page_url( false );
    }

    /**
     * Filter WooCommerce base slug translations for REQUEST_URI to use the actual permalink configuration.
     *
     * When translating REQUEST_URI back to the default language, WooCommerce base slugs should
     * always translate to the actual WooCommerce permalink setting, not the database "original"
     * (which is the gettext translation for the default language).
     *
     * Example scenarios:
     * - URL has "/product/" (English), WC permalink is "/product/", target would be "produs" (Romanian gettext)
     *   -> Remove this pair, keep "/product/" as-is
     * - URL has "/produkt/" (German gettext), WC permalink is "/product/", target would be "produs" (Romanian gettext)
     *   -> Change target to "/product/" so WordPress can find the page
     *
     * @param array|false $slug_pairs The slug pairs from get_slugs_pairs_based_on_language()
     * @return array|false Filtered slug pairs
     */
    protected function filter_woocommerce_base_slugs_for_request_uri( $slug_pairs ) {
        if ( empty( $slug_pairs ) || ! is_array( $slug_pairs ) ) {
            return $slug_pairs;
        }

        // Only filter if WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            return $slug_pairs;
        }

        // WooCommerce base slugs to check (English)
        $wc_base_slugs = array( 'product', 'product-category', 'product-tag' );

        foreach ( $wc_base_slugs as $english_slug ) {
            // Get the actual permalink slug configured in WooCommerce
            $actual_permalink_slug = $this->get_woocommerce_permalink_base_slug( $english_slug );

            if ( empty( $actual_permalink_slug ) ) {
                continue;
            }

            $actual_permalink_lower = strtolower( $actual_permalink_slug );

            // Get all gettext translations for this WooCommerce slug across all languages
            $gettext_translations = array( $actual_permalink_lower ); // Include the permalink itself
            foreach ( $this->settings['translation-languages'] as $lang ) {
                $gettext_slug = trp_x( $english_slug, 'slug', 'woocommerce', $lang );
                if ( ! empty( $gettext_slug ) ) {
                    $gettext_translations[] = strtolower( $gettext_slug );
                }
            }
            $gettext_translations = array_unique( $gettext_translations );

            // Check each slug pair
            foreach ( $slug_pairs as $source_slug => $target_slug ) {
                $source_lower = strtolower( $source_slug );
                $target_lower = strtolower( $target_slug );

                // If the source is a WooCommerce slug (either permalink or any gettext translation)
                if ( in_array( $source_lower, $gettext_translations, true ) ) {
                    if ( $source_lower === $actual_permalink_lower ) {
                        // Source is the WooCommerce permalink - remove the pair to keep it as-is
                        unset( $slug_pairs[ $source_slug ] );
                    } elseif ( $target_lower !== $actual_permalink_lower ) {
                        // Source is a gettext translation, target is not the permalink
                        // Change target to the actual permalink so WordPress can find the page
                        $slug_pairs[ $source_slug ] = $actual_permalink_slug;
                    }
                    // If target already equals permalink, keep the pair as-is
                }
            }
        }

        return $slug_pairs;
    }

    /**
     * Add WooCommerce base slug translations using gettext.
     *
     * This method handles WooCommerce base slug translations for URL generation when:
     * 1. The WooCommerce permalink uses English slugs (e.g., /product/) but the default
     *    language is non-English (e.g., Romanian with gettext "produs")
     * 2. The URL contains a gettext-translated slug (e.g., /produkt/ in German) that needs
     *    to be translated to another language
     *
     * For each WooCommerce base slug, it:
     * - Builds a map of all known variations (permalink + all language gettext translations)
     * - Checks if any variation is in the URL
     * - Determines the correct target: gettext translation for target language, or
     *   WooCommerce permalink if target is default language and permalink differs from gettext
     *
     * @param array|false $slug_pairs         Existing slug pairs from the database
     * @param array       $translatable_slugs Slugs extracted from the URL
     * @param string      $target_language    The target language for translation
     * @return array|false Updated slug pairs with WooCommerce translations
     */
    public function add_woocommerce_gettext_slug_pairs( $slug_pairs, $translatable_slugs, $target_language ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return $slug_pairs;
        }

        if ( ! is_array( $slug_pairs ) ) {
            $slug_pairs = array();
        }

        // WooCommerce base slugs (English)
        $wc_base_slugs = array( 'product', 'product-category', 'product-tag' );

        foreach ( $wc_base_slugs as $english_slug ) {
            // Get the actual permalink slug configured in WooCommerce
            $actual_permalink_slug = $this->get_woocommerce_permalink_base_slug( $english_slug );

            if ( empty( $actual_permalink_slug ) ) {
                continue;
            }

            $permalink_lower = strtolower( $actual_permalink_slug );

            // Build a map of all known variations of this WooCommerce slug
            // Key = lowercase slug, Value = original case slug
            $known_variations = array( $permalink_lower => $actual_permalink_slug );
            foreach ( $this->settings['translation-languages'] as $lang ) {
                $gettext_slug = trp_x( $english_slug, 'slug', 'woocommerce', $lang );
                if ( ! empty( $gettext_slug ) ) {
                    $known_variations[ strtolower( $gettext_slug ) ] = $gettext_slug;
                }
            }

            // Check if any known variation is in the URL
            $found_source_slug = null;
            foreach ( $translatable_slugs as $url_slug ) {
                $url_slug_lower = strtolower( $url_slug );
                if ( isset( $known_variations[ $url_slug_lower ] ) ) {
                    $found_source_slug = $url_slug_lower;
                    break;
                }
            }

            if ( $found_source_slug === null ) {
                continue; // This WooCommerce slug is not in the URL
            }

            // Skip if already handled in slug_pairs
            if ( isset( $slug_pairs[ $found_source_slug ] ) ) {
                continue;
            }

            $default_language = $this->settings['default-language'];
            $default_gettext = trp_x( $english_slug, 'slug', 'woocommerce', $default_language );
            $target_gettext = trp_x( $english_slug, 'slug', 'woocommerce', $target_language );
            $has_custom_permalink = strtolower( $actual_permalink_slug ) !== strtolower( $default_gettext );

            // When WooCommerce permalink is custom (e.g. /tool/), keep it stable across languages.
            // Slug translations from DB should handle real translations for custom bases.
            if ( $has_custom_permalink ) {
                $target_slug = $actual_permalink_slug;
            } else {
                $target_slug = ! empty( $target_gettext ) ? $target_gettext : $actual_permalink_slug;
            }

            $target_slug_lower = strtolower( $target_slug );

            // Only add if source and target are different
            if ( $found_source_slug !== $target_slug_lower ) {
                $slug_pairs[ $found_source_slug ] = $target_slug_lower;
            }
        }

        return ! empty( $slug_pairs ) ? $slug_pairs : false;
    }

    /**
     * Get a single slug component for WooCommerce permalink bases.
     *
     * Product bases can include placeholders like `%product_cat%` (for example `product/%product_cat%`).
     * For slug matching/replacement we only need the static product base segment (`product`).
     *
     * @param string $english_slug
     * @return string
     */
    protected function get_woocommerce_permalink_base_slug( $english_slug ) {
        $saved_permalink_slug = $this->url_converter->trp_get_woocommerce_saved_permalink( $english_slug );
        if ( empty( $saved_permalink_slug ) ) {
            return '';
        }

        $saved_permalink_slug = trim( $saved_permalink_slug, '/\\' );
        if ( $english_slug !== 'product' ) {
            return $saved_permalink_slug;
        }

        $slug_components = array_values( array_filter( explode( '/', $saved_permalink_slug ), function( $component ) {
            return $component !== '' && $component !== '%product_cat%';
        } ) );

        return ! empty( $slug_components ) ? $slug_components[0] : '';
    }

    /**
     * Used for internal links, generated using WP functions
     *
     * @param $url string Expected to already have the language slug appended to it using trp_home_url hook
     * @return string
     */
    public function translate_slugs_on_internal_links( $url ) {
        global $TRP_LANGUAGE;
        $abs_home = $this->url_converter->get_abs_home();
        $home_url = home_url();
        $url_without_language = str_replace( untrailingslashit($home_url), untrailingslashit($abs_home), $url );

        if ( $url === $url_without_language ) return  apply_filters('trp_translate_slugs_on_internal_links', $url, $TRP_LANGUAGE);

        return apply_filters('trp_translate_slugs_on_internal_links', $this->get_slug_translated_url_for_language( $url, $url_without_language, $TRP_LANGUAGE ), $TRP_LANGUAGE);
    }

    /*
     * We need to filter admin_links such as wp-json/wp/v2/ , /wp-admin/ , /wp-login/ from the translate_request_uri function
     *
     */
    public function verify_if_is_admin_link_for_slug_translation ( $is_admin_link, $url ){


        $admin_url = admin_url();

        $wp_login_url = wp_login_url();

        if ( strpos( $url, $admin_url ) !== false || strpos( $url, $wp_login_url ) !== false || strpos( $url, 'wp-login.php') !== false ){
            $is_admin_link = true;
        }

        return apply_filters('trp_is_admin_link', $is_admin_link, $url, $admin_url, $wp_login_url);
    }

    public function verify_if_is_rest_api_slug_translation( $is_rest_api_request ){
        $trp = TRP_Translate_Press::get_trp_instance();
        $this->translation_manager = $trp->get_component('translation_manager');

        $is_rest_api_request = $this->translation_manager::is_rest_api_request();
        $is_custom_api_request = isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
        $is_ajax_request = defined( 'DOING_AJAX' ) && DOING_AJAX;

        return $is_rest_api_request || $is_custom_api_request || $is_ajax_request;
    }

    /**
     * Fix oEmbed post ID resolution for URLs with translated slugs.
     *
     * When the oEmbed REST endpoint receives a URL with a translated slug,
     * url_to_postid() cannot find the post because it expects the original slug.
     * This filter translates the slug back to the default language and retries.
     *
     * @param int    $post_id The post ID (0 if not found).
     * @param string $url     The requested oEmbed URL.
     * @return int The resolved post ID.
     */
    public function fix_oembed_request_post_id( $post_id, $url ) {
        if ( $post_id !== 0 ) {
            return $post_id;
        }

        $language = $this->url_converter->get_lang_from_url_string( $url );
        if ( empty( $language ) || $language === $this->settings['default-language'] ) {
            return $post_id;
        }

        $url_in_default_language = $this->get_slug_translated_url_for_language( $url, $url, $this->settings['default-language'] );

        if ( $url_in_default_language !== $url ) {
            $post_id = url_to_postid( $url_in_default_language );
        }

        return $post_id;
    }

    public function is_the_slug_orphan( $slug_data ){

        global $wpdb;

        if ( $slug_data['type'] == 'post' ) {

            $sql = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_name = %s", $slug_data['original'] );

        }elseif ( $slug_data['type'] == 'term' ) {

            $sql = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE slug = %s", $slug_data['original'] );

        }elseif ( $slug_data['type'] == 'taxonomy' ){

            $taxonomies = get_taxonomies(['public' => true ], 'objects');
            if ( isset( $taxonomies[ $slug_data['original'] ] ) ){
                return false;
            }else{
                return true;
            }

        }elseif ( $slug_data['type'] == 'post-type-base' ){

            $post_types = get_post_types( ['publicly_queryable' => true], 'objects' );
            if ( isset( $post_types[ $slug_data['original'] ] ) ){
                return false;
            }else{
                return true;
            }

        }else {
            return false;
        }
        if ( $wpdb->get_var( $sql ) ){
            return false;
        }else{
            return true;
        }

    }
    /**
     * Add suffix to the post / term slug in case a translation with the same value is found
     *
     * Hooked into wp_insert_post_data, wp_insert_term_data and wp_update_term_data
     *
     * @param $data
     * @param $extra
     * @param $taxonomy
     * @return mixed
     */
    public function ensure_post_or_term_slug_uniqueness($data, $extra = null, $taxonomy = '') {

        // Only apply slug uniqueness check to public post types
        // Private post types (wp_template, wp_template_part, revision, etc.) don't need this
        // and modifying their slugs can cause issues like duplicate templates in Site Editor
        if ( isset( $data['post_type'] ) ) {
            $post_type_object = get_post_type_object( $data['post_type'] );
            if ( ! $post_type_object || ! $post_type_object->public ) {
                return $data;
            }
        }

        global $wpdb;

        $table_name_translations = $wpdb->prefix . 'trp_slug_translations';
        $table_name_original = $wpdb->prefix . 'trp_slug_originals';

        // Determine if it's a term or a post based on what data is provided
        $is_term = isset( $data['post_name'] );

        $slug_field = $is_term ? 'post_name' : 'slug';

        $wp_slug = $data[$slug_field];

        if ( empty( $wp_slug ) ) return $data;

        $new_slug = $wp_slug;
        $suffix = 2;

        // Loop until we find an available suffix
        // We don't need to loop until we find an available suffix because the funtcions insert_s;ugs() takes care of adding the correct suffix to the translation
        while ( !empty( $slug_original_id = $wpdb->get_results( $wpdb->prepare( "SELECT original_id FROM $table_name_translations WHERE translated = %s", $new_slug ), ARRAY_A) )) {
            foreach ( $slug_original_id as $array_key => $array ) {
                $slug_original_name_and_type = $wpdb->get_results( $wpdb->prepare( "SELECT id, original, type FROM $table_name_original WHERE id = %s", $array['original_id'] ), ARRAY_A );

                if ( empty( $slug_original_name_and_type ) ) {

                    $sql = $wpdb->prepare( "DELETE FROM $table_name_translations WHERE original_id = %d", $array[ 'original_id' ] );

                    $result = $wpdb->query( $sql );

                    if ( $result !== false ) {

                        unset( $slug_original_id[ $array_key ] );
                        continue;

                    }

                }
                if ( $slug_original_name_and_type[0]['original'] == $new_slug ) {
                    return $data;
                } elseif ( preg_match( '/^' . preg_quote( $new_slug, '/' ) . '\-\d+$/', $slug_original_name_and_type[0]['original'] ) ) {

                    $original_to_delete_translations = $wpdb->get_results( $wpdb->prepare( "SELECT translated, language, status FROM $table_name_translations WHERE original_id = %s", $slug_original_name_and_type[0]['id'] ), ARRAY_A );

                    $sql = $wpdb->prepare( "DELETE FROM $table_name_original WHERE id = %d", $slug_original_name_and_type[0]['id'] );
                    $result = $wpdb->query( $sql );

                    if ( $result !== false ) {

                        unset( $slug_original_id[ $array_key ] );

                    }

                } elseif
                ( $this->is_the_slug_orphan( $slug_original_name_and_type[0] ) ) {

                    $sql = $wpdb->prepare( "DELETE FROM $table_name_original WHERE id = %d", $slug_original_name_and_type[0]['id'] );

                    $result = $wpdb->query( $sql );

                    if ( $result !== false ) {

                        unset( $slug_original_id[ $array_key ] );

                    }

                }
            }

            if ( !empty( $slug_original_id ) ){

                $new_slug = $wp_slug . '-' . $suffix;

                $suffix++;
            }else{
                if ( isset( $original_to_delete_translations ) && !empty( $original_to_delete_translations ) ){
                    foreach ( $original_to_delete_translations as $key => $translation ){
                        $formed_array['original'] = $new_slug;
                        $formed_array['translated'] = $translation['translated'];
                        $formed_array['status'] = $translation['status'];
                        if ( isset( $slug_original_name_and_type) ) {
                            $formed_array['type'] = $is_term ? 'post' : 'term';
                        }

                        $insert_slugs_with_translations = $this->slug_query->insert_slugs( array( $formed_array ), $translation['language'] );
                    }

                }else {
                    $formed_array['original'] = $new_slug;
                    if ( isset( $slug_original_name_and_type )  && !empty( $slug_original_name_and_type)) {
                        $formed_array['type'] = $is_term ? 'post' : 'term';
                    }

                    $insert_original_slug = $this->slug_query->insert_original_slugs( array( $formed_array ) );

                }

                break;
            }
        }

        // Update the slug if a new one was generated
        if ( $new_slug !== $wp_slug ) {
            $data[$slug_field] = $new_slug;
        }

        return $data;
    }

    /**
     * WooCommerce uses $_SERVER['REQUEST_URI'] when setting the reset password cookie, so we need to replicate the behavior using the current URL
     * This is due to the way we translate URL's (by modifying request_uri in order to load the correct page)
     *
     * @return void
     */
    public function set_reset_pass_cookie(){
        global $TRP_ORIGINAL_REQUEST_URI;
        global $TRP_LANGUAGE;

        if ( !function_exists('is_account_page' ) || !is_account_page() )
            return;

        $rp_cookie = 'wp-resetpass-' . COOKIEHASH;

        if ( !( isset( $_GET['key'] ) && ( isset( $_GET['id'] ) || isset( $_GET['login'] ) ) ) )
            return;

        // If available, get $user_id from query string parameter for fallback purposes.
        if ( isset( $_GET['login'] ) ) {
            $user    = get_user_by( 'login', sanitize_user( wp_unslash( $_GET['login'] ) ) );
            $user_id = $user ? $user->ID : 0;
        } else
            $user_id = absint( $_GET['id'] );

        // If the reset token is not for the current user, ignore the reset request
        $logged_in_user_id = get_current_user_id();

        if ( $logged_in_user_id && $logged_in_user_id !== $user_id ) {
            wc_add_notice( __( 'This password reset key is for a different user account. Please log out and try again.', 'woocommerce' ), 'error' );
            return;
        }

        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
        $value = sprintf( '%d:%s', $user_id, wp_unslash( $_GET['key'] ) ); // phpcs:ignore
        $redirect_url = wc_lostpassword_url();

        if ( ! empty( $TRP_LANGUAGE ) && $TRP_LANGUAGE !== $this->settings['default-language'] ) {
            $redirect_url = $this->url_converter->get_url_for_language( $TRP_LANGUAGE, $redirect_url, '' );
        }

        $redirect_args = array(
            'show-reset-form' => 'true',
        );

        if ( ! empty( $action ) ) {
            $redirect_args['action'] = $action;
        }

        $redirect_url = add_query_arg( $redirect_args, $redirect_url );

        $cookie_base_url = wc_get_page_permalink( 'myaccount' );

        if ( ! empty( $TRP_LANGUAGE ) && $TRP_LANGUAGE !== $this->settings['default-language'] ) {
            $cookie_base_url = $this->url_converter->get_url_for_language( $TRP_LANGUAGE, $cookie_base_url, '' );
        }

        $rp_path = wp_parse_url( $cookie_base_url, PHP_URL_PATH );

        if ( empty( $rp_path ) && !empty( $TRP_ORIGINAL_REQUEST_URI ) ) {
            $rp_path = current( explode( '?', wp_unslash( $TRP_ORIGINAL_REQUEST_URI ) ) );
        }

        if ( empty( $rp_path ) ) {
            $rp_path = '/';
        }

        if ( $value )
            setcookie( $rp_cookie, $value, 0, $rp_path, COOKIE_DOMAIN, is_ssl(), true );
        else
            setcookie( $rp_cookie, ' ', time() - YEAR_IN_SECONDS, $rp_path, COOKIE_DOMAIN, is_ssl(), true );

        wp_safe_redirect(
            $redirect_url
        );
        exit;
    }
}
