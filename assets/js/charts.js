/**
 * BENGKEL MANAGEMENT PRO - CHARTS MODULE
 * Version: 3.1.0
 * Advanced Chart.js Integration with Real-time Updates
 */

class BengkelCharts {
    constructor() {
        this.charts = new Map();
        this.defaultColors = [
            '#0d6efd', '#198754', '#dc3545', '#ffc107', '#0dcaf0',
            '#6f42c1', '#fd7e14', '#20c997', '#e83e8c', '#6c757d'
        ];
        this.gradientColors = {
            primary: ['#0d6efd', '#4a90e2'],
            success: ['#198754', '#20c997'],
            danger: ['#dc3545', '#e74c3c'],
            warning: ['#ffc107', '#f39c12'],
            info: ['#0dcaf0', '#3498db']
        };
        
        this.init();
    }
    
    init() {
        // Set Chart.js defaults
        Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
        Chart.defaults.font.size = 12;
        Chart.defaults.color = '#6c757d';
        Chart.defaults.borderColor = '#dee2e6';
        Chart.defaults.backgroundColor = 'rgba(13, 110, 253, 0.1)';
        
        // Register custom plugins
        this.registerCustomPlugins();
        
        // Initialize charts on page load
        this.initializeCharts();
        
        // Setup real-time updates
        this.setupRealTimeUpdates();
    }
    
    registerCustomPlugins() {
        // Custom plugin for gradient backgrounds
        Chart.register({
            id: 'gradientBackground',
            beforeDraw: (chart) => {
                if (chart.config.options.plugins?.gradientBackground) {
                    const ctx = chart.ctx;
                    const chartArea = chart.chartArea;
                    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                    
                    const colors = chart.config.options.plugins.gradientBackground.colors;
                    gradient.addColorStop(0, colors[0]);
                    gradient.addColorStop(1, colors[1]);
                    
                    ctx.fillStyle = gradient;
                    ctx.fillRect(chartArea.left, chartArea.top, 
                               chartArea.right - chartArea.left, 
                               chartArea.bottom - chartArea.top);
                }
            }
        });
        
        // Custom plugin for data labels
        Chart.register({
            id: 'customDataLabels',
            afterDatasetsDraw: (chart) => {
                if (chart.config.options.plugins?.customDataLabels?.enabled) {
                    const ctx = chart.ctx;
                    ctx.font = '12px Arial';
                    ctx.fillStyle = '#333';
                    ctx.textAlign = 'center';
                    
                    chart.data.datasets.forEach((dataset, datasetIndex) => {
                        const meta = chart.getDatasetMeta(datasetIndex);
                        meta.data.forEach((element, index) => {
                            const value = dataset.data[index];
                            if (value > 0) {
                                ctx.fillText(value, element.x, element.y - 5);
                            }
                        });
                    });
                }
            }
        });
    }
    
    initializeCharts() {
        // Find all chart containers and initialize them
        const chartContainers = document.querySelectorAll('[data-chart]');
        chartContainers.forEach(container => {
            const chartType = container.dataset.chart;
            const chartId = container.id || `chart-${Date.now()}`;
            
            if (!container.id) {
                container.id = chartId;
            }
            
            this.createChart(chartId, chartType, container.dataset);
        });
    }
    
