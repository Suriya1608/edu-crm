/* ========================================
   ADMISSION CRM - JAVASCRIPT
   Main JS file with all functionality
   ======================================== */

// ========================================
// DUMMY DATA
// ========================================

const staffData = [
    {
        name: 'Johnathan Smith',
        role: 'Senior Associate',
        avatar: 'https://i.pravatar.cc/150?img=33',
        assigned: 450,
        calls: 382,
        pending: { count: 5, status: 'low' },
        convRate: 18.4
    },
    {
        name: 'Sarah Williams',
        role: 'Associate',
        avatar: 'https://i.pravatar.cc/150?img=44',
        assigned: 320,
        calls: 290,
        pending: { count: 12, status: 'high' },
        convRate: 14.2
    },
    {
        name: 'Michael Chen',
        role: 'Junior Associate',
        avatar: 'https://i.pravatar.cc/150?img=15',
        assigned: 180,
        calls: 115,
        pending: { count: 28, status: 'critical' },
        convRate: 9.5
    }
];

const criticalAlertsData = [
    {
        name: 'Arjun Mehta',
        time: '10:30 AM',
        description: 'Scheduled call missed by Michael Chen.',
        hasActions: true
    },
    {
        name: 'Lisa Thompson',
        time: '09:15 AM',
        description: 'Payment link follow-up overdue.',
        hasActions: false
    }
];

const urgentAlertsData = [
    {
        name: 'Kevin Spacey',
        time: 'Just Now',
        description: 'Lead source: Facebook Ads. Course: B.Tech CS.'
    },
    {
        name: 'Maria Garcia',
        time: '12m ago',
        description: 'Lead source: Website. Course: MBA Finance.'
    }
];

const leadSourceData = {
    labels: ['Facebook Ads', 'Instagram', 'Google Search', 'Direct Website', 'Walk-ins'],
    data: [40, 25, 20, 10, 5],
    colors: ['#137fec', '#8b5cf6', '#10b981', '#f59e0b', '#94a3b8']
};

// Campus data for switching
const campusData = {
    main: {
        totalLeads: 4250,
        newLeads: 125,
        followups: 84,
        converted: 612,
        dropped: 145,
        activeTeam: 18
    },
    east: {
        totalLeads: 2890,
        newLeads: 87,
        followups: 52,
        converted: 398,
        dropped: 98,
        activeTeam: 12
    },
    online: {
        totalLeads: 5120,
        newLeads: 156,
        followups: 102,
        converted: 724,
        dropped: 187,
        activeTeam: 15
    }
};

// ========================================
// GLOBAL VARIABLES
// ========================================

let leadChart;
let currentChartType = 'doughnut';
let currentCampus = 'main';

// ========================================
// INITIALIZATION
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard initializing...');
    initializeStaffTable();
    initializeAlerts();
    initializeChart();
    startLiveUpdates();
    setupEventListeners();
    console.log('Dashboard initialized successfully!');
});

// ========================================
// STAFF TABLE
// ========================================

function initializeStaffTable() {
    const tbody = document.getElementById('staffTableBody');
    if (!tbody) return;

    tbody.innerHTML = staffData.map(staff => `
        <tr>
            <td>
                <div class="staff-info">
                    <div class="staff-avatar">
                        <img src="${staff.avatar}" alt="${staff.name}">
                    </div>
                    <div>
                        <p class="staff-name">${staff.name}</p>
                        <p class="staff-role">${staff.role}</p>
                    </div>
                </div>
            </td>
            <td class="fw-semibold">${staff.assigned}</td>
            <td>${staff.calls}</td>
            <td class="text-center">
                <span class="status-badge ${staff.pending.status}">
                    ${String(staff.pending.count).padStart(2, '0')} ${staff.pending.status}
                </span>
            </td>
            <td class="text-end fw-bold text-primary">${staff.convRate}%</td>
        </tr>
    `).join('');
}

// ========================================
// ALERTS SYSTEM
// ========================================

