<?php
/**
 * Quote request handling and admin management.
 *
 * @package IPPGI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get quote request notification recipient emails.
 *
 * @return array<int, string> Email addresses.
 */
function ippgi_get_quote_request_recipient_emails() {
    $configured_value = trim((string) get_theme_mod('ippgi_quote_request_recipient_email', ''));
    $emails = [];

    if ($configured_value !== '') {
        $parts = preg_split('/[\s,;]+/', $configured_value);

        foreach ($parts as $part) {
            $email = sanitize_email($part);
            if ($email !== '' && is_email($email)) {
                $emails[] = $email;
            }
        }
    }

    $emails = array_values(array_unique($emails));

    if (!empty($emails)) {
        return $emails;
    }

    $admin_email = (string) get_option('admin_email');

    return is_email($admin_email) ? [$admin_email] : [];
}

/**
 * Register quote request custom post type for backend review.
 */
function ippgi_register_quote_request_post_type() {
    $labels = [
        'name'               => __('Quote Requests', 'ippgi'),
        'singular_name'      => __('Quote Request', 'ippgi'),
        'menu_name'          => __('Quote Requests', 'ippgi'),
        'name_admin_bar'     => __('Quote Request', 'ippgi'),
        'add_new'            => __('Add New', 'ippgi'),
        'add_new_item'       => __('Add New Quote Request', 'ippgi'),
        'edit_item'          => __('Edit Quote Request', 'ippgi'),
        'new_item'           => __('New Quote Request', 'ippgi'),
        'view_item'          => __('View Quote Request', 'ippgi'),
        'search_items'       => __('Search Quote Requests', 'ippgi'),
        'not_found'          => __('No quote requests found', 'ippgi'),
        'not_found_in_trash' => __('No quote requests found in Trash', 'ippgi'),
        'all_items'          => __('All Quote Requests', 'ippgi'),
    ];

    $args = [
        'labels'             => $labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => false,
        'rewrite'            => false,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 26,
        'menu_icon'          => 'dashicons-email-alt',
        'supports'           => ['title'],
        'show_in_rest'       => false,
    ];

    register_post_type('ippgi_quote_request', $args);
}
add_action('init', 'ippgi_register_quote_request_post_type');

/**
 * Add quote request details meta box.
 */
function ippgi_quote_request_meta_boxes() {
    add_meta_box(
        'ippgi_quote_request_details',
        __('Quote Request Details', 'ippgi'),
        'ippgi_quote_request_details_callback',
        'ippgi_quote_request',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'ippgi_quote_request_meta_boxes');

/**
 * Render quote request details meta box.
 *
 * @param WP_Post $post Current post object.
 */
function ippgi_quote_request_details_callback($post) {
    $name = get_post_meta($post->ID, '_ippgi_quote_name', true);
    $contact = get_post_meta($post->ID, '_ippgi_quote_contact', true);
    $company = get_post_meta($post->ID, '_ippgi_quote_company', true);
    $product_interest = get_post_meta($post->ID, '_ippgi_quote_product_interest', true);
    $details = get_post_meta($post->ID, '_ippgi_quote_details', true);
    $source = get_post_meta($post->ID, '_ippgi_quote_source', true) ?: 'homepage';
    $mail_status = get_post_meta($post->ID, '_ippgi_quote_mail_status', true) ?: 'pending';
    $user_id = absint(get_post_meta($post->ID, '_ippgi_quote_user_id', true));
    $user_email = get_post_meta($post->ID, '_ippgi_quote_user_email', true);
    $submitted_at = get_post_meta($post->ID, '_ippgi_quote_submitted_at', true);
    ?>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><?php esc_html_e('Name', 'ippgi'); ?></th>
                <td><?php echo esc_html($name ?: '—'); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Email / WhatsApp', 'ippgi'); ?></th>
                <td><?php echo esc_html($contact ?: '—'); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Company', 'ippgi'); ?></th>
                <td><?php echo esc_html($company ?: '—'); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Steel Product of Interest', 'ippgi'); ?></th>
                <td><?php echo esc_html($product_interest ?: '—'); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Additional Details', 'ippgi'); ?></th>
                <td style="white-space: pre-wrap;"><?php echo esc_html($details ?: '—'); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Source', 'ippgi'); ?></th>
                <td><?php echo esc_html(ucfirst($source)); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Submitted At', 'ippgi'); ?></th>
                <td><?php echo esc_html($submitted_at ?: '—'); ?> <?php echo $submitted_at ? esc_html__('(UTC+8)', 'ippgi') : ''; ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Mail Status', 'ippgi'); ?></th>
                <td><?php echo esc_html(ucfirst($mail_status)); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Logged-in User', 'ippgi'); ?></th>
                <td>
                    <?php
                    if ($user_id > 0) {
                        echo esc_html(sprintf(__('User ID %1$d (%2$s)', 'ippgi'), $user_id, $user_email ?: '—'));
                    } else {
                        echo esc_html__('Guest submission', 'ippgi');
                    }
                    ?>
                </td>
            </tr>
        </tbody>
    </table>
    <?php
}

/**
 * Customize admin columns for quote requests.
 *
 * @param array $columns Default columns.
 * @return array
 */
function ippgi_quote_request_columns($columns) {
    $new_columns = [];

    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;

        if ($key === 'title') {
            $new_columns['contact'] = __('Email / WhatsApp', 'ippgi');
            $new_columns['company'] = __('Company', 'ippgi');
            $new_columns['product'] = __('Product', 'ippgi');
            $new_columns['source'] = __('Source', 'ippgi');
            $new_columns['mail_status'] = __('Mail', 'ippgi');
        }
    }

    return $new_columns;
}
add_filter('manage_ippgi_quote_request_posts_columns', 'ippgi_quote_request_columns');

