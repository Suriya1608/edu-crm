/* ========================================
   SOCIAL MEDIA INTEGRATION - JAVASCRIPT
   ======================================== */

// ========================================
// DUMMY DATA
// ========================================

const platformsData = {
    facebook: {
        name: 'Facebook Ads',
        connected: true,
        lastSync: new Date(Date.now() - 12 * 60 * 1000), // 12 minutes ago
        totalLeads: 8420
    },
    instagram: {
        name: 'Instagram',
        connected: false,
        lastSync: null,
        totalLeads: 0
    },
    google: {
        name: 'Google Ads',
        connected: false,
        lastSync: null,
        totalLeads: 0
    }
};

const facebookPagesData = {
    'skyline-global': {
        name: 'Skyline University Global',
        forms: {
            'fall-2024': {
                name: 'Fall 2024 Enrollment Inquiry (Form ID: 48201)',
                fields: [
                    { fbField: 'full_name', type: 'STRING', crmField: 'Lead Name' },
                    { fbField: 'email_address', type: 'EMAIL', crmField: 'Email' },
                    { fbField: 'phone_number', type: 'PHONE', crmField: 'Mobile Number' },
                    { fbField: 'interested_course', type: 'STRING', crmField: 'Course Interest' }
                ]
            },
            'mba-brochure': {
                name: 'MBA Brochure Request 2024',
                fields: [
                    { fbField: 'full_name', type: 'STRING', crmField: 'Lead Name' },
                    { fbField: 'email_address', type: 'EMAIL', crmField: 'Email' },
                    { fbField: 'company_name', type: 'STRING', crmField: 'Company' },
                    { fbField: 'job_title', type: 'STRING', crmField: 'Job Title' }
                ]
            },
            'campus-visit': {
                name: 'Campus Visit Registration',
                fields: [
                    { fbField: 'full_name', type: 'STRING', crmField: 'Lead Name' },
                    { fbField: 'email_address', type: 'EMAIL', crmField: 'Email' },
                    { fbField: 'phone_number', type: 'PHONE', crmField: 'Mobile Number' },
                    { fbField: 'preferred_date', type: 'DATE', crmField: 'Visit Date' }
                ]
            }
        }
    },
    'skyline-executive': {
        name: 'Skyline Executive Education',
        forms: {
            'fall-2024': {
                name: 'Executive MBA Inquiry',
                fields: [
                    { fbField: 'full_name', type: 'STRING', crmField: 'Lead Name' },
                    { fbField: 'email_address', type: 'EMAIL', crmField: 'Email' },
                    { fbField: 'work_experience', type: 'NUMBER', crmField: 'Experience Years' }
                ]
            }
        }
    },
    'horizon-tech': {
        name: 'Horizon Technical Institute',
        forms: {
            'fall-2024': {
                name: 'Technical Program Inquiry',
                fields: [
                    { fbField: 'full_name', type: 'STRING', crmField: 'Lead Name' },
                    { fbField: 'email_address', type: 'EMAIL', crmField: 'Email' },
                    { fbField: 'technical_interest', type: 'STRING', crmField: 'Technical Track' }
                ]
            }
        }
    }
};

const crmFieldOptions = {
    'STRING': ['Lead Name', 'Contact Person', 'Parent Name', 'Company', 'Job Title', 'Address'],
    'EMAIL': ['Email', 'Secondary Email', 'Work Email'],
    'PHONE': ['Mobile Number', 'Office Phone', 'Home Phone'],
    'NUMBER': ['Experience Years', 'Age', 'Batch Year'],
    'DATE': ['Visit Date', 'Follow-up Date', 'Expected Join Date']
};

// Sync statistics
let syncStats = {
    totalSynced: 12482,
    failedSyncs: 14,
    activeForms: 8,
    lastSyncMinutes: 12
};

// Settings state
let settings = {
    autoSync: true,
    realtimeWebhook: false,
    selectedPage: 'skyline-global',
    selectedForm: 'fall-2024'
};