function initializeAlerts() {
    const criticalContainer = document.getElementById('criticalAlerts');
    const urgentContainer = document.getElementById('urgentAlerts');
    
    if (criticalContainer) {
        criticalContainer.innerHTML = criticalAlertsData.map(alert => `
            <div class="alert-item critical">
                <div class="alert-item-header">
                    <p class="alert-item-name">${alert.name}</p>
                    <span class="alert-time">${alert.time}</span>
                </div>
                <p class="alert-description">${alert.description}</p>
                ${alert.hasActions ? `
                    <div class="alert-actions">
                        <button onclick="reassignAlert('${alert.name}')">REASSIGN</button>
                        <button class="primary" onclick="callNow('${alert.name}')">CALL NOW</button>
                    </div>
                ` : ''}
            </div>
        `).join('');
    }

    if (urgentContainer) {
        urgentContainer.innerHTML = urgentAlertsData.map(alert => `
            <div class="alert-item info">
                <div class="alert-item-header">
                    <p class="alert-item-name">${alert.name}</p>
                    <span class="alert-time">${alert.time}</span>
                </div>
                <p class="alert-description">${alert.description}</p>
            </div>
        `).join('');
    }
}

function reassignAlert(name) {
    const staffNames = staffData.map((s, i) => `${i + 1}. ${s.name}`).join('\n');
    alert(`Reassigning lead: ${name}\n\nAvailable staff:\n${staffNames}`);
}

function callNow(name) {
    alert(`Initiating call to: ${name}\n\nDialing...`);
    // You can add actual call functionality here
}

function clearAllAlerts() {
    if (confirm('Are you sure you want to clear all alerts?')) {
        const criticalContainer = document.getElementById('criticalAlerts');
        const urgentContainer = document.getElementById('urgentAlerts');
        const alertCount = document.getElementById('alertCount');
        
        if (criticalContainer) {
            criticalContainer.innerHTML = '<p class="text-muted text-center py-3" style="font-size: 12px;">No critical alerts</p>';
        }
        if (urgentContainer) {
            urgentContainer.innerHTML = '<p class="text-muted text-center py-3" style="font-size: 12px;">No urgent alerts</p>';
        }
        if (alertCount) {
            alertCount.textContent = '0';
        }
    }
}

// ========================================
// CHART.JS INTEGRATION
// ========================================

function initializeChart() {
    const ctx = document.getElementById('leadSourceChart');
    if (!ctx) return;
    
    leadChart = new Chart(ctx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: leadSourceData.labels,
            datasets: [{
                data: leadSourceData.data,
                backgroundColor: leadSourceData.colors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 13,
                            family: 'Manrope'
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.parsed + '%';
                        }
                    }
                }
            },
            cutout: '70%'
        }
    });
}

function switchChartType(type) {
    currentChartType = type;
    
    const btnDoughnut = document.getElementById('btnDoughnut');
    const btnBar = document.getElementById('btnBar');
    
    if (btnDoughnut && btnBar) {
        btnDoughnut.className = type === 'doughnut' ? 'btn btn-sm btn-light' : 'btn btn-sm btn-outline-secondary';
        btnBar.className = type === 'bar' ? 'btn btn-sm btn-light' : 'btn btn-sm btn-outline-secondary';
    }
    
    if (leadChart) {
        leadChart.destroy();
    }
    
    const ctx = document.getElementById('leadSourceChart');
    if (!ctx) return;
    
    if (type === 'doughnut') {
        leadChart = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: leadSourceData.labels,
                datasets: [{
                    data: leadSourceData.data,
                    backgroundColor: leadSourceData.colors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 13,
                                family: 'Manrope'
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + '%';
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });
    } else {
        leadChart = new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: leadSourceData.labels,
                datasets: [{
                    label: 'Lead Percentage',
                    data: leadSourceData.data,
                    backgroundColor: leadSourceData.colors,
                    borderRadius: 8
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
                            label: function(context) {
                                return context.label + ': ' + context.parsed.y + '%';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
    }
}

// ========================================
// CAMPUS SWITCHING
// ========================================

function switchCampus(button, campus) {
    currentCampus = campus;
    
    // Update active tab
    document.querySelectorAll('.campus-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    button.classList.add('active');
    
    // Update stats
    const data = campusData[campus];
    updateStats(data);
    
    console.log('Switched to campus:', campus);
}

function updateStats(data) {
    const elements = {
        totalLeads: document.getElementById('totalLeads'),
        newLeads: document.getElementById('newLeads'),
        followups: document.getElementById('followups'),
        converted: document.getElementById('converted'),
        dropped: document.getElementById('dropped'),
        activeTeam: document.getElementById('activeTeam')
    };
    
    Object.keys(elements).forEach(key => {
        if (elements[key]) {
            animateValue(elements[key], parseInt(elements[key].textContent.replace(',', '')), data[key], 500);
        }
    });
}

function animateValue(element, start, end, duration) {
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
            current = end;
            clearInterval(timer);
        }
        element.textContent = Math.round(current).toLocaleString();
    }, 16);
}

// ========================================
// LIVE UPDATES
// ========================================

function startLiveUpdates() {
    setInterval(() => {
        const totalLeadsEl = document.getElementById('totalLeads');
        if (!totalLeadsEl) return;
        
        const currentValue = parseInt(totalLeadsEl.textContent.replace(',', ''));
        const change = Math.floor(Math.random() * 3) - 1; // -1, 0, or 1
        const newValue = currentValue + change;
        
        totalLeadsEl.textContent = newValue.toLocaleString();
        
        // Add brief highlight effect
        totalLeadsEl.style.color = 'var(--primary-color)';
        totalLeadsEl.style.transition = 'color 0.3s';
        setTimeout(() => {
            totalLeadsEl.style.color = 'var(--text-dark)';
        }, 300);
    }, 5000); // Update every 5 seconds
}

// ========================================
// UI INTERACTIONS
// ========================================

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.classList.toggle('show');
    }
}

