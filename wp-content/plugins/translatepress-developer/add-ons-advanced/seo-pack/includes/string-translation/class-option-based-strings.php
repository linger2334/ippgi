<?php


// Exit if accessed directly
if ( !defined('ABSPATH' ) )
    exit();

/** Functions useful for cpt slugs and taxonomy slugs */
class TRP_IN_SP_Option_Based_Strings {
    private $trp_slug_query;
    private $editor_actions;

    public function __construct(){
        $trp          = TRP_Translate_Press::get_trp_instance();
        $trp_settings = $trp->get_component( 'settings' );
        $settings     = $trp_settings->get_settings();

        $this->trp_slug_query = new TRP_Slug_Query();
        $this->editor_actions = new TRP_IN_SP_Editor_Actions( $this->trp_slug_query, $settings );
    }

    public function get_public_slugs( $type, $include_labels = false, $include_items = array(), $only_with_slugs = true ) {
        $exclude_array = apply_filters( 'trp_exclude_' . $type . '_from_translation', array() );
        $slugs         = call_user_func( 'get_' . $type, array(), 'objects' );
        $return        = array();

        foreach ( $slugs as $item ) {
            $is_public             = (bool) $item->public;
            $is_publicly_queryable = (bool) $item->publicly_queryable;

            if ( ( count( $include_items ) == 0 || in_array( $item->name, $include_items ) ) &&
                $is_public &&
                ( !$only_with_slugs || ( $is_publicly_queryable && $item->rewrite !== false && isset( $item->rewrite['slug'] ) && !in_array( $item->rewrite['slug'], $exclude_array ) ) )
            ) {
                if ( $include_labels ) {
                    $return[ $item->name ] = trim( $item->label, '\\/');
                } else {
                    $return[] = ( $only_with_slugs ) ? trim( $item->rewrite['slug'], '\\/') : trim( $item->name, '\\/');
                }
            }
        }

        return apply_filters( 'trp_to_translate_' . $type . '_slugs_array', $return, $type, $include_labels );
    }

