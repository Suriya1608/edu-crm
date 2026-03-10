/* ========================================
   LEAD DETAIL PAGE - JAVASCRIPT
   ======================================== */

// ========================================
// DUMMY DATA
// ========================================

const leadData = {
    id: 'LD-89231',
    name: 'Aditya Sharma',
    photo: 'https://i.pravatar.cc/150?img=33',
    priority: 'hot-lead',
    phone: '+91 98765 43210',
    email: 'aditya.sharma@email.com',
    course: 'B.Tech Computer Science',
    campus: 'North Campus, Delhi',
    assignee: 'Rahul Verma',
    source: 'Facebook Ads - Campaign Q4',
    percentage12th: '92.4%',
    entranceScore: '880 / 1000',
    status: 'follow-up',
    createdDate: new Date('2023-10-20')
};

const timelineData = [
    {
        type: 'status',
        icon: 'flag',
        title: 'Lead Status Updated',
        time: new Date(Date.now() - 2 * 60 * 60 * 1000), // 2 hours ago
        description: 'Status changed from <span class="status-tag old">New</span> to <span class="status-tag new">Follow-up Required</span> by Rahul Verma.',
        category: 'status'
    },
    {
        type: 'call',
        icon: 'call_made',
        title: 'Outbound Call',
        time: new Date(Date.now() - 5 * 60 * 60 * 1000), // 5 hours ago
        category: 'calls',
        detail: {
            outcome: 'Answered',
            duration: '4m 32s',
            note: 'Spoke with the student about the Computer Science curriculum. He is very interested but wants to discuss financial aid options with his father. Scheduled a callback.'
        }
    },
    {
        type: 'whatsapp',
        icon: 'chat_bubble',
        title: 'WhatsApp Message Sent',
        time: new Date(Date.now() - 1 * 24 * 60 * 60 * 1000), // Yesterday
        category: 'messages',
        detail: {
            message: 'Hi Aditya, as discussed, here is the brochure for B.Tech CS North Campus. Let me know if you have any questions!',
            attachment: 'BTech_CS_Brochure_2024.pdf'
        }
    },
    {
        type: 'sms',
        icon: 'sms',
        title: 'Automated SMS Sent',
        time: new Date('2023-10-24T11:20:00'),
        category: 'messages',
        description: 'Template: <span class="fw-semibold">Welcome Greeting</span><br>"Thank you for showing interest in Global University. Our counselor will contact you shortly."'
    },
    {
        type: 'note',
        icon: 'sticky_note_2',
        title: 'Note Added',
        time: new Date('2023-10-23T14:00:00'),
        description: 'Lead mentioned they are also looking at South Campus but North is priority due to proximity to home.',
        category: 'notes'
    },
    {
        type: 'email',
        icon: 'mail',
        title: 'Email Sent',
        time: new Date('2023-10-22T10:15:00'),
        category: 'messages',
        description: 'Sent course brochure and fee structure document via email.'
    },
    {
        type: 'call',
        icon: 'call_missed',
        title: 'Missed Call',
        time: new Date('2023-10-21T16:30:00'),
        category: 'calls',
        description: 'Call attempt made but no answer. Left voicemail.'
    }
];

let currentFilter = 'all';

// ========================================
// INITIALIZATION
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('Lead Detail page initializing...');
    loadLeadData();
    renderTimeline();
    console.log('Lead Detail page initialized!');
});

// ========================================
// LOAD LEAD DATA
// ========================================

function loadLeadData() {
    // Update profile information
    document.getElementById('leadName').textContent = leadData.name;
    document.getElementById('leadId').textContent = `ID: #${leadData.id}`;
    document.getElementById('leadPhoto').src = leadData.photo;
    document.getElementById('leadPhone').textContent = leadData.phone;
    document.getElementById('leadEmail').textContent = leadData.email;
    document.getElementById('leadCourse').textContent = leadData.course;
    document.getElementById('leadCampus').textContent = leadData.campus;
    document.getElementById('leadAssignee').textContent = leadData.assignee;
    document.getElementById('leadSource').textContent = leadData.source;
    document.getElementById('percentage12th').textContent = leadData.percentage12th;
    document.getElementById('entranceScore').textContent = leadData.entranceScore;
    
    // Update priority badge
    const priorityBadge = document.getElementById('leadPriority');
    const priorityText = leadData.priority === 'hot-lead' ? 'Hot Lead' : 
                         leadData.priority === 'warm-lead' ? 'Warm Lead' : 'Cold Lead';
    priorityBadge.textContent = priorityText;
    priorityBadge.className = `status-badge ${leadData.priority}`;
}