/**
 * Populate custom columns for quote requests.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function ippgi_quote_request_column_content($column, $post_id) {
    switch ($column) {
        case 'contact':
            echo esc_html(get_post_meta($post_id, '_ippgi_quote_contact', true) ?: '—');
            break;

        case 'company':
            echo esc_html(get_post_meta($post_id, '_ippgi_quote_company', true) ?: '—');
            break;

        case 'product':
            echo esc_html(get_post_meta($post_id, '_ippgi_quote_product_interest', true) ?: '—');
            break;

        case 'source':
            $source = get_post_meta($post_id, '_ippgi_quote_source', true) ?: 'homepage';
            echo esc_html(ucfirst($source));
            break;

        case 'mail_status':
            $mail_status = get_post_meta($post_id, '_ippgi_quote_mail_status', true) ?: 'pending';
            $colors = [
                'sent' => '#15803d',
                'failed' => '#dc2626',
                'pending' => '#6b7280',
            ];
            $color = isset($colors[$mail_status]) ? $colors[$mail_status] : '#6b7280';
            echo '<span style="color:' . esc_attr($color) . ';font-weight:600;">' . esc_html(ucfirst($mail_status)) . '</span>';
            break;
    }
}
add_action('manage_ippgi_quote_request_posts_custom_column', 'ippgi_quote_request_column_content', 10, 2);

/**
 * Add CSV export button to the quote request list screen.
 *
 * @param string $which Tablenav location.
 */