// ========================================
// INITIALIZATION
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('Social Media Integration page initializing...');
    initializeFieldMapping();
    startLastSyncTimer();
    console.log('Social Media Integration page initialized!');
});

// ========================================
// FIELD MAPPING
// ========================================

function initializeFieldMapping() {
    updateFieldMapping();
}

function updateFieldMapping() {
    const page = settings.selectedPage;
    const form = settings.selectedForm;
    const formData = facebookPagesData[page]?.forms[form];
    
    if (!formData) {
        console.error('Form data not found');
        return;
    }
    
    const tbody = document.getElementById('fieldMappingBody');
    if (!tbody) return;
    
    tbody.innerHTML = formData.fields.map((field, index) => `
        <tr>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <span class="field-type-badge">${field.type}</span>
                    <span class="field-name">${field.fbField}</span>
                </div>
            </td>
            <td class="text-center">
                <span class="material-icons arrow-icon">arrow_forward</span>
            </td>
            <td>
                <select class="form-select form-select-sm" onchange="updateMapping(${index}, this.value)">
                    ${generateCrmFieldOptions(field.type, field.crmField)}
                </select>
            </td>
        </tr>
    `).join('');
}

function generateCrmFieldOptions(fieldType, selectedField) {
    const options = crmFieldOptions[fieldType] || crmFieldOptions['STRING'];
    return options.map(option => 
        `<option value="${option}" ${option === selectedField ? 'selected' : ''}>${option}</option>`
    ).join('');
}

function updateMapping(index, newValue) {
    const page = settings.selectedPage;
    const form = settings.selectedForm;
    
    if (facebookPagesData[page]?.forms[form]?.fields[index]) {
        facebookPagesData[page].forms[form].fields[index].crmField = newValue;
        showNotification('Mapping Updated', `Field mapping updated to ${newValue}`, 'success');
    }
}

// ========================================
// PLATFORM MANAGEMENT
// ========================================

function connectPlatform(platform) {
    showNotification(
        'Connecting Platform',
        `Initiating OAuth flow for ${platformsData[platform].name}...`,
        'info'
    );
    
    // Simulate connection process
    setTimeout(() => {
        platformsData[platform].connected = true;
        platformsData[platform].lastSync = new Date();
        
        // Update UI
        const card = document.getElementById(`${platform}Card`);
        if (card) {
            card.className = 'platform-card connected fade-in';
            const badge = card.querySelector('.status-badge');
            const button = card.querySelector('button');
            
            if (badge) {
                badge.className = 'status-badge status-connected';
                badge.textContent = 'Connected';
            }
            
            if (button) {
                button.className = 'btn btn-outline-danger btn-sm w-100 mt-3';
                button.textContent = 'Disconnect';
                button.setAttribute('onclick', `disconnectPlatform('${platform}')`);
            }
        }
        
        // Update stats
        syncStats.activeForms++;
        updateStatsDisplay();
        
        showNotification(
            'Successfully Connected',
            `${platformsData[platform].name} has been connected successfully!`,
            'success'
        );
    }, 2000);
}

function disconnectPlatform(platform) {
    if (!confirm(`Are you sure you want to disconnect ${platformsData[platform].name}? This will stop all lead syncing from this platform.`)) {
        return;
    }
    
    platformsData[platform].connected = false;
    platformsData[platform].lastSync = null;
    
    // Update UI
    const card = document.getElementById(`${platform}Card`);
    if (card) {
        card.className = 'platform-card not-connected fade-in';
        const badge = card.querySelector('.status-badge');
        const button = card.querySelector('button');
        
        if (badge) {
            badge.className = 'status-badge status-not-connected';
            badge.textContent = 'Not Linked';
        }
        
        if (button) {
            button.className = 'btn btn-primary btn-sm w-100 mt-3';
            button.textContent = 'Connect';
            button.setAttribute('onclick', `connectPlatform('${platform}')`);
        }
    }
    
    // Update stats
    if (syncStats.activeForms > 0) {
        syncStats.activeForms--;
    }
    updateStatsDisplay();
    
    showNotification(
        'Platform Disconnected',
        `${platformsData[platform].name} has been disconnected.`,
        'info'
    );
}

