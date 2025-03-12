<?php
/**
 * Referral Widget Template
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/referral-widget.php.
 *
 * @package    WC_Reward_Points
 * @version    1.0.0
 */

defined('ABSPATH') || exit;
?>

<div class="wc-reward-points-referral-widget">
    <div class="referral-header">
        <h3><?php esc_html_e('Share & Earn Rewards', 'wc-reward-points'); ?></h3>
        <p><?php esc_html_e('Share your referral link with friends and earn points when they shop!', 'wc-reward-points'); ?></p>
    </div>

    <div class="referral-url-container">
        <label for="referral-url"><?php esc_html_e('Your Referral Link:', 'wc-reward-points'); ?></label>
        <div class="referral-url-copy">
            <input type="text" id="referral-url" value="<?php echo esc_url($referral_url); ?>" readonly />
            <button class="copy-url" data-clipboard-target="#referral-url">
                <span class="dashicons dashicons-clipboard"></span>
                <?php esc_html_e('Copy', 'wc-reward-points'); ?>
            </button>
        </div>
    </div>

    <?php if (!empty($networks)) : ?>
        <div class="social-share-container">
            <p><?php esc_html_e('Share via:', 'wc-reward-points'); ?></p>
            <div class="social-buttons">
                <?php foreach ($networks as $network => $label) : ?>
                    <?php
                    switch ($network) {
                        case 'facebook':
                            $share_url = 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($referral_url) . '&quote=' . urlencode($message);
                            $icon = 'dashicons-facebook';
                            break;
                        case 'twitter':
                            $share_url = 'https://twitter.com/intent/tweet?url=' . urlencode($referral_url) . '&text=' . urlencode($message);
                            $icon = 'dashicons-twitter';
                            break;
                        case 'whatsapp':
                            $share_url = 'https://api.whatsapp.com/send?text=' . urlencode($message . ' ' . $referral_url);
                            $icon = 'dashicons-whatsapp';
                            break;
                    }
                    ?>
                    <a href="<?php echo esc_url($share_url); ?>" 
                       class="share-button share-<?php echo esc_attr($network); ?>"
                       target="_blank"
                       rel="noopener noreferrer">
                        <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                        <span class="screen-reader-text"><?php echo esc_html($label); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="referral-info">
        <p class="message"><?php echo esc_html($message); ?></p>
    </div>
</div>

<style>
.wc-reward-points-referral-widget {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 24px;
    margin: 20px 0;
}

.referral-header {
    text-align: center;
    margin-bottom: 24px;
}

.referral-header h3 {
    margin: 0 0 12px;
    color: #2c3338;
    font-size: 1.5em;
}

.referral-header p {
    color: #646970;
    margin: 0;
}

.referral-url-container {
    margin-bottom: 24px;
}

.referral-url-container label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
}

.referral-url-copy {
    display: flex;
    gap: 8px;
}

.referral-url-copy input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    background: #f6f7f7;
}

.copy-url {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 8px 16px;
    background: #2271b1;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.2s ease;
}

.copy-url:hover {
    background: #135e96;
}

.copy-url .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.social-share-container {
    margin-bottom: 24px;
    text-align: center;
}

.social-buttons {
    display: flex;
    justify-content: center;
    gap: 16px;
    margin-top: 12px;
}

.share-button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    color: #fff;
    text-decoration: none;
    transition: transform 0.2s ease;
}

.share-button:hover {
    transform: scale(1.1);
}

.share-facebook {
    background: #1877f2;
}

.share-twitter {
    background: #1da1f2;
}

.share-whatsapp {
    background: #25d366;
}

.share-button .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.referral-info {
    text-align: center;
    padding-top: 16px;
    border-top: 1px solid #f0f0f1;
}

.referral-info .message {
    color: #646970;
    margin: 0;
    font-style: italic;
}

@media (max-width: 480px) {
    .referral-url-copy {
        flex-direction: column;
    }
    
    .copy-url {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize clipboard.js
    new ClipboardJS('.copy-url');

    // Add copy feedback
    $('.copy-url').click(function(e) {
        e.preventDefault();
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text('<?php esc_html_e('Copied!', 'wc-reward-points'); ?>');
        setTimeout(function() {
            $button.html('<span class="dashicons dashicons-clipboard"></span> ' + originalText);
        }, 2000);
    });

    // Open share links in popup
    $('.share-button').click(function(e) {
        e.preventDefault();
        var url = $(this).attr('href');
        var width = 600;
        var height = 400;
        var left = (screen.width/2)-(width/2);
        var top = (screen.height/2)-(height/2);
        
        window.open(url, 'share', 
            'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=no, resizable=no, copyhistory=no, width='+width+', height='+height+', top='+top+', left='+left
        );
    });
});
</script> 