function ippgi_quote_request_export_button($which) {
    if ($which !== 'top') {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $post_type = $screen && !empty($screen->post_type) ? $screen->post_type : '';

    if ($post_type !== 'ippgi_quote_request') {
        return;
    }

    if (!current_user_can('edit_posts')) {
        return;
    }

    $export_url = wp_nonce_url(
        admin_url('admin-post.php?action=ippgi_export_quote_requests_csv'),
        'ippgi_export_quote_requests_csv'
    );

    echo '<a href="' . esc_url($export_url) . '" class="button" style="margin-left:8px;">' . esc_html__('Export CSV', 'ippgi') . '</a>';
}
add_action('manage_posts_extra_tablenav', 'ippgi_quote_request_export_button');

/**
 * Make quote request list default to newest first.
 *
 * @param WP_Query $query Main admin query.
 */
function ippgi_quote_request_admin_order($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    if ($query->get('post_type') !== 'ippgi_quote_request') {
        return;
    }

    if (!$query->get('orderby')) {
        $query->set('orderby', 'date');
    }

    if (!$query->get('order')) {
        $query->set('order', 'DESC');
    }
}
add_action('pre_get_posts', 'ippgi_quote_request_admin_order');

/**
 * Export quote requests as CSV.
 */
function ippgi_export_quote_requests_csv() {
    if (!current_user_can('edit_posts')) {
        wp_die(esc_html__('You do not have permission to export quote requests.', 'ippgi'));
    }

    check_admin_referer('ippgi_export_quote_requests_csv');

    $posts = get_posts([
        'post_type'      => 'ippgi_quote_request',
        'post_status'    => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
    ]);

    $filename = 'quote-requests-' . wp_date('Y-m-d-H-i-s', null, wp_timezone()) . '.csv';

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');

    if ($output === false) {
        wp_die(esc_html__('Unable to generate CSV export.', 'ippgi'));
    }

    // UTF-8 BOM for Excel compatibility.
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        'ID',
        'Title',
        'Name',
        'Email / WhatsApp',
        'Company',
        'Steel Product of Interest',
        'Additional Details',
        'Source',
        'Submitted At (UTC+8)',
        'Mail Status',
        'Logged-in User ID',
        'Logged-in User Email',
        'Created At',
    ]);

    foreach ($posts as $post) {
        fputcsv($output, [
            $post->ID,
            $post->post_title,
            get_post_meta($post->ID, '_ippgi_quote_name', true),
            get_post_meta($post->ID, '_ippgi_quote_contact', true),
            get_post_meta($post->ID, '_ippgi_quote_company', true),
            get_post_meta($post->ID, '_ippgi_quote_product_interest', true),
            get_post_meta($post->ID, '_ippgi_quote_details', true),
            get_post_meta($post->ID, '_ippgi_quote_source', true) ?: 'homepage',
            get_post_meta($post->ID, '_ippgi_quote_submitted_at', true),
            get_post_meta($post->ID, '_ippgi_quote_mail_status', true) ?: 'pending',
            get_post_meta($post->ID, '_ippgi_quote_user_id', true),
            get_post_meta($post->ID, '_ippgi_quote_user_email', true),
            get_date_from_gmt($post->post_date_gmt, 'Y-m-d H:i:s'),
        ]);
    }

    fclose($output);
    exit;
}
add_action('admin_post_ippgi_export_quote_requests_csv', 'ippgi_export_quote_requests_csv');

/**
 * Build a compact admin title for each quote request.
 *
 * @param string $name            Requester name.
 * @param string $company         Company name.
 * @param string $product_interest Product interest.
 * @return string
 */
function ippgi_build_quote_request_title($name, $company, $product_interest) {
    $title_parts = array_filter([$name, $company]);
    $title = implode(' - ', $title_parts);

    if ($title === '') {
        $title = $product_interest;
    }

    if ($title === '') {
        $title = __('Quote Request', 'ippgi');
    }

    return wp_html_excerpt($title, 120, '...');
}

/**
 * Save quote request submission into admin-manageable storage.
 *
 * @param array $data Sanitized quote request data.
 * @return int|WP_Error
 */
function ippgi_store_quote_request($data) {
    $user_id = 0;
    $user_email = '';

    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        if ($current_user instanceof WP_User && $current_user->exists()) {
            $user_id = (int) $current_user->ID;
            $user_email = $current_user->user_email;
        }
    }

    $post_id = wp_insert_post([
        'post_type'   => 'ippgi_quote_request',
        'post_status' => 'publish',
        'post_title'  => ippgi_build_quote_request_title($data['name'], $data['company'], $data['product_interest']),
        'post_author' => $user_id > 0 ? $user_id : 0,
    ], true);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    update_post_meta($post_id, '_ippgi_quote_name', $data['name']);
    update_post_meta($post_id, '_ippgi_quote_contact', $data['contact']);
    update_post_meta($post_id, '_ippgi_quote_company', $data['company']);
    update_post_meta($post_id, '_ippgi_quote_product_interest', $data['product_interest']);
    update_post_meta($post_id, '_ippgi_quote_details', $data['details']);
    update_post_meta($post_id, '_ippgi_quote_source', $data['source']);
    update_post_meta($post_id, '_ippgi_quote_mail_status', 'pending');
    update_post_meta($post_id, '_ippgi_quote_submitted_at', wp_date('Y-m-d H:i:s', null, wp_timezone()));

    if ($user_id > 0) {
        update_post_meta($post_id, '_ippgi_quote_user_id', $user_id);
        update_post_meta($post_id, '_ippgi_quote_user_email', $user_email);
    }

    return $post_id;
}

