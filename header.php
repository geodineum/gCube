<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php bloginfo('description'); ?>">

    <?php
    // PWA: manifest.json, theme-color, Apple meta tags, and service worker registration
    // are handled by inc/assets/pwa-rewrite.php and inc/integrations/managers/manifest.php via wp_head/wp_footer
    ?>

    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page" class="site">
    <header>
        <?php
        // Check for logo: WordPress custom_logo first, then fallback to logo_source theme mod
        $logo_url = null;
        $logo_alt = get_theme_mod('logo_alt_text', get_bloginfo('name') . ' logo');

        if (has_custom_logo()) {
            // WordPress standard custom_logo (Site Identity)
            $custom_logo_id = get_theme_mod('custom_logo');
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            // Use attachment alt if set, otherwise use theme mod alt
            $attachment_alt = get_post_meta($custom_logo_id, '_wp_attachment_image_alt', true);
            if ($attachment_alt) {
                $logo_alt = $attachment_alt;
            }
        } elseif ($logo_source = get_theme_mod('logo_source')) {
            // Fallback to logo_source (Logo Settings section)
            $logo_url = $logo_source;
        }

        // Get logo dimensions from customizer (Logo Settings section)
        $logo_width = get_theme_mod('logo_width', '10vmin');
        $logo_height = get_theme_mod('logo_height', 'auto');

        if ($logo_url):
        ?>
            <div id="logoWrapper" class="leftcorner">
                <a href="<?php echo esc_url(home_url('/')); ?>" rel="home"
                   aria-label="<?php echo esc_attr($logo_alt); ?> — go to home"
                   onclick="if(window.gCube){window.gCube.goHome();return false;}">
                    <img src="<?php echo esc_url($logo_url); ?>"
                         alt="<?php echo esc_attr($logo_alt); ?>"
                         id="logo_goHome"
                         style="max-width: <?php echo esc_attr($logo_width); ?>; max-height: <?php echo esc_attr($logo_height); ?>; cursor: pointer;">
                </a>
            </div>
        <?php endif; ?>
    </header>
