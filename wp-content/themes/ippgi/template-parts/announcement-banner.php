<?php
/**
 * Announcement Banner
 *
 * Displays active announcements below the header (homepage only).
 *
 * @package IPPGI
 * @since 1.0.0
 */

// Only show on homepage
if (!is_front_page()) {
    return;
}

// Get active announcements for current user
$announcements = ippgi_get_active_announcements(1); // Get only the latest one

if (empty($announcements)) {
    return;
}

$announcement = $announcements[0];
$data = ippgi_get_announcement_data($announcement->ID);

if (!$data) {
    return;
}
?>

<div class="announcement-banner"
     id="announcement-banner-<?php echo esc_attr($data['id']); ?>"
     data-announcement-id="<?php echo esc_attr($data['id']); ?>"
     data-announcement-hash="<?php echo esc_attr($data['hash']); ?>">
    <div class="container">
        <div class="announcement-banner__content">
            <div class="announcement-banner__text">
                <?php if (!empty($data['link'])) : ?>
                    <a href="<?php echo esc_url($data['link']); ?>">
                        <?php echo wp_strip_all_tags($data['content']); ?>
                    </a>
                <?php else : ?>
                    <?php echo wp_strip_all_tags($data['content']); ?>
                <?php endif; ?>
            </div>
            <?php if ($data['dismissible']) : ?>
                <button type="button"
                        class="announcement-banner__close"
                        aria-label="<?php esc_attr_e('Dismiss announcement', 'ippgi'); ?>"
                        data-dismiss-id="<?php echo esc_attr($data['id']); ?>"
                        data-dismiss-hash="<?php echo esc_attr($data['hash']); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>
