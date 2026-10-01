/**
 * Portal Utilities for PT. Kualita Prima Prestasi
 * Handles global UI interactions like notification timeouts.
 */

document.addEventListener('DOMContentLoaded', function() {
    // 1. Auto-hide alerts after 3 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            // Add a fade-out effect
            alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            
            // Remove from DOM after fade animation
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 3000);
    });
});