/**
 * Handle quote request submissions from the homepage modal.
 */
function ippgi_ajax_submit_quote_request() {
    check_ajax_referer('ippgi_quote_request', 'nonce');

    $website = isset($_POST['website']) ? trim((string) wp_unslash($_POST['website'])) : '';
    if ($website !== '') {
        wp_send_json_success([
            'message' => __('Thanks, your quote request has been received. Our team will contact you soon.', 'ippgi'),
        ]);
    }

    $source = isset($_POST['source']) ? sanitize_key(wp_unslash($_POST['source'])) : 'homepage';
    $allowed_sources = array('homepage', 'price_detail');
    if (!in_array($source, $allowed_sources, true)) {
        $source = 'homepage';
    }

    $quote_data = [
        'name' => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
        'contact' => isset($_POST['contact']) ? sanitize_text_field(wp_unslash($_POST['contact'])) : '',
        'company' => isset($_POST['company']) ? sanitize_text_field(wp_unslash($_POST['company'])) : '',
        'product_interest' => isset($_POST['product_interest']) ? sanitize_text_field(wp_unslash($_POST['product_interest'])) : '',
        'details' => isset($_POST['details']) ? sanitize_textarea_field(wp_unslash($_POST['details'])) : '',
        'source' => $source,
    ];

    if ($quote_data['name'] === '' || $quote_data['contact'] === '' || $quote_data['company'] === '' || $quote_data['product_interest'] === '') {
        wp_send_json_error([
            'message' => __('Please complete all required fields.', 'ippgi'),
        ], 400);
    }

    $post_id = ippgi_store_quote_request($quote_data);
    if (is_wp_error($post_id)) {
        wp_send_json_error([
            'message' => __('Unable to save your request right now. Please try again in a moment.', 'ippgi'),
        ], 500);
    }

    $source_label_map = array(
        'homepage' => 'homepage',
        'price_detail' => 'price detail page',
    );
    $source_label = isset($source_label_map[$quote_data['source']]) ? $source_label_map[$quote_data['source']] : $quote_data['source'];

    $recipients = ippgi_get_quote_request_recipient_emails();
    if (!empty($recipients)) {
        $subject = sprintf(
            /* translators: %s: requester name */
            __('New quote request from %s', 'ippgi'),
            $quote_data['name']
        );

        $message_lines = [
            'Quote request submitted from ' . $source_label . '.',
            '',
            'Name: ' . $quote_data['name'],
            'Email / WhatsApp: ' . $quote_data['contact'],
            'Company: ' . $quote_data['company'],
            'Steel Product of Interest: ' . $quote_data['product_interest'],
            'Additional Details: ' . ($quote_data['details'] !== '' ? $quote_data['details'] : '-'),
            'Stored Record ID: ' . $post_id,
        ];

        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            if ($current_user instanceof WP_User && $current_user->exists()) {
                $message_lines[] = 'Logged-in User ID: ' . $current_user->ID;
                $message_lines[] = 'Logged-in User Email: ' . $current_user->user_email;
            }
        }

        $message_lines[] = 'Submitted At (UTC+8): ' . wp_date('Y-m-d H:i:s', null, wp_timezone());
        $message_lines[] = 'Site: ' . home_url('/');

        $headers = [];
        if (is_email($quote_data['contact'])) {
            $headers[] = 'Reply-To: ' . $quote_data['name'] . ' <' . $quote_data['contact'] . '>';
        }

        $sent = wp_mail(
            $recipients,
            $subject,
            implode("\n", $message_lines),
            $headers
        );

        update_post_meta($post_id, '_ippgi_quote_mail_status', $sent ? 'sent' : 'failed');
    } else {
        update_post_meta($post_id, '_ippgi_quote_mail_status', 'failed');
    }

    wp_send_json_success([
        'message' => __('Thanks, your quote request has been received. Our team will contact you soon.', 'ippgi'),
        'requestId' => $post_id,
    ]);
}
add_action('wp_ajax_ippgi_submit_quote_request', 'ippgi_ajax_submit_quote_request');
add_action('wp_ajax_nopriv_ippgi_submit_quote_request', 'ippgi_ajax_submit_quote_request');
