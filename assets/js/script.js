/**
 * Event Management System 007 - Custom JavaScript
 */

/**
 * Initialize all JavaScript functionality when DOM is loaded
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initializeTooltips();
    initializeDropdowns();
    initializeAlerts();
    initializeForms();
    initializeCountdowns();
});

/**
 * Initialize Bootstrap tooltips
 */
function initializeTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialize Bootstrap dropdowns
 */
function initializeDropdowns() {
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    dropdownElementList.map(function(dropdown) {
        return new bootstrap.Dropdown(dropdown);
    });
}

/**
 * Initialize auto-dismissing alerts
 */
function initializeAlerts() {
    var alertList = document.querySelectorAll('.alert-dismissible');
    alertList.forEach(function(alert) {
        new bootstrap.Alert(alert);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            var closeButton = alert.querySelector('.btn-close');
            if (closeButton) {
                closeButton.click();
            }
        }, 5000);
    });
}

/**
 * Initialize form enhancements
 */
function initializeForms() {
    // Confirm password validation
    var confirmPasswordInputs = document.querySelectorAll('[data-confirm-password]');
    confirmPasswordInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            var passwordInput = document.querySelector('[data-password="' + this.dataset.confirmPassword + '"]');
            if (passwordInput && this.value !== passwordInput.value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    });
    
    // Character counter for textareas
    var textareas = document.querySelectorAll('[data-maxlength]');
    textareas.forEach(function(textarea) {
        var maxLength = parseInt(textarea.dataset.maxlength);
        var counter = document.createElement('small');
        counter.className = 'text-muted float-end';
        textarea.parentNode.appendChild(counter);
        
        function updateCounter() {
            counter.textContent = textarea.value.length + '/' + maxLength;
        }
        
        textarea.addEventListener('input', updateCounter);
        updateCounter();
    });
}

/**
 * Initialize countdown timers
 */
function initializeCountdowns() {
    var countdowns = document.querySelectorAll('[data-countdown]');
    countdowns.forEach(function(element) {
        var endDate = new Date(element.dataset.countdown).getTime();
        
        function updateCountdown() {
            var now = new Date().getTime();
            var distance = endDate - now;
            
            if (distance < 0) {
                element.textContent = 'Event has started!';
                return;
            }
            
            var days = Math.floor(distance / (1000 * 60 * 60 * 24));
            var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            
            element.textContent = days + 'd ' + hours + 'h ' + minutes + 'm';
        }
        
        updateCountdown();
        setInterval(updateCountdown, 60000);
    });
}

/**
 * Show loading indicator
 */
function showLoading(element) {
    element = element || document.body;
    var loader = document.createElement('div');
    loader.className = 'loader text-center';
    loader.style.cssText = 'margin: 20px auto;';
    element.appendChild(loader);
    return loader;
}

/**
 * Hide loading indicator
 */
function hideLoading(loader) {
    if (loader && loader.parentNode) {
        loader.parentNode.removeChild(loader);
    }
}

/**
 * Show toast notification
 */
function showToast(message, type) {
    type = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'].indexOf(type) >= 0 ? type : 'info';
    
    var toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    var toast = document.createElement('div');
    toast.className = 'toast';
    toast.setAttribute('role', 'alert');
    var header = document.createElement('div');
    header.className = 'toast-header bg-' + type + ' text-white';
    var title = document.createElement('strong');
    title.className = 'me-auto';
    title.textContent = 'Notification';
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close btn-close-white';
    close.setAttribute('data-bs-dismiss', 'toast');
    close.setAttribute('aria-label', 'Close');
    header.append(title, close);
    var body = document.createElement('div');
    body.className = 'toast-body';
    body.textContent = String(message);
    toast.append(header, body);
    
    toastContainer.appendChild(toast);
    
    var bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

/**
 * Confirm action with modal
 */
function confirmAction(message, callback) {
    message = message || 'Are you sure?';
    
    var modalContainer = document.createElement('div');
    modalContainer.innerHTML = '<div class="modal fade" id="confirmModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirm Action</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="confirm-message"></p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="confirmBtn">Confirm</button></div></div></div></div>';
    modalContainer.querySelector('.confirm-message').textContent = String(message);
    document.body.appendChild(modalContainer);
    
    var modalElement = document.getElementById('confirmModal');
    var modal = new bootstrap.Modal(modalElement);
    
    document.getElementById('confirmBtn').addEventListener('click', function() {
        modal.hide();
        callback(true);
    });
    
    modalElement.addEventListener('hidden.bs.modal', function() {
        modalContainer.remove();
    });
    
    modal.show();
}

/**
 * Make AJAX request
 */
function ajaxRequest(url, options) {
    options = options || {};
    
    var xhr = new XMLHttpRequest();
    xhr.open(options.method || 'GET', url, true);
    
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    if (options.headers) {
        for (var key in options.headers) {
            xhr.setRequestHeader(key, options.headers[key]);
        }
    }
    
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 400) {
            if (options.success) {
                options.success(JSON.parse(xhr.responseText));
            }
        } else {
            if (options.error) {
                options.error(xhr);
            }
        }
    };
    
    xhr.onerror = function() {
        if (options.error) {
            options.error(xhr);
        }
    };
    
    var data = options.data || null;
    if (data) {
        var params = new URLSearchParams();
        for (var key in data) {
            params.append(key, data[key]);
        }
        xhr.send(params.toString());
    } else {
        xhr.send();
    }
}