// ========================================
// TIMELINE RENDERING
// ========================================

function renderTimeline() {
    const container = document.getElementById('timelineContent');
    if (!container) return;
    
    // Filter timeline items
    const filteredItems = currentFilter === 'all' ? 
        timelineData : 
        timelineData.filter(item => item.category === currentFilter);
    
    if (filteredItems.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <span class="material-icons" style="font-size: 48px; color: var(--text-muted); opacity: 0.3;">search_off</span>
                <p class="text-muted mt-3">No activities found for this filter</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = filteredItems.map(item => {
        let detailHTML = '';
        
        if (item.detail) {
            if (item.type === 'call') {
                detailHTML = `
                    <div class="timeline-detail-box mt-3">
                        <div class="timeline-meta">
                            <span class="meta-item">Outcome: ${item.detail.outcome}</span>
                            <span class="meta-item">Duration: ${item.detail.duration}</span>
                        </div>
                        <p class="timeline-quote">"${item.detail.note}"</p>
                    </div>
                `;
            } else if (item.type === 'whatsapp') {
                detailHTML = `
                    <div class="timeline-detail-box whatsapp mt-3">
                        <p class="timeline-quote">"${item.detail.message}"</p>
                        ${item.detail.attachment ? `
                            <div class="timeline-attachment">
                                <span class="material-icons">picture_as_pdf</span>
                                <span>${item.detail.attachment}</span>
                            </div>
                        ` : ''}
                    </div>
                `;
            }
        }
        
        return `
            <div class="timeline-item">
                <div class="timeline-icon ${item.type}">
                    <span class="material-icons">${item.icon}</span>
                </div>
                <div class="timeline-body">
                    <div class="timeline-header-info">
                        <h4 class="timeline-title">${item.title}</h4>
                        <span class="timeline-time">${formatTimelineDate(item.time)}</span>
                    </div>
                    ${item.description ? `<p class="timeline-description">${item.description}</p>` : ''}
                    ${detailHTML}
                </div>
            </div>
        `;
    }).join('');
}

function formatTimelineDate(date) {
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 60) {
        return `${diffMins} minutes ago`;
    } else if (diffHours < 24) {
        return `${diffHours} hours ago`;
    } else if (diffDays === 1) {
        return 'Yesterday, ' + date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    } else if (diffDays < 7) {
        return `${diffDays} days ago`;
    } else {
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ', ' + 
               date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
}

function filterTimeline(filter) {
    currentFilter = filter;
    
    // Update active button
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Re-render timeline
    renderTimeline();
}

// ========================================
// ACTION FUNCTIONS
// ========================================

function callNow() {
    showNotification('Initiating Call', `Calling ${leadData.name} at ${leadData.phone}...`, 'info');
    
    // Simulate call
    setTimeout(() => {
        // Add to timeline
        timelineData.unshift({
            type: 'call',
            icon: 'call_made',
            title: 'Outbound Call',
            time: new Date(),
            category: 'calls',
            detail: {
                outcome: 'In Progress',
                duration: 'Ongoing',
                note: 'Call initiated from system.'
            }
        });
        
        renderTimeline();
        showNotification('Call Connected', 'Call in progress...', 'success');
    }, 2000);
}

function callLead() {
    callNow();
}

function sendWhatsApp() {
    showNotification('Opening WhatsApp', `Opening chat with ${leadData.name}...`, 'info');
    
    setTimeout(() => {
        const message = prompt('Enter message to send via WhatsApp:');
        if (message && message.trim()) {
            // Add to timeline
            timelineData.unshift({
                type: 'whatsapp',
                icon: 'chat_bubble',
                title: 'WhatsApp Message Sent',
                time: new Date(),
                category: 'messages',
                detail: {
                    message: message,
                    attachment: null
                }
            });
            
            renderTimeline();
            showNotification('Message Sent', 'WhatsApp message delivered successfully!', 'success');
        }
    }, 1000);
}

function sendSMS() {
    const message = prompt('Enter SMS message:');
    
    if (message && message.trim()) {
        // Add to timeline
        timelineData.unshift({
            type: 'sms',
            icon: 'sms',
            title: 'SMS Sent',
            time: new Date(),
            category: 'messages',
            description: `"${message}"`
        });
        
        renderTimeline();
        showNotification('SMS Sent', 'Message delivered successfully!', 'success');
    }
}

function scheduleFollowup() {
    const modal = new bootstrap.Modal(document.getElementById('followupModal'));
    
    // Set default date time to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(10, 0, 0, 0);
    
    const dateTimeInput = document.getElementById('followupDateTime');
    dateTimeInput.value = tomorrow.toISOString().slice(0, 16);
    
    modal.show();
}

function confirmFollowup() {
    const dateTime = document.getElementById('followupDateTime').value;
    const notes = document.getElementById('followupNotes').value;
    
    if (!dateTime) {
        alert('Please select a date and time');
        return;
    }
    
    const followupDate = new Date(dateTime);
    
    // Add to timeline
    timelineData.unshift({
        type: 'note',
        icon: 'calendar_today',
        title: 'Follow-up Scheduled',
        time: new Date(),
        category: 'notes',
        description: `Follow-up scheduled for ${followupDate.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
        })}. ${notes ? 'Notes: ' + notes : ''}`
    });
    
    renderTimeline();
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('followupModal'));
    modal.hide();
    
    showNotification('Follow-up Scheduled', 'Reminder has been set successfully!', 'success');
}

