/**
 * Social Share Functionality
 */
(function($) {
    'use strict';

    // Store popup instance
    let popup = null;

    /**
     * Show notification message
     * @param {string} message - Message to display
     * @param {string} type - Message type (success/error)
     */
    function showMessage(message, type = 'success') {
        const messageEl = $('<div>')
            .addClass(`wc-reward-points-message ${type}`)
            .text(message);

        $('body').append(messageEl);

        setTimeout(() => {
            messageEl.remove();
        }, 3000);
    }

    /**
     * Copy text to clipboard
     * @param {string} text - Text to copy
     * @returns {Promise} - Resolves when text is copied
     */
    async function copyToClipboard(text) {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
                return true;
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    document.execCommand('copy');
                    textArea.remove();
                    return true;
                } catch (err) {
                    textArea.remove();
                    return false;
                }
            }
        } catch (err) {
            return false;
        }
    }

    /**
     * Initialize share popup
     */
    function initSharePopup() {
        // Get share data from server
        $.ajax({
            url: wcRewardPointsShare.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_share_data',
                nonce: wcRewardPointsShare.nonce
            },
            success: function(response) {
                if (response.success) {
                    showPopup(response.data);
                } else {
                    showMessage(response.data, 'error');
                }
            },
            error: function() {
                showMessage(wcRewardPointsShare.i18n.shareError, 'error');
            }
        });
    }

    /**
     * Show share popup
     * @param {Object} shareData - Share data from server
     */
    function showPopup(shareData) {
        // Remove existing popup if any
        if (popup) {
            popup.remove();
        }

        // Create popup from template
        popup = $(shareData.popupHtml);
        $('body').append(popup);

        // Show popup with animation
        popup.fadeIn(300);

        // Handle close button
        popup.find('.wc-reward-points-share-popup-close').on('click', function() {
            closePopup();
        });

        // Handle click outside popup
        popup.on('click', function(e) {
            if ($(e.target).is('.wc-reward-points-share-popup')) {
                closePopup();
            }
        });

        // Handle copy buttons
        popup.find('.wc-reward-points-copy-code').on('click', async function() {
            const code = popup.find('.wc-reward-points-share-code input').val();
            if (await copyToClipboard(code)) {
                showMessage(wcRewardPointsShare.i18n.copySuccess);
            } else {
                showMessage(wcRewardPointsShare.i18n.copyError, 'error');
            }
        });

        popup.find('.wc-reward-points-copy-url').on('click', async function() {
            const url = popup.find('.wc-reward-points-share-url input').val();
            if (await copyToClipboard(url)) {
                showMessage(wcRewardPointsShare.i18n.copySuccess);
            } else {
                showMessage(wcRewardPointsShare.i18n.copyError, 'error');
            }
        });

        // Handle share buttons
        popup.find('.wc-reward-points-share-button').on('click', function(e) {
            const width = 600;
            const height = 400;
            const left = (screen.width / 2) - (width / 2);
            const top = (screen.height / 2) - (height / 2);

            // Don't open popup for email links
            if (!$(this).hasClass('email')) {
                e.preventDefault();
                
                window.open(
                    this.href,
                    'share',
                    `width=${width},height=${height},left=${left},top=${top}`
                );
            }
        });

        // Handle keyboard events
        $(document).on('keydown.sharePopup', function(e) {
            if (e.key === 'Escape') {
                closePopup();
            }
        });
    }

    /**
     * Close share popup
     */
    function closePopup() {
        if (popup) {
            popup.fadeOut(300, function() {
                popup.remove();
                popup = null;
            });
            $(document).off('keydown.sharePopup');
        }
    }

    /**
     * Initialize social share functionality
     */
    function init() {
        // Add click handler to share buttons
        $('.wc-reward-points-share-trigger').on('click', function(e) {
            e.preventDefault();
            initSharePopup();
        });
    }

    // Initialize when document is ready
    $(document).ready(init);

})(jQuery); 