    public function get_strings_for_option_based_slug( $type, $option_name, $all_slugs ) {
        $trp                = TRP_Translate_Press::get_trp_instance();
        $string_translation = $trp->get_component( 'string_translation' );
        $trp_query          = $trp->get_component( 'query' );
        $config             = $string_translation->get_configuration_options();
        $trp_settings       = $trp->get_component( 'settings' );
        $settings           = $trp_settings->get_settings();
        $helper             = new TRP_String_Translation_Helper();

        $dictionary_by_original = [];
        $found_inactive_slug    = false;
        $items_found            = 0;

        // convert string array to associative array in the format array['slug'] = 'slug'
        $associative_all_public_slugs = array();

        foreach ( $all_slugs as $slug ) {
            $associative_all_public_slugs[ $slug ] = $slug;
        }

        $slugs_from_tp_table = [];
        if ( apply_filters( 'trp_show_inactive_slugs_in_editor', true, $type ) ) {
            $slugs_from_tp_table_assoc = $this->trp_slug_query->get_original_slugs( [ 'slug_type' => $type ] );
            foreach ( $slugs_from_tp_table_assoc as $slug_from_tp_table_assoc ) {
                $slugs_from_tp_table[ $slug_from_tp_table_assoc['original'] ] = $slug_from_tp_table_assoc['original'];
            }
        }
        $all_slugs = array_unique( $associative_all_public_slugs + $slugs_from_tp_table );
        $translated_slugs = $this->trp_slug_query->get_translated_slugs_from_original( array_values( $all_slugs ) );

        $translationsArray = new TRP_String_Translation_Array( array_values( $all_slugs ), $translated_slugs, $type );
        $translationsArray = $translationsArray->get_formatted_translations_array();

        foreach ( $translationsArray as $key => $array ){
            $dictionary_by_original[ $key ] = [
                'original' => $key,
                'type'     => $type,
                'translationsArray' => $array
            ];
        }


        foreach ( $dictionary_by_original as $key => $entry ) {
            if ( isset( $all_slugs[ $entry['original'] ] ) ) {
                // found slug, don't add it again in the next foreach
                unset( $all_slugs[ $entry['original'] ] );

            }
            if ( !isset( $associative_all_public_slugs[$dictionary_by_original[$key]['original']] ) ) {
                // found a previously detected slug that no longer exists
                $dictionary_by_original[$key]['inactive'] = true;
                $dictionary_by_original[$key]['original'] .= esc_html__('(inactive)', 'translatepress-multilingual' );
            }

            // make sure all languages have arrays, even those added later
            foreach( $settings['translation-languages'] as $language ){
                if ( $settings['default-language'] === $language ) {
                    continue;
                }

                if ( !isset( $dictionary_by_original[$key]['translationsArray'][$language] ) ){
                    $dictionary_by_original[$key]['translationsArray'][ $language ] = array(
                        'editedTranslation' => '',
                        'translated'        => '',
                        'status'            => $trp_query->get_constant_not_translated(),
                        'original_id'       => $dictionary_by_original[$key]['original'],
                    );
                }
            }

        }

        if ( $found_inactive_slug ) {
            $dictionary_by_original = array_values( $dictionary_by_original );
        }

        if ( !empty ( $all_slugs ) ) {
            // add to dictionary all the newly found all_slugs
            foreach ( $all_slugs as $slug ) {
                $translationsArray = array();
                foreach ( $settings['translation-languages'] as $language ) {
                    if ( $settings['default-language'] === $language ) {
                        continue;
                    }
                    $translationsArray[ $language ] = array(
                        'editedTranslation' => '',
                        'translated'        => '',
                        'status'            => $trp_query->get_constant_not_translated(),
                        'id'                => $slug,
                    );
                }

                $dictionary_by_original[ $slug ] = array(
                    'original'          => $slug,
                    'type'              => $type,
                    'translationsArray' => $translationsArray
                );
            }
        }


        $sanitized_args      = $helper->get_sanitized_query_args( $type );
        $returned_dictionary = array();

        // order and orderby
        if ( !empty( $sanitized_args['order'] ) && !empty( $sanitized_args['orderby'] ) ) {
            $orderby_array    = array_column( $dictionary_by_original, $sanitized_args['orderby'] );
            $sort_asc_or_desc = ( $sanitized_args['order'] === 'desc' ) ? SORT_DESC : SORT_ASC;
            array_multisort( $orderby_array, $sort_asc_or_desc, $dictionary_by_original );
        }

        $upper_limit = $sanitized_args['page'] * $config['items_per_page'];
        $lower_limit = ( $sanitized_args['page'] - 1 ) * $config['items_per_page'];

        // Parse search input once outside the loop for performance
        $search_data = ! empty( $sanitized_args['s'] ) ? $helper->parse_search_input( $sanitized_args['s'] ) : null;
        $is_exact_match = $search_data ? $search_data['is_exact_match'] : false;
        $search_term = $search_data ? $search_data['search_term'] : '';

        // Pre-compute search values for performance (done once, not per iteration)
        $search_term_encoded_lower = ! empty( $search_term ) ? mb_strtolower( urlencode( $search_term ), 'UTF-8' ) : '';
        $search_term_lower = ! empty( $search_term ) ? mb_strtolower( $search_term, 'UTF-8' ) : '';

        // filter the dictionary according to the requested params
        foreach ( $dictionary_by_original as $key => $entry ) {
            $found_in_translation = false;

            foreach ( $entry['translationsArray'] as $language => $translation ) {
                if ( ! empty( $sanitized_args['s'] ) ) {
                    if ( $is_exact_match ) {
                        // Compare with lowercase URL-encoded value since translations are stored that way
                        // Also check decoded version in case translation is stored differently
                        $translated_decoded = urldecode( $translation['translated'] );
                        if ( mb_strtolower( $translation['translated'], 'UTF-8' ) === $search_term_encoded_lower ||
                             mb_strtolower( $translated_decoded, 'UTF-8' ) === $search_term_lower ) {
                            $found_in_translation = true;
                        }
                    } else {
                        if ( strpos( $translation['translated'], $search_term_encoded_lower ) !== false ) {
                            $found_in_translation = true;
                        }
                    }
                }
            }

            // if the search key is not set, consider every item
            // Check both encoded and decoded versions of original for exact match
            if ( $is_exact_match ) {
                $original_decoded = urldecode( $entry['original'] );
                $original_matches = empty( $sanitized_args['s'] ) ||
                                    mb_strtolower( $entry['original'], 'UTF-8' ) === $search_term_lower ||
                                    mb_strtolower( $entry['original'], 'UTF-8' ) === $search_term_encoded_lower ||
                                    mb_strtolower( $original_decoded, 'UTF-8' ) === $search_term_lower;
            } else {
                $original_matches = empty( $sanitized_args['s'] ) || strpos( $entry['original'], $search_term ) !== false;
            }
            if ( $original_matches || $found_in_translation ) {
                if ( $found_in_translation )
                    $entry['foundInTranslation'] = true;

                foreach ( $entry['translationsArray'] as $language => $item ) {

                    // if the language is not selected, look for the status in all the languages
                    if ( empty( $sanitized_args['language'] ) || $language === $sanitized_args['language'] ) {
                        if ( empty( $sanitized_args['status'] ) || in_array( $item['status'], $sanitized_args['status'] ) ) {

                            // only keep the entries according to the page number requested
                            if ( $lower_limit <= $items_found && $items_found < $upper_limit ) {
                                $returned_dictionary[] = $entry;
                            }

                            // only add entry once
                            $items_found++;
                            break;
                        }
                    }
                }
            }
        }

        return array(
            'dictionary' => $returned_dictionary,
            'totalItems' => $items_found
        );
    }

    public function save_strings_for_option_based_slug( $type, $option_name, $strings = array() ) {
        $update_slugs = [];

        //initially made to work through ajax, added an optional parameter that enables the function to be used without
        if( empty( $strings ) )
            $all_strings  = json_decode( stripslashes( $_POST['strings'] ), true ); //phpcs:ignore
        else
            $all_strings = $strings;

        if ( !empty( $all_strings ) ){
            $update_slugs = $this->editor_actions->save_slugs( $all_strings, $type );
        }

        do_action( 'trp_before_based_slug_save', $type, $all_strings );

        echo trp_safe_json_encode( $update_slugs );//phpcs:ignore
        wp_die();
    }

}