// ========================================
// FORM CONFIGURATION
// ========================================

function updateFormsList() {
    const pageSelect = document.getElementById('facebookPage');
    if (!pageSelect) return;
    
    settings.selectedPage = pageSelect.value;
    
    // Update form dropdown
    const formSelect = document.getElementById('leadForm');
    if (!formSelect) return;
    
    const forms = facebookPagesData[settings.selectedPage]?.forms || {};
    formSelect.innerHTML = Object.keys(forms).map(formKey => 
        `<option value="${formKey}">${forms[formKey].name}</option>`
    ).join('');
    
    settings.selectedForm = Object.keys(forms)[0] || 'fall-2024';
    updateFieldMapping();
    
    showNotification('Page Changed', `Switched to ${facebookPagesData[settings.selectedPage].name}`, 'info');
}

// ========================================
// SYNC FUNCTIONALITY
// ========================================

function syncNow() {
    const button = event.target.closest('button');
    const buttonText = document.getElementById('syncButtonText');
    
    if (!platformsData.facebook.connected) {
        showNotification('Connection Required', 'Please connect Facebook Ads first.', 'error');
        return;
    }
    
    // Disable button and show loading
    button.classList.add('btn-syncing');
    button.disabled = true;
    if (buttonText) buttonText.textContent = 'Syncing...';
    
    showNotification('Sync Started', 'Fetching leads from Facebook...', 'info');
    
    // Simulate sync process
    setTimeout(() => {
        // Simulate receiving new leads
        const newLeads = Math.floor(Math.random() * 50) + 10;
        syncStats.totalSynced += newLeads;
        syncStats.lastSyncMinutes = 0;
        
        // Random chance of failed syncs
        if (Math.random() > 0.7) {
            const failed = Math.floor(Math.random() * 3) + 1;
            syncStats.failedSyncs += failed;
        }
        
        // Update display
        updateStatsDisplay();
        
        // Reset button
        button.classList.remove('btn-syncing');
        button.disabled = false;
        if (buttonText) buttonText.textContent = 'Sync Now';
        
        showNotification(
            'Sync Complete',
            `Successfully synced ${newLeads} new leads!`,
            'success'
        );
    }, 3000);
}

function toggleAutoSync(checkbox) {
    settings.autoSync = checkbox.checked;
    const status = checkbox.checked ? 'enabled' : 'disabled';
    showNotification(
        'Auto-sync Updated',
        `Auto-sync has been ${status}`,
        'info'
    );
}

function toggleRealtimeWebhook(checkbox) {
    settings.realtimeWebhook = checkbox.checked;
    const status = checkbox.checked ? 'enabled' : 'disabled';
    showNotification(
        'Webhook Updated',
        `Real-time webhook has been ${status}`,
        'info'
    );
}

// ========================================
// STATS MANAGEMENT
// ========================================

function updateStatsDisplay() {
    const elements = {
        totalSynced: document.getElementById('totalSynced'),
        failedSyncs: document.getElementById('failedSyncs'),
        activeForms: document.getElementById('activeForms'),
        lastSync: document.getElementById('lastSync')
    };
    
    if (elements.totalSynced) {
        animateValue(elements.totalSynced, syncStats.totalSynced);
    }
    
    if (elements.failedSyncs) {
        elements.failedSyncs.textContent = syncStats.failedSyncs;
    }
    
    if (elements.activeForms) {
        elements.activeForms.textContent = String(syncStats.activeForms).padStart(2, '0');
    }
    
    if (elements.lastSync) {
        updateLastSyncDisplay();
    }
}

function animateValue(element, targetValue) {
    const currentValue = parseInt(element.textContent.replace(/,/g, '')) || 0;
    const duration = 500;
    const increment = (targetValue - currentValue) / (duration / 16);
    let current = currentValue;
    
    const timer = setInterval(() => {
        current += increment;
        if ((increment > 0 && current >= targetValue) || (increment < 0 && current <= targetValue)) {
            current = targetValue;
            clearInterval(timer);
        }
        element.textContent = Math.round(current).toLocaleString();
    }, 16);
}

