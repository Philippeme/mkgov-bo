document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('adminSidebar');
    const content = document.getElementById('adminContent');
    const sidebarSwitchBtn = document.getElementById('sidebarSwitchBtn');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const fullscreenBtn = document.getElementById('fullscreenBtn');

    let isAutoMode = false; // Mode automatique avec souris
    let hoverTimeout;

    // Switch Button functionality
    if (sidebarSwitchBtn) {
        sidebarSwitchBtn.addEventListener('click', function() {
            this.classList.toggle('active');
            
            if (this.classList.contains('active')) {
                // Mode fixe étendu
                isAutoMode = false;
                sidebar.classList.remove('collapsed');
                content.classList.remove('collapsed');
            } else {
        
                // Mode auto avec souris
                isAutoMode = true;
                sidebar.classList.add('collapsed');
                content.classList.add('collapsed');
            }
        });
    }

    // Mouse hover behavior when in auto mode
    if (sidebar) {
        sidebar.addEventListener('mouseenter', function() {
            if (isAutoMode) {
                clearTimeout(hoverTimeout);
                this.classList.remove('collapsed');
                content.classList.remove('collapsed');
            }
        });

        sidebar.addEventListener('mouseleave', function() {
            if (isAutoMode) {
                hoverTimeout = setTimeout(() => {
                    this.classList.add('collapsed');
                    content.classList.add('collapsed');
                }, 300); // Délai de 300ms
            }
        });
    }

    // Mobile menu toggle
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }

    // Fullscreen functionality
    if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', function() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().then(() => {
                    this.innerHTML = '<i class="fas fa-compress"></i>';
                });
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen().then(() => {
                        this.innerHTML = '<i class="fas fa-expand"></i>';
                    });
                }
            }
        });
    }

    // Handle fullscreen change events
    document.addEventListener('fullscreenchange', function() {
        const fullscreenBtn = document.getElementById('fullscreenBtn');
        if (fullscreenBtn) {
            if (document.fullscreenElement) {
                fullscreenBtn.innerHTML = '<i class="fas fa-compress"></i>';
            } else {
                fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i>';
            }
        }
    });

    // Gestion des sous-menus
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle-custom');
    
    dropdownToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            const parentLi = this.closest('.nav-item.dropdown');
            const dropdownMenu = parentLi.querySelector('.dropdown-menu');
            const isCurrentlyOpen = parentLi.classList.contains('open');
            
            // Fermer tous les autres accordéons
            document.querySelectorAll('.nav-item.dropdown.open').forEach(function(openItem) {
                if (openItem !== parentLi) {
                    openItem.classList.remove('open');
                    openItem.querySelector('.dropdown-menu').classList.remove('show');
                }
            });
            
            // Toggle l'accordéon actuel
            if (isCurrentlyOpen) {
                parentLi.classList.remove('open');
                dropdownMenu.classList.remove('show');
            } else {
                parentLi.classList.add('open');
                dropdownMenu.classList.add('show');
            }
        });
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('show');
        }
    });

    // Search functionality
    const searchInput = document.getElementById('headerSearch');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const query = this.value.trim();
                if (query) {
                    console.log('Searching for:', query);
                }
            }
        });
    }

    // Language selector
    const languageItems = document.querySelectorAll('[data-lang]');
    languageItems.forEach(function(item) {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const lang = this.dataset.lang;
            const langDisplay = document.querySelector('#languageDropdown span');
            
            if (lang === 'fr') {
                langDisplay.textContent = 'FR';
            } else if (lang === 'en') {
                langDisplay.textContent = 'EN';
            }
            
            console.log('Language changed to:', lang);
        });
    });

    // Notification functionality
    const notificationBtn = document.getElementById('notificationBtn');
    if (notificationBtn) {
        notificationBtn.addEventListener('click', function() {
            console.log('Notifications clicked');
        });
    }

    // Auto-dismiss flash messages after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.flash-message');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Table row hover effects
    const tableRows = document.querySelectorAll('.table tbody tr');
    tableRows.forEach(function(row) {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.01)';
            this.style.transition = 'all 0.2s ease';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });

    // Card hover effects
    const cards = document.querySelectorAll('.card');
    cards.forEach(function(card) {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.transition = 'all 0.3s ease';
            this.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.15)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1)';
        });
    });

    // Smooth scrolling for anchor links
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Form validation helpers
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                const firstInvalid = this.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
        });
    });

    // Loading states for buttons
    const loadingButtons = document.querySelectorAll('.btn[data-loading]');
    loadingButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (this.dataset.loading === 'true') {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
            }
        });
    });

    // Initialize all popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Theme persistence
    const theme = localStorage.getItem('mkba-theme') || 'light';
    document.body.setAttribute('data-theme', theme);

    console.log('%c🚀 MK BA Admin Dashboard', 'color: #6d5192; font-size: 16px; font-weight: bold;');
});

// Utility functions
window.MKBAAdmin = {
    // Show notification
    showNotification: function(message, type = 'info') {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show flash-message" role="alert">
                <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-triangle' : 'info-circle')}"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        const flashContainer = document.querySelector('.flash-messages');
        if (flashContainer) {
            flashContainer.insertAdjacentHTML('beforeend', alertHtml);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                const newAlert = flashContainer.lastElementChild;
                if (newAlert && newAlert.classList.contains('flash-message')) {
                    const bsAlert = new bootstrap.Alert(newAlert);
                    bsAlert.close();
                }
            }, 5000);
        }
    },
    
    // Confirm dialog
    confirm: function(message, callback) {
        if (window.confirm(message)) {
            if (typeof callback === 'function') {
                callback();
            }
        }
    },
    
    // AJAX request helper
    request: function(url, options = {}) {
        const defaults = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        const config = Object.assign(defaults, options);
        
        return fetch(url, config)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .catch(error => {
                console.error('Request failed:', error);
                throw error;
            });
    }
};