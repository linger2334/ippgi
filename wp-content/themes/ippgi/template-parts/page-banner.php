<?php
/**
 * Page Header Banner
 *
 * @package IPPGI
 * @since 1.0.0
 *
 * Usage:
 * get_template_part('template-parts/page-banner', null, [
 *     'title'    => 'Page Title',
 *     'subtitle' => 'Page description text',
 * ]);
 */

$title    = isset($args['title']) ? $args['title'] : get_the_title();
$subtitle = isset($args['subtitle']) ? $args['subtitle'] : '';
?>

<div class="page-banner">
    <h1 class="page-banner__title"><?php echo esc_html($title); ?></h1>
    <?php if ($subtitle) : ?>
        <p class="page-banner__subtitle"><?php echo esc_html($subtitle); ?></p>
    <?php endif; ?>
</div>