function startLastSyncTimer() {
    setInterval(() => {
        if (platformsData.facebook.connected) {
            syncStats.lastSyncMinutes++;
            updateLastSyncDisplay();
        }
    }, 60000); // Update every minute
}

function updateLastSyncDisplay() {
    const element = document.getElementById('lastSync');
    if (!element) return;
    
    const minutes = syncStats.lastSyncMinutes;
    
    if (minutes === 0) {
        element.textContent = 'Just now';
    } else if (minutes === 1) {
        element.textContent = '1 min ago';
    } else if (minutes < 60) {
        element.textContent = `${minutes} mins ago`;
    } else {
        const hours = Math.floor(minutes / 60);
        element.textContent = hours === 1 ? '1 hour ago' : `${hours} hours ago`;
    }
}

// ========================================
// CUSTOM MAPPING
// ========================================

function addCustomMapping() {
    const newFieldName = prompt('Enter the Facebook form field name:');
    
    if (!newFieldName || !newFieldName.trim()) {
        return;
    }
    
    const fieldType = prompt('Select field type:\n1. STRING\n2. EMAIL\n3. PHONE\n4. NUMBER\n5. DATE\n\nEnter number (1-5):');
    const types = ['STRING', 'EMAIL', 'PHONE', 'NUMBER', 'DATE'];
    const selectedType = types[parseInt(fieldType) - 1] || 'STRING';
    
    const page = settings.selectedPage;
    const form = settings.selectedForm;
    
    if (facebookPagesData[page]?.forms[form]) {
        facebookPagesData[page].forms[form].fields.push({
            fbField: newFieldName.trim(),
            type: selectedType,
            crmField: crmFieldOptions[selectedType][0]
        });
        
        updateFieldMapping();
        showNotification('Custom Mapping Added', `Added mapping for ${newFieldName}`, 'success');
    }
}

// ========================================
// UI INTERACTIONS
// ========================================

function showSyncHistory() {
    alert('Sync History\n\n' +
          'Recent sync activities:\n\n' +
          '• 12 mins ago: 45 leads synced (Success)\n' +
          '• 42 mins ago: 32 leads synced (Success)\n' +
          '• 1 hour ago: 28 leads synced (Success)\n' +
          '• 2 hours ago: 15 leads synced (2 failed)\n' +
          '• 3 hours ago: 38 leads synced (Success)\n\n' +
          'View detailed logs in the Reports section.');
}

function saveChanges() {
    showNotification('Saving Changes', 'Updating configuration...', 'info');
    
    setTimeout(() => {
        showNotification(
            'Changes Saved',
            'All configuration changes have been saved successfully!',
            'success'
        );
        console.log('Saved settings:', settings);
        console.log('Field mappings:', facebookPagesData);
    }, 1000);
}

// ========================================
// NOTIFICATION SYSTEM
// ========================================

function showNotification(title, message, type = 'info') {
    // Remove existing notification
    const existing = document.querySelector('.notification');
    if (existing) {
        existing.remove();
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    const iconMap = {
        success: 'check_circle',
        error: 'error',
        info: 'info'
    };
    
    notification.innerHTML = `
        <span class="material-icons notification-icon">${iconMap[type]}</span>
        <div class="notification-content">
            <div class="notification-title">${title}</div>
            <div class="notification-message">${message}</div>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <span class="material-icons">close</span>
        </button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

// Add slide out animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// ========================================
// EXPORT FUNCTIONS
// ========================================

window.socialMediaFunctions = {
    connectPlatform,
    disconnectPlatform,
    updateFormsList,
    updateFieldMapping,
    syncNow,
    toggleAutoSync,
    toggleRealtimeWebhook,
    addCustomMapping,
    showSyncHistory,
    saveChanges,
    updateMapping
};

console.log('Social Media Integration functions loaded!');