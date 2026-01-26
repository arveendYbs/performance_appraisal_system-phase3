
// assets/js/custom.js
/**
 * Custom JavaScript for Performance Appraisal System
 */

// Initialize application
document.addEventListener('DOMContentLoaded', function() {
    initializeComponents();
    setupEventListeners();
});

/**
 * Initialize UI components
 */
function initializeComponents() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Initialize rating displays
    document.querySelectorAll('input[type="range"]').forEach(function(slider) {
        const displayId = slider.getAttribute('oninput');
        if (displayId) {
            const match = displayId.match(/'([^']+)'/);
            if (match) {
                updateRatingValue(slider, match[1]);
            }
        }
    });
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Form validation
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm(form)) {
                e.preventDefault();
            }
        });
    });

    // Auto-hide alerts
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            alert.classList.add('fade');
            setTimeout(function() {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 500);
        });
    }, 5000);

    // Confirm delete actions
    document.querySelectorAll('[onclick*="confirmDelete"]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            const message = element.getAttribute('data-confirm-message') || 
                           'Are you sure you want to delete this item? This action cannot be undone.';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
}

/**
 * Form validation
 */
function validateForm(form) {
    let isValid = true;
    
    // Check required fields
    form.querySelectorAll('input[required], select[required], textarea[required]').forEach(function(field) {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    // Email validation
    form.querySelectorAll('input[type="email"]').forEach(function(field) {
        if (field.value && !isValidEmail(field.value)) {
            field.classList.add('is-invalid');
            isValid = false;
        }
    });
    
    // Password confirmation
    const password = form.querySelector('input[name="password"], input[name="new_password"]');
    const confirmPassword = form.querySelector('input[name="confirm_password"]');
    
    if (password && confirmPassword && password.value !== confirmPassword.value) {
        confirmPassword.classList.add('is-invalid');
        confirmPassword.setCustomValidity('Passwords do not match');
        isValid = false;
    } else if (confirmPassword) {
        confirmPassword.setCustomValidity('');
    }
    
    if (!isValid) {
        showAlert('Please correct the errors and try again.', 'danger');
    }
    
    return isValid;
}

/**
 * Email validation
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Update rating display
 */
function updateRatingValue(slider, displayId) {
    const display = document.getElementById(displayId);
    if (display) {
        display.textContent = slider.value;
    }
}

/**
 * Show alert message
 */
function showAlert(message, type = 'info') {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="bi bi-${getAlertIcon(type)} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const container = document.querySelector('.main-content');
    if (container) {
        container.insertAdjacentHTML('afterbegin', alertHtml);
    }
}

/**
 * Get alert icon based on type
 */
function getAlertIcon(type) {
    const icons = {
        success: 'check-circle',
        danger: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    return icons[type] || 'info-circle';
}

/**
 * Auto-save functionality
 */
class AutoSave {
    constructor(formId, saveUrl, interval = 30000) {
        this.form = document.getElementById(formId);
        this.saveUrl = saveUrl;
        this.interval = interval;
        this.timeoutId = null;
        this.init();
    }
    
    init() {
        if (!this.form) return;
        
        this.form.addEventListener('input', () => {
            this.scheduleAutoSave();
        });
    }
    
    scheduleAutoSave() {
        clearTimeout(this.timeoutId);
        this.timeoutId = setTimeout(() => {
            this.performAutoSave();
        }, this.interval);
    }
    
    async performAutoSave() {
        try {
            const formData = new FormData(this.form);
            formData.append('action', 'autosave');
            
            const response = await fetch(this.saveUrl, {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                this.showAutoSaveIndicator();
                console.log('Auto-saved successfully');
            }
        } catch (error) {
            console.error('Auto-save failed:', error);
        }
    }
    
    showAutoSaveIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'alert alert-success position-fixed';
        indicator.style.cssText = 'top: 80px; right: 20px; z-index: 9999; opacity: 0.9; font-size: 0.875rem;';
        indicator.innerHTML = '<i class="bi bi-check-circle me-2"></i>Auto-saved';
        document.body.appendChild(indicator);
        
        setTimeout(() => {
            if (indicator.parentNode) {
                indicator.parentNode.removeChild(indicator);
            }
        }, 2000);
    }
}

/**
 * Progress tracking
 */
function updateProgress(current, total) {
    const percentage = Math.round((current / total) * 100);
    const progressBar = document.querySelector('.progress-step');
    
    if (progressBar) {
        progressBar.setAttribute('data-progress', percentage);
    }
    
    return percentage;
}

/**
 * Export functionality
 */
async function exportData(endpoint, filename = 'export.csv') {
    try {
        const response = await fetch(endpoint);
        const blob = await response.blob();
        
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    } catch (error) {
        console.error('Export failed:', error);
        showAlert('Export failed. Please try again.', 'danger');
    }
}

/**
 * Confirm delete with custom message
 */
function confirmDelete(message = 'Are you sure you want to delete this item? This action cannot be undone.') {
    return confirm(message);
}

/**
 * Load content dynamically
 */
async function loadContent(url, targetId) {
    const target = document.getElementById(targetId);
    if (!target) return;
    
    target.innerHTML = '<div class="text-center"><div class="loading"></div></div>';
    
    try {
        const response = await fetch(url);
        const html = await response.text();
        target.innerHTML = html;
    } catch (error) {
        console.error('Failed to load content:', error);
        target.innerHTML = '<div class="alert alert-danger">Failed to load content.</div>';
    }
}

// Global functions for backward compatibility
window.updateRatingValue = updateRatingValue;
window.confirmDelete = confirmDelete;
window.showAlert = showAlert;
window.AutoSave = AutoSave;
window.exportData = exportData;
window.loadContent = loadContent;