    createChart(containerId, type, options = {}) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error(`Chart container ${containerId} not found`);
            return null;
        }
        
        const canvas = container.querySelector('canvas') || this.createCanvas(container);
        const ctx = canvas.getContext('2d');
        
        let chartConfig;
        
        switch (type) {
            case 'revenue':
                chartConfig = this.createRevenueChart(options);
                break;
            case 'workorders':
                chartConfig = this.createWorkOrdersChart(options);
                break;
            case 'inventory':
                chartConfig = this.createInventoryChart(options);
                break;
            case 'customer-satisfaction':
                chartConfig = this.createCustomerSatisfactionChart(options);
                break;
            case 'service-performance':
                chartConfig = this.createServicePerformanceChart(options);
                break;
            case 'monthly-comparison':
                chartConfig = this.createMonthlyComparisonChart(options);
                break;
            case 'top-services':
                chartConfig = this.createTopServicesChart(options);
                break;
            case 'technician-performance':
                chartConfig = this.createTechnicianPerformanceChart(options);
                break;
            default:
                console.error(`Unknown chart type: ${type}`);
                return null;
        }
        
        const chart = new Chart(ctx, chartConfig);
        this.charts.set(containerId, chart);
        
        // Load initial data
        this.loadChartData(containerId, type);
        
        return chart;
    }
    
    createCanvas(container) {
        const canvas = document.createElement('canvas');
        canvas.style.maxHeight = '400px';
        container.appendChild(canvas);
        return canvas;
    }
    
    // ========================================
    // CHART CONFIGURATIONS
    // ========================================
    
    createRevenueChart(options) {
        return {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Revenue',
                    data: [],
                    borderColor: this.gradientColors.primary[0],
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: this.gradientColors.primary[0],
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: this.gradientColors.primary[0],
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: (context) => {
                                return `Revenue: ${this.formatCurrency(context.parsed.y)}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6c757d'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            color: '#6c757d',
                            callback: (value) => this.formatCurrency(value)
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        };
    }
    
    createWorkOrdersChart(options) {
        return {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Completed', 'Cancelled'],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        '#ffc107',
                        '#0dcaf0',
                        '#198754',
                        '#dc3545'
                    ],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        cornerRadius: 8,
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
        };
    }
    
    createInventoryChart(options) {
        return {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Stock Level',
                    data: [],
                    backgroundColor: (context) => {
                        const value = context.parsed?.y;
                        if (value <= 10) return '#dc3545';
                        if (value <= 25) return '#ffc107';
                        return '#198754';
                    },
                    borderRadius: 4,
                    borderSkipped: false
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
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        cornerRadius: 8,
                        callbacks: {
                            afterLabel: (context) => {
                                const value = context.parsed.y;
                                if (value <= 10) return 'Status: Critical';
                                if (value <= 25) return 'Status: Low';
                                return 'Status: Good';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45,
                            color: '#6c757d'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            color: '#6c757d'
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        };
    }
    
    createCustomerSatisfactionChart(options) {
        return {
            type: 'radar',
            data: {
                labels: ['Service Quality', 'Timeliness', 'Communication', 'Pricing', 'Overall Experience'],
                datasets: [{
                    label: 'Current Month',
                    data: [],
                    borderColor: this.gradientColors.primary[0],
                    backgroundColor: 'rgba(13, 110, 253, 0.2)',
                    borderWidth: 2,
                    pointBackgroundColor: this.gradientColors.primary[0],
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }, {
                    label: 'Previous Month',
                    data: [],
                    borderColor: this.gradientColors.success[0],
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    borderWidth: 2,
                    pointBackgroundColor: this.gradientColors.success[0],
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 5,
                        ticks: {
                            stepSize: 1,
                            color: '#6c757d'
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        pointLabels: {
                            color: '#6c757d',
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                animation: {
                    duration: 1000
                }
            }
        };
    }
    
    createServicePerformanceChart(options) {
        return {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Completed',
                    data: [],
                    backgroundColor: this.gradientColors.success[0],
                    borderRadius: 4
                }, {
                    label: 'Average Time (hours)',
                    data: [],
                    backgroundColor: this.gradientColors.info[0],
                    borderRadius: 4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        cornerRadius: 8
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6c757d'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            color: '#6c757d'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            color: '#6c757d'
                        }
                    }
                },
                animation: {
                    duration: 1000
                }
            }
        };
    }
    
    createMonthlyComparisonChart(options) {
        return {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'This Year',
                    data: [],
                    borderColor: this.gradientColors.primary[0],
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 3,
                    fill: false,
                    tension: 0.4
                }, {
                    label: 'Last Year',
                    data: [],
                    borderColor: this.gradientColors.success[0],
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    borderWidth: 3,
                    fill: false,
                    tension: 0.4,
                    borderDash: [5, 5]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        cornerRadius: 8,
                        callbacks: {
                            label: (context) => {
                                return `${context.dataset.label}: ${this.formatCurrency(context.parsed.y)}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6c757d'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            color: '#6c757d',
                            callback: (value) => this.formatCurrency(value)
                        }
                    }
                },
                animation: {
                    duration: 1000
                }
            }
        };
    }
    
    createTopServicesChart(options) {
        return {
            type: 'horizontalBar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Revenue',
                    data: [],
                    backgroundColor: this.defaultColors.slice(0, 10),
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        cornerRadius: 8,
                        callbacks: {
                            label: (context) => {
                                return `Revenue: ${this.formatCurrency(context.parsed.x)}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            color: '#6c757d',
                            callback: (value) => this.formatCurrency(value)
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6c757d'
                        }
                    }
                },
                animation: {
                    duration: 1000
                }
            }
        };
    }
    
    createTechnicianPerformanceChart(options) {
        return {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'Technician Performance',
                    data: [],
                    backgroundColor: this.gradientColors.primary[0],
                    borderColor: this.gradientColors.primary[1],
                    borderWidth: 2,
                    pointRadius: 8,
                    pointHoverRadius: 12
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
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        cornerRadius: 8,
                        callbacks: {
                            title: (context) => {
                                return context[0].raw.name || 'Technician';
                            },
                            label: (context) => {
                                return [
                                    `Jobs Completed: ${context.parsed.x}`,
                                    `Avg Rating: ${context.parsed.y.toFixed(1)}/5`
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'linear',
                        position: 'bottom',
                        title: {
                            display: true,
                            text: 'Jobs Completed',
                            color: '#6c757d'
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            color: '#6c757d'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Average Rating',
                            color: '#6c757d'
                        },
                        min: 0,
                        max: 5,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            color: '#6c757d'
                        }
                    }
                },
                animation: {
                    duration: 1000
                }
            }
        };
    }
    
    // ========================================
    // DATA LOADING METHODS
    // ========================================
    
    async loadChartData(chartId, chartType) {
        try {
            const response = await fetch(`/api/charts.php?type=${chartType}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': window.APP_CONFIG.csrf_token
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.updateChartData(chartId, data.data);
            } else {
                console.error('Failed to load chart data:', data.message);
            }
        } catch (error) {
            console.error('Error loading chart data:', error);
            this.showChartError(chartId, 'Failed to load chart data');
        }
    }
    
    updateChartData(chartId, data) {
        const chart = this.charts.get(chartId);
        if (!chart) {
            console.error(`Chart ${chartId} not found`);
            return;
        }
        
        // Update labels
        if (data.labels) {
            chart.data.labels = data.labels;
        }
        
        // Update datasets
        if (data.datasets) {
            data.datasets.forEach((datasetData, index) => {
                if (chart.data.datasets[index]) {
                    chart.data.datasets[index].data = datasetData.data;
                    if (datasetData.label) {
                        chart.data.datasets[index].label = datasetData.label;
                    }
                }
            });
        } else if (data.data) {
            // Single dataset
            if (chart.data.datasets[0]) {
                chart.data.datasets[0].data = data.data;
            }
        }
        
        // Update chart
        chart.update('active');
        
        // Hide loading indicator
        this.hideChartLoading(chartId);
    }
    
    showChartError(chartId, message) {
        const container = document.getElementById(chartId);
        if (container) {
            container.innerHTML = `
                <div class="d-flex align-items-center justify-content-center h-100">
                    <div class="text-center text-muted">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                        <p>${message}</p>
                        <button class="btn btn-sm btn-outline-primary" onclick="bengkelCharts.reloadChart('${chartId}')">
                            <i class="fas fa-redo"></i> Retry
                        </button>
                    </div>
                </div>
            `;
        }
    }
    
    hideChartLoading(chartId) {
        const container = document.getElementById(chartId);
        const loader = container?.querySelector('.chart-loader');
        if (loader) {
            loader.remove();
        }
    }
    
    // ========================================
    // REAL-TIME UPDATES
    // ========================================
    
    setupRealTimeUpdates() {
        // Update charts every 5 minutes
        setInterval(() => {
            this.refreshAllCharts();
        }, 5 * 60 * 1000);
        
        // Listen for custom events
        document.addEventListener('chartUpdate', (event) => {
            const { chartId, data } = event.detail;
            this.updateChartData(chartId, data);
        });
        
        // Listen for data changes
        document.addEventListener('dataChanged', (event) => {
            const { type } = event.detail;
            this.refreshChartsByType(type);
        });
    }
    
    refreshAllCharts() {
        this.charts.forEach((chart, chartId) => {
            const container = document.getElementById(chartId);
            const chartType = container?.dataset.chart;
            if (chartType) {
                this.loadChartData(chartId, chartType);
            }
        });
    }
    
    refreshChartsByType(type) {
        this.charts.forEach((chart, chartId) => {
            const container = document.getElementById(chartId);
            const chartType = container?.dataset.chart;
            if (chartType === type) {
                this.loadChartData(chartId, chartType);
            }
        });
    }
    
    reloadChart(chartId) {
        const container = document.getElementById(chartId);
        const chartType = container?.dataset.chart;
        if (chartType) {
            this.loadChartData(chartId, chartType);
        }
    }
    
    // ========================================
    // UTILITY METHODS
    // ========================================
    
    formatCurrency(amount) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(amount);
    }
    
    createGradient(ctx, colorStart, colorEnd) {
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, colorStart);
        gradient.addColorStop(1, colorEnd);
        return gradient;
    }
    
    exportChart(chartId, format = 'png') {
        const chart = this.charts.get(chartId);
        if (!chart) {
            console.error(`Chart ${chartId} not found`);
            return;
        }
        
        const url = chart.toBase64Image();
        const link = document.createElement('a');
        link.download = `chart-${chartId}-${Date.now()}.${format}`;
        link.href = url;
        link.click();
    }
    
    printChart(chartId) {
        const chart = this.charts.get(chartId);
        if (!chart) {
            console.error(`Chart ${chartId} not found`);
            return;
        }
        
        const url = chart.toBase64Image();
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Chart Print</title>
                    <style>
                        body { margin: 0; padding: 20px; }
                        img { max-width: 100%; height: auto; }
                    </style>
                </head>
                <body>
                    <img src="${url}" alt="Chart">
                    <script>
                        window.onload = function() {
                            window.print();
                            window.close();
                        }
                    </script>
                </body>
            </html>
        `);
        printWindow.document.close();
    }
    
    destroyChart(chartId) {
        const chart = this.charts.get(chartId);
        if (chart) {
            chart.destroy();
            this.charts.delete(chartId);
        }
    }
    
    destroyAllCharts() {
        this.charts.forEach((chart, chartId) => {
            chart.destroy();
        });
        this.charts.clear();
    }
    
    // ========================================
    // PUBLIC API METHODS
    // ========================================
    
    getChart(chartId) {
        return this.charts.get(chartId);
    }
    
    updateChart(chartId, newData) {
        this.updateChartData(chartId, newData);
    }
    
    addDataPoint(chartId, label, data) {
        const chart = this.charts.get(chartId);
        if (!chart) return;
        
        chart.data.labels.push(label);
        chart.data.datasets.forEach((dataset, index) => {
            dataset.data.push(data[index] || 0);
        });
        
        chart.update();
    }
    
    removeDataPoint(chartId, index) {
        const chart = this.charts.get(chartId);
        if (!chart) return;
        
        chart.data.labels.splice(index, 1);
        chart.data.datasets.forEach(dataset => {
            dataset.data.splice(index, 1);
        });
        
        chart.update();
    }
    
    setChartTheme(theme = 'light') {
        const isDark = theme === 'dark';
        
        Chart.defaults.color = isDark ? '#e9ecef' : '#6c757d';
        Chart.defaults.borderColor = isDark ? '#495057' : '#dee2e6';
        
        // Update existing charts
        this.charts.forEach(chart => {
            if (chart.options.scales) {
                Object.keys(chart.options.scales).forEach(scaleKey => {
                    const scale = chart.options.scales[scaleKey];
                    if (scale.ticks) {
                        scale.ticks.color = isDark ? '#e9ecef' : '#6c757d';
                    }
                    if (scale.grid) {
                        scale.grid.color = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.05)';
                    }
                });
            }
            chart.update();
        });
    }
}

// Initialize charts when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.bengkelCharts = new BengkelCharts();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BengkelCharts;
}
