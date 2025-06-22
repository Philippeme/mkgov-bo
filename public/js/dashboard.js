/**
 * MK Gov Dashboard JavaScript
 * Enhanced dashboard functionality with charts, filters, and real-time updates
 */

class MKGovDashboard {
    constructor() {
        this.charts = {};
        this.filters = {};
        this.refreshInterval = 300000; // 5 minutes
        this.autoRefreshTimer = null;
        
        this.colors = {
            primary: '#1a4b8f',
            secondary: '#f18221',
            success: '#28a745',
            danger: '#dc3545',
            warning: '#ffc107',
            info: '#17a2b8',
            light: '#f8f9fa',
            dark: '#343a40'
        };

        this.chartColors = [
            this.colors.primary,
            this.colors.secondary,
            this.colors.success,
            this.colors.danger,
            this.colors.warning,
            this.colors.info,
            '#e83e8c',
            '#6f42c1',
            '#fd7e14',
            '#20c997'
        ];

        this.init();
    }

    /**
     * Initialize dashboard
     */
    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.initializeCharts();
            this.initializeFilters();
            this.initializeEventListeners();
            this.initializeMapInteractions();
            this.startAutoRefresh();
            this.showWelcomeMessage();
        });
    }

    /**
     * Initialize all charts
     */
    initializeCharts() {
        this.initDonutChart();
        this.initBarChart();
        this.initLineChart();
    }

    /**
     * Initialize donut chart for requests by family
     */
    initDonutChart() {
        const canvas = document.getElementById('donutChart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        const familyData = window.dashboardData?.requestsByFamily || [];
        
        this.charts.donut = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: familyData.map(item => item.family_name || 'Unassigned'),
                datasets: [{
                    data: familyData.map(item => item.count),
                    backgroundColor: this.chartColors.slice(0, familyData.length),
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                size: 12
                            },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return `${context.label}: ${context.parsed} (${percentage}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    duration: 1000
                }
            }
        });
    }

    /**
     * Initialize bar chart for requests by gender
     */
    initBarChart() {
        const canvas = document.getElementById('barChart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        const genderData = window.dashboardData?.requestsByGender || { labels: [], data: [] };
        
        this.charts.bar = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: genderData.labels,
                datasets: [{
                    label: 'Number of Requests',
                    data: genderData.data,
                    backgroundColor: [this.colors.info + '80', this.colors.warning + '80'],
                    borderColor: [this.colors.info, this.colors.warning],
                    borderWidth: 2,
                    borderRadius: 4,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((context.parsed.y / total) * 100).toFixed(1) : 0;
                                return `${context.label}: ${context.parsed.y} (${percentage}%)`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                }
            }
        });
    }

    /**
     * Initialize line chart for monthly requests
     */
    initLineChart() {
        const canvas = document.getElementById('lineChart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        const monthlyData = window.dashboardData?.monthlyRequests || { labels: [], data: [] };
        
        this.charts.line = new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.labels,
                datasets: [{
                    label: 'Requests',
                    data: monthlyData.data,
                    borderColor: this.colors.primary,
                    backgroundColor: this.colors.primary + '20',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: this.colors.primary,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 3,
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: this.colors.primary,
                        borderWidth: 1
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    intersect: false
                },
                animation: {
                    duration: 1500,
                    easing: 'easeOutQuart'
                }
            }
        });
    }

    /**
     * Initialize filters functionality
     */
    initializeFilters() {
        const filterElements = {
            region: document.getElementById('regionFilter'),
            family: document.getElementById('familyFilter'),
            request: document.getElementById('requestFilter'),
            year: document.getElementById('yearFilter')
        };

        // Store filter elements
        this.filterElements = filterElements;

        // Initialize filter change listeners
        Object.keys(filterElements).forEach(key => {
            if (filterElements[key]) {
                filterElements[key].addEventListener('change', () => {
                    this.updateActiveFilters();
                });
            }
        });
    }

    /**
     * Initialize event listeners
     */
    initializeEventListeners() {
        // Apply filters button
        const applyBtn = document.querySelector('[onclick="applyFilters()"]');
        if (applyBtn) {
            applyBtn.removeAttribute('onclick');
            applyBtn.addEventListener('click', () => this.applyFilters());
        }

        // Clear filters button
        const clearBtn = document.querySelector('[onclick="clearFilters()"]');
        if (clearBtn) {
            clearBtn.removeAttribute('onclick');
            clearBtn.addEventListener('click', () => this.clearFilters());
        }

        // Export functionality
        this.initializeExportButtons();

        // Auto-refresh toggle
        this.initializeAutoRefreshToggle();

        // Keyboard shortcuts
        this.initializeKeyboardShortcuts();
    }

    /**
     * Initialize map interactions
     */
    initializeMapInteractions() {
        const regionMarkers = document.querySelectorAll('.region-marker');
        
        regionMarkers.forEach(marker => {
            marker.addEventListener('click', (e) => {
                const region = e.currentTarget.dataset.region;
                const count = e.currentTarget.dataset.count;
                
                // Show region details
                this.showRegionDetails(region, count);
                
                // Auto-filter by region
                if (this.filterElements.region) {
                    this.filterElements.region.value = region;
                    this.applyFilters();
                }
            });

            // Enhanced hover effects
            marker.addEventListener('mouseenter', (e) => {
                e.currentTarget.style.transform = 'scale(1.3)';
                e.currentTarget.style.zIndex = '20';
            });

            marker.addEventListener('mouseleave', (e) => {
                e.currentTarget.style.transform = 'scale(1)';
                e.currentTarget.style.zIndex = '10';
            });
        });
    }

    /**
     * Apply filters and update dashboard
     */
    async applyFilters() {
        const filters = this.getCurrentFilters();
        
        this.showLoadingSpinners();
        this.updateActiveFilters(filters);

        try {
            const response = await fetch(`/admin/dashboard/filter?${new URLSearchParams(filters)}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.updateCharts(data.data);
                this.updateStatistics(data.data);
                this.showSuccessMessage('Filters applied successfully');
            } else {
                throw new Error(data.message || 'Failed to apply filters');
            }
        } catch (error) {
            console.error('Error applying filters:', error);
            this.showErrorMessage('Error applying filters: ' + error.message);
        } finally {
            this.hideLoadingSpinners();
        }
    }

    /**
     * Clear all filters
     */
    clearFilters() {
        Object.values(this.filterElements).forEach(element => {
            if (element) element.value = '';
        });
        
        this.hideActiveFilters();
        
        // Reload page to reset all data
        window.location.reload();
    }

    /**
     * Get current filter values
     */
    getCurrentFilters() {
        const filters = {};
        
        Object.keys(this.filterElements).forEach(key => {
            const element = this.filterElements[key];
            if (element && element.value) {
                filters[key] = element.value;
            }
        });
        
        return filters;
    }

    /**
     * Update active filters display
     */
    updateActiveFilters(filters = null) {
        const activeFiltersContainer = document.getElementById('activeFilters');
        const filterBadges = document.getElementById('filterBadges');
        
        if (!activeFiltersContainer || !filterBadges) return;
        
        const currentFilters = filters || this.getCurrentFilters();
        
        filterBadges.innerHTML = '';
        let hasActiveFilters = false;

        Object.entries(currentFilters).forEach(([key, value]) => {
            if (value) {
                hasActiveFilters = true;
                const badge = document.createElement('span');
                badge.className = 'filter-badge';
                badge.innerHTML = `${key}: ${value} <span class="remove" data-filter="${key}">&times;</span>`;
                filterBadges.appendChild(badge);
            }
        });

        // Add event listeners to remove buttons
        filterBadges.querySelectorAll('.remove').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const filterKey = e.target.dataset.filter;
                this.removeFilter(filterKey);
            });
        });

        activeFiltersContainer.style.display = hasActiveFilters ? 'block' : 'none';
    }

    /**
     * Remove specific filter
     */
    removeFilter(filterKey) {
        if (this.filterElements[filterKey]) {
            this.filterElements[filterKey].value = '';
            this.applyFilters();
        }
    }

    /**
     * Hide active filters
     */
    hideActiveFilters() {
        const activeFiltersContainer = document.getElementById('activeFilters');
        if (activeFiltersContainer) {
            activeFiltersContainer.style.display = 'none';
        }
    }

    /**
     * Update charts with new data
     */
    updateCharts(data) {
        // Update donut chart
        if (this.charts.donut && data.requestsByFamily) {
            this.charts.donut.data.labels = data.requestsByFamily.map(item => item.family_name || 'Unassigned');
            this.charts.donut.data.datasets[0].data = data.requestsByFamily.map(item => item.count);
            this.charts.donut.update('resize');
        }

        // Update bar chart
        if (this.charts.bar && data.requestsByGender) {
            this.charts.bar.data.labels = data.requestsByGender.labels;
            this.charts.bar.data.datasets[0].data = data.requestsByGender.data;
            this.charts.bar.update('resize');
        }

        // Update line chart
        if (this.charts.line && data.monthlyRequests) {
            this.charts.line.data.labels = data.monthlyRequests.labels;
            this.charts.line.data.datasets[0].data = data.monthlyRequests.data;
            this.charts.line.update('resize');
        }
    }

    /**
     * Update statistics cards
     */
    updateStatistics(data) {
        if (!data.stats) return;

        const statElements = {
            institutions: document.querySelector('.stats-card:nth-child(1) .stats-value'),
            procedures: document.querySelector('.stats-card:nth-child(2) .stats-value'),
            families: document.querySelector('.stats-card:nth-child(3) .stats-value'),
            requests: document.querySelector('.stats-card:nth-child(4) .stats-value'),
            pending_admin: document.querySelector('.stats-card:nth-child(5) .stats-value'),
            pending_requestor: document.querySelector('.stats-card:nth-child(6) .stats-value')
        };

        Object.keys(statElements).forEach(key => {
            if (statElements[key] && data.stats[key] !== undefined) {
                this.animateNumber(statElements[key], data.stats[key]);
            }
        });
    }

    /**
     * Animate number changes
     */
    animateNumber(element, newValue) {
        const currentValue = parseInt(element.textContent) || 0;
        const increment = newValue > currentValue ? 1 : -1;
        const duration = 1000;
        const steps = Math.abs(newValue - currentValue);
        const stepDuration = duration / steps;

        let current = currentValue;
        const timer = setInterval(() => {
            current += increment;
            element.textContent = current;
            
            if (current === newValue) {
                clearInterval(timer);
            }
        }, stepDuration);
    }

    /**
     * Show loading spinners
     */
    showLoadingSpinners() {
        ['donutLoading', 'barLoading', 'lineLoading'].forEach(id => {
            const element = document.getElementById(id);
            if (element) element.style.display = 'block';
        });
    }

    /**
     * Hide loading spinners
     */
    hideLoadingSpinners() {
        ['donutLoading', 'barLoading', 'lineLoading'].forEach(id => {
            const element = document.getElementById(id);
            if (element) element.style.display = 'none';
        });
    }

    /**
     * Show region details modal
     */
    showRegionDetails(region, count) {
        // Create a simple modal or use existing modal system
        const message = `Region: ${region}\nRequests: ${count}\n\nClick OK to filter by this region.`;
        
        if (confirm(message)) {
            // Already handled in the click event
        }
    }

    /**
     * Initialize export buttons
     */
    initializeExportButtons() {
        // Add export dropdown to dashboard
        const headerActions = document.querySelector('.header-actions');
        if (headerActions) {
            const exportBtn = document.createElement('div');
            exportBtn.className = 'dropdown';
            exportBtn.innerHTML = `
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-download me-2"></i>Export
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" data-format="json">JSON</a></li>
                    <li><a class="dropdown-item" href="#" data-format="csv">CSV</a></li>
                    <li><a class="dropdown-item" href="#" data-format="pdf">PDF Report</a></li>
                    <li><a class="dropdown-item" href="#" data-format="excel">Excel</a></li>
                </ul>
            `;
            
            headerActions.insertBefore(exportBtn, headerActions.firstChild);
            
            // Add export event listeners
            exportBtn.querySelectorAll('[data-format]').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.exportData(e.target.dataset.format);
                });
            });
        }
    }

    /**
     * Export dashboard data
     */
    async exportData(format) {
        const filters = this.getCurrentFilters();
        const params = new URLSearchParams({
            format: format,
            ...filters
        });

        try {
            const response = await fetch(`/admin/dashboard/export?${params}`);
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `dashboard_export_${format}_${new Date().toISOString().slice(0, 10)}.${format}`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                this.showSuccessMessage(`Dashboard exported as ${format.toUpperCase()}`);
            } else {
                throw new Error('Export failed');
            }
        } catch (error) {
            console.error('Export error:', error);
            this.showErrorMessage('Failed to export dashboard data');
        }
    }

    /**
     * Initialize auto-refresh functionality
     */
    initializeAutoRefreshToggle() {
        const headerActions = document.querySelector('.header-actions');
        if (headerActions) {
            const refreshBtn = document.createElement('button');
            refreshBtn.className = 'btn btn-outline-info';
            refreshBtn.innerHTML = '<i class="fas fa-sync-alt me-2"></i>Auto-refresh: ON';
            refreshBtn.id = 'autoRefreshToggle';
            
            headerActions.insertBefore(refreshBtn, headerActions.firstChild);
            
            refreshBtn.addEventListener('click', () => {
                this.toggleAutoRefresh();
            });
        }
    }

    /**
     * Toggle auto-refresh
     */
    toggleAutoRefresh() {
        const btn = document.getElementById('autoRefreshToggle');
        
        if (this.autoRefreshTimer) {
            clearInterval(this.autoRefreshTimer);
            this.autoRefreshTimer = null;
            btn.innerHTML = '<i class="fas fa-sync-alt me-2"></i>Auto-refresh: OFF';
            btn.className = 'btn btn-outline-secondary';
        } else {
            this.startAutoRefresh();
            btn.innerHTML = '<i class="fas fa-sync-alt me-2"></i>Auto-refresh: ON';
            btn.className = 'btn btn-outline-info';
        }
    }

    /**
     * Start auto-refresh
     */
    startAutoRefresh() {
        this.autoRefreshTimer = setInterval(() => {
            if (document.visibilityState === 'visible') {
                this.refreshDashboard();
            }
        }, this.refreshInterval);
    }

    /**
     * Refresh dashboard data
     */
    async refreshDashboard() {
        try {
            const filters = this.getCurrentFilters();
            const response = await fetch('/admin/dashboard/refresh', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(filters)
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.updateCharts(data.data);
                    this.updateStatistics(data.data);
                    this.showInfoMessage('Dashboard refreshed');
                }
            }
        } catch (error) {
            console.error('Auto-refresh error:', error);
        }
    }

    /**
     * Initialize keyboard shortcuts
     */
    initializeKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + R: Refresh dashboard
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                this.refreshDashboard();
            }
            
            // Ctrl/Cmd + E: Export as JSON
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                this.exportData('json');
            }
            
            // Escape: Clear filters
            if (e.key === 'Escape') {
                this.clearFilters();
            }
        });
    }

    /**
     * Show welcome message
     */
    showWelcomeMessage() {
        this.showInfoMessage('Dashboard loaded successfully. Use filters to analyze data.', 3000);
    }

    /**
     * Show success message
     */
    showSuccessMessage(message, duration = 3000) {
        this.showNotification(message, 'success', duration);
    }

    /**
     * Show error message
     */
    showErrorMessage(message, duration = 5000) {
        this.showNotification(message, 'danger', duration);
    }

    /**
     * Show info message
     */
    showInfoMessage(message, duration = 3000) {
        this.showNotification(message, 'info', duration);
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info', duration = 3000) {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, duration);
    }
}

// Initialize dashboard when script loads
const mkgovDashboard = new MKGovDashboard();