function showDatePicker() {
    alert('Date picker would open here.\n\nCurrent range: Last 30 Days\n\nOptions:\n- Today\n- Last 7 Days\n- Last 30 Days\n- Last 90 Days\n- Custom Range');
}

function showNotifications() {
    alert('Notifications panel would open here.\n\nYou have:\n• 3 new lead assignments\n• 2 pending approvals\n• 1 system update');
}

function showMessages() {
    alert('Messages panel would open here.\n\nRecent messages:\n• Team chat: 2 unread\n• Admin announcements: 1 new');
}

function addNewLead() {
    const leadName = prompt('Enter lead name:');
    if (leadName && leadName.trim()) {
        alert(`New lead "${leadName}" would be added to the system.\n\nNext steps:\n1. Assign to telecaller\n2. Set follow-up date\n3. Add course interest`);
        
        // Increment new leads counter
        const newLeadsEl = document.getElementById('newLeads');
        if (newLeadsEl) {
            const current = parseInt(newLeadsEl.textContent);
            newLeadsEl.textContent = current + 1;
        }
    }
}

// ========================================
// EVENT LISTENERS
// ========================================

function setupEventListeners() {
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            console.log('Searching for:', searchTerm);
            // Implement search logic here
            // You can filter tables, alerts, etc.
        });
    }
    
    // Close sidebar on outside click (mobile)
    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebar');
        const menuBtn = document.querySelector('.mobile-menu-btn');
        
        if (window.innerWidth <= 991 && sidebar) {
            if (!sidebar.contains(e.target) && !menuBtn?.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        }
    });
    
    // Responsive handling
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        if (window.innerWidth > 991 && sidebar) {
            sidebar.classList.remove('show');
        }
    });
    
    // Stat card clicks
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('click', function() {
            const label = this.querySelector('.stat-label')?.textContent || 'Unknown';
            console.log('Clicked stat card:', label);
            // You can navigate to detailed view here
        });
    });
}

// ========================================
// UTILITY FUNCTIONS
// ========================================

function formatNumber(num) {
    return num.toLocaleString();
}

function getRandomChange(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

function updateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: true 
    });
    
    // Update any time displays
    document.querySelectorAll('.current-time').forEach(el => {
        el.textContent = timeString;
    });
}

// Update time every minute
setInterval(updateTime, 60000);

// ========================================
// EXPORT FOR OTHER SCRIPTS
// ========================================

// Make functions available globally if needed
window.dashboardFunctions = {
    toggleSidebar,
    switchCampus,
    switchChartType,
    showDatePicker,
    showNotifications,
    showMessages,
    addNewLead,
    reassignAlert,
    callNow,
    clearAllAlerts,
    updateStats,
    initializeChart,
    initializeStaffTable,
    initializeAlerts
};

console.log('Dashboard functions loaded and ready!');