/**
 * Debounce function for search inputs
 */
function debounce(func, wait) {
    var timeout;
    return function() {
        var context = this;
        var args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(function() {
            func.apply(context, args);
        }, wait);
    };
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Copy text to clipboard
 */
function copyToClipboard(text, callback) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            if (callback) callback(true);
        }).catch(function() {
            if (callback) callback(false);
        });
    } else {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            if (callback) callback(true);
        } catch (err) {
            if (callback) callback(false);
        }
        document.body.removeChild(textarea);
    }
}

/**
 * Print current page
 */
function printPage() {
    window.print();
}

/**
 * Export data to CSV
 */
function exportToCSV(data, filename) {
    var csv = [];
    var headers = Object.keys(data[0]);
    csv.push(headers.join(','));
    
    data.forEach(function(row) {
        var values = headers.map(function(header) {
            var value = row[header];
            return '"' + (value || '').toString().replace(/"/g, '""') + '"';
        });
        csv.push(values.join(','));
    });
    
    var csvContent = csv.join('\n');
    var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    
    if (link.download !== undefined) {
        var url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

/**
 * Smooth scroll to element
 */
function scrollToElement(elementId) {
    var element = document.getElementById(elementId);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
    }
}

/**
 * Toggle password visibility
 */
function togglePasswordVisibility(inputId, buttonId) {
    var input = document.getElementById(inputId);
    var button = document.getElementById(buttonId);
    
    if (input && button) {
        button.addEventListener('click', function() {
            var type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    }
}

/**
 * Initialize data table
 */
function initDataTable(tableId, options) {
    options = options || {};
    
    var table = document.getElementById(tableId);
    if (!table) return;
    
    var rows = table.querySelectorAll('tbody tr');
    var pagination = options.pagination || true;
    var pageSize = options.pageSize || 10;
    
    function showPage(page) {
        var start = (page - 1) * pageSize;
        var end = start + pageSize;
        
        rows.forEach(function(row, index) {
            row.style.display = index >= start && index < end ? '' : 'none';
        });
    }
    
    if (pagination) {
        var totalPages = Math.ceil(rows.length / pageSize);
        if (totalPages > 1) {
            var paginationHTML = '<nav><ul class="pagination justify-content-center mt-3">';
            
            for (var i = 1; i <= totalPages; i++) {
                paginationHTML += '<li class="page-item" data-page="' + i + '"><a class="page-link" href="#">' + i + '</a></li>';
            }
            
            paginationHTML += '</ul></nav>';
            table.parentNode.insertAdjacentHTML('beforeend', paginationHTML);
            
            var paginationItems = table.parentNode.querySelectorAll('.page-item');
            paginationItems.forEach(function(item) {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    paginationItems.forEach(function(p) { return p.classList.remove('active'); });
                    this.classList.add('active');
                    showPage(parseInt(this.dataset.page));
                });
            });
            
            paginationItems[0].classList.add('active');
        }
    }
    
    showPage(1);
}

/**
 * Add loading state to button
 */
function setButtonLoading(button, loading) {
    if (!button) return;
    
    if (loading) {
        button.disabled = true;
        var originalText = button.innerHTML;
        button.setAttribute('data-loading-text', originalText);
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Loading...';
    } else {
        button.disabled = false;
        var originalText = button.getAttribute('data-loading-text');
        if (originalText) {
            button.innerHTML = originalText;
        }
    }
}

/**
 * Parse URL parameters
 */
function getUrlParams() {
    var params = {};
    var queryString = window.location.search.substring(1);
    var pairs = queryString.split('&');
    
    pairs.forEach(function(pair) {
        var keyValue = pair.split('=');
        var key = decodeURIComponent(keyValue[0]);
        var value = decodeURIComponent(keyValue[1] || '');
        params[key] = value;
    });
    
    return params;
}

/**
 * Update URL without reload
 */
function updateUrlParams(params) {
    var url = new URL(window.location.href);
    
    for (var key in params) {
        url.searchParams.set(key, params[key]);
    }
    
    window.history.pushState({}, '', url);
}

/**
 * Initialize image lazy loading
 */
function initLazyLoading() {
    var images = document.querySelectorAll('img[data-src]');
    
    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    observer.unobserve(img);
                }
            });
        });
        
        images.forEach(function(img) {
            observer.observe(img);
        });
    } else {
        // Fallback for browsers without IntersectionObserver
        images.forEach(function(img) {
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
        });
    }
}
