<?php
/**
 * Toast Message Component
 *
 * Global toast notification that can be triggered from anywhere.
 * Usage: ippgiToast.show('Message text', 'success') or ippgiToast.show('Message text', 'error')
 *
 * @package IPPGI
 * @since 1.0.0
 */
?>

<!-- Toast Container -->
<div class="toast-message" id="ippgi-toast" style="display: none;">
    <span class="toast-message__text" id="ippgi-toast-text"></span>
    <span class="toast-message__icon" id="ippgi-toast-icon"></span>
</div>

<script>
window.ippgiToast = (function() {
    const toast = document.getElementById('ippgi-toast');
    const toastText = document.getElementById('ippgi-toast-text');
    const toastIcon = document.getElementById('ippgi-toast-icon');

    let hideTimeout = null;

    const successIcon = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"></polyline>
    </svg>`;

    const errorIcon = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
    </svg>`;

    function show(message, type = 'success', duration = 3000) {
        // Clear any existing timeout
        if (hideTimeout) {
            clearTimeout(hideTimeout);
        }

        // Set message
        toastText.textContent = message;

        // Set type class and icon
        toast.className = 'toast-message toast-message--' + type;
        toastIcon.className = 'toast-message__icon toast-message__icon--' + type;
        toastIcon.innerHTML = type === 'success' ? successIcon : errorIcon;

        // Show toast
        toast.style.display = 'flex';
        toast.style.opacity = '1';

        // Auto hide after duration
        hideTimeout = setTimeout(function() {
            hide();
        }, duration);
    }

    function hide() {
        toast.style.opacity = '0';
        setTimeout(function() {
            toast.style.display = 'none';
        }, 500);
    }

    return {
        show: show,
        hide: hide,
        success: function(message, duration) {
            show(message, 'success', duration);
        },
        error: function(message, duration) {
            show(message, 'error', duration);
        }
    };
})();
</script>