function changeStatus() {
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    document.getElementById('newStatus').value = leadData.status;
    modal.show();
}

function confirmStatusChange() {
    const newStatus = document.getElementById('newStatus').value;
    const oldStatus = leadData.status;
    
    if (newStatus === oldStatus) {
        alert('Please select a different status');
        return;
    }
    
    const statusNames = {
        'new': 'New',
        'follow-up': 'Follow-up Required',
        'interested': 'Interested',
        'not-interested': 'Not Interested',
        'enrolled': 'Enrolled'
    };
    
    // Update lead status
    leadData.status = newStatus;
    
    // Add to timeline
    timelineData.unshift({
        type: 'status',
        icon: 'flag',
        title: 'Lead Status Updated',
        time: new Date(),
        description: `Status changed from <span class="status-tag old">${statusNames[oldStatus]}</span> to <span class="status-tag new">${statusNames[newStatus]}</span> by you.`,
        category: 'status'
    });
    
    renderTimeline();
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('statusModal'));
    modal.hide();
    
    showNotification('Status Updated', `Lead status changed to ${statusNames[newStatus]}`, 'success');
}

function addNote() {
    const noteInput = document.getElementById('noteInput');
    const noteText = noteInput.value.trim();
    
    if (!noteText) {
        alert('Please enter a note');
        return;
    }
    
    // Add to timeline
    timelineData.unshift({
        type: 'note',
        icon: 'sticky_note_2',
        title: 'Note Added',
        time: new Date(),
        description: noteText,
        category: 'notes'
    });
    
    // Clear input
    noteInput.value = '';
    
    renderTimeline();
    showNotification('Note Added', 'Your note has been saved successfully!', 'success');
}

// ========================================
// UTILITY FUNCTIONS
// ========================================

function printProfile() {
    window.print();
}

function deleteLead() {
    if (confirm(`Are you sure you want to delete lead ${leadData.name}? This action cannot be undone.`)) {
        showNotification('Lead Deleted', 'Lead has been permanently deleted', 'success');
        
        setTimeout(() => {
            window.location.href = 'leads.html';
        }, 1500);
    }
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
    
    const iconMap = {
        success: 'check_circle',
        error: 'error',
        info: 'info'
    };
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
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
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

// ========================================
// EXPORT FUNCTIONS
// ========================================

window.leadDetailFunctions = {
    callNow,
    callLead,
    sendWhatsApp,
    sendSMS,
    scheduleFollowup,
    confirmFollowup,
    changeStatus,
    confirmStatusChange,
    addNote,
    filterTimeline,
    printProfile,
    deleteLead
};

console.log('Lead Detail functions loaded!');