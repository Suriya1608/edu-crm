/* ========================================
   LEAD MANAGEMENT - JAVASCRIPT
   ======================================== */

// ========================================
// DUMMY DATA
// ========================================

let leadsData = [
    {
        id: 'LD-9281',
        name: 'Arjun Jaiswal',
        initials: 'AJ',
        avatarColor: 'blue',
        phone: '+91 98765 43210',
        email: 'arjun.j@email.com',
        source: 'Instagram',
        course: 'B.Tech Computer Science',
        assignedTo: 'Rahul Sharma',
        status: 'follow-up',
        followupDate: new Date(Date.now() + 2 * 60 * 60 * 1000),
        createdDate: new Date('2023-10-20'),
        notes: 'Interested in AI/ML specialization'
    },
    {
        id: 'LD-9282',
        name: 'Sara Miller',
        initials: 'SM',
        avatarColor: 'green',
        phone: '+44 20 7946 0958',
        email: 'sara.m@email.com',
        source: 'Website',
        course: 'MBA Global Management',
        assignedTo: 'Priya Verma',
        status: 'interested',
        followupDate: new Date(Date.now() + 1 * 24 * 60 * 60 * 1000),
        createdDate: new Date('2023-10-21'),
        notes: 'Requested brochure'
    },
    {
        id: 'LD-9283',
        name: 'David Khan',
        initials: 'DK',
        avatarColor: 'purple',
        phone: '+91 76543 21098',
        email: 'david.k@email.com',
        source: 'Walk-in',
        course: 'Ph.D Research',
        assignedTo: 'Rahul Sharma',
        status: 'new',
        followupDate: null,
        createdDate: new Date('2023-10-21'),
        notes: ''
    }
];

// ========================================
// STATE
// ========================================

let currentPage = 1;
let perPage = 25;
let filteredLeads = [...leadsData];
let selectedLeads = new Set();

let filters = {
    search: '',
    dateRange: '30',
    source: 'all',
    course: 'all',
    telecaller: 'all'
};

// ========================================
// INITIALIZATION
// ========================================

document.addEventListener('DOMContentLoaded', function () {
    renderLeadsTable();
    updatePagination();
    updateStats();
});

// ========================================
// REDIRECT FUNCTION
// ========================================

function goToLeadDetails(leadId) {
    window.location.href = `leadDetails.html?id=${leadId}`;
}

// ========================================
// RENDER TABLE
// ========================================

function renderLeadsTable() {

    const tbody = document.getElementById('leadsTableBody');
    if (!tbody) return;

    applyFiltersToData();

    const startIndex = (currentPage - 1) * perPage;
    const endIndex = startIndex + perPage;
    const pageLeads = filteredLeads.slice(startIndex, endIndex);

    if (pageLeads.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="text-center py-5">
                    No leads found
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = pageLeads.map(lead => `
        <tr class="${selectedLeads.has(lead.id) ? 'selected' : ''}">
            <td class="text-center">
                <input type="checkbox"
                    class="form-check-input"
                    ${selectedLeads.has(lead.id) ? 'checked' : ''}
                    onchange="toggleLeadSelection('${lead.id}', this.checked)">
            </td>

            <td>
                <span class="lead-id">#${lead.id}</span>
            </td>

            <td>
                <div class="lead-name-cell lead-clickable"
                     onclick="goToLeadDetails('${lead.id}')">
                    <div class="lead-avatar ${lead.avatarColor}">
                        ${lead.initials}
                    </div>
                    <span class="lead-name">${lead.name}</span>
                </div>
            </td>

            <td>${lead.phone}</td>
            <td>${lead.source}</td>
            <td>${lead.course}</td>
            <td>${lead.assignedTo}</td>

            <td>
                <span class="status-badge ${lead.status}">
                    ${formatStatus(lead.status)}
                </span>
            </td>

            <td>
                ${formatFollowup(lead.followupDate)}
            </td>

            <td>
                ${formatDate(lead.createdDate)}
            </td>
        </tr>
    `).join('');
}

// ========================================
// FILTERING
// ========================================

function applyFiltersToData() {

    filteredLeads = leadsData.filter(lead => {

        if (filters.search) {
            const search = filters.search.toLowerCase();
            const match =
                lead.name.toLowerCase().includes(search) ||
                lead.id.toLowerCase().includes(search) ||
                lead.phone.includes(search);

            if (!match) return false;
        }

        if (filters.source !== 'all' &&
            lead.source.toLowerCase() !== filters.source) {
            return false;
        }

        return true;
    });
}

// ========================================
// PAGINATION
// ========================================

function updatePagination() {

    const totalPages = Math.ceil(filteredLeads.length / perPage);
    const pagination = document.getElementById('pagination');
    if (!pagination) return;

    let html = '';

    for (let i = 1; i <= totalPages; i++) {
        html += `
            <li class="page-item ${currentPage === i ? 'active' : ''}">
                <a class="page-link"
                   href="#"
                   onclick="changePage(${i}); return false;">
                   ${i}
                </a>
            </li>
        `;
    }

    pagination.innerHTML = html;

    const showingFrom = document.getElementById('showingFrom');
    const showingTo = document.getElementById('showingTo');
    const total = document.getElementById('totalLeads');

    if (showingFrom)
        showingFrom.textContent = (currentPage - 1) * perPage + 1;

    if (showingTo)
        showingTo.textContent = Math.min(currentPage * perPage, filteredLeads.length);

    if (total)
        total.textContent = filteredLeads.length;
}

function changePage(page) {
    currentPage = page;
    renderLeadsTable();
    updatePagination();
}

// ========================================
// SELECTION
// ========================================

function toggleLeadSelection(id, checked) {
    if (checked) selectedLeads.add(id);
    else selectedLeads.delete(id);
}

// ========================================
// HELPERS
// ========================================

function formatStatus(status) {
    const map = {
        'new': 'New',
        'follow-up': 'Follow-up',
        'interested': 'Interested',
        'not-interested': 'Not Interested',
        'enrolled': 'Enrolled'
    };
    return map[status] || status;
}

function formatDate(date) {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString();
}

function formatFollowup(date) {
    if (!date) return 'Not Scheduled';
    return new Date(date).toLocaleDateString();
}

function updateStats() {
    const el = document.getElementById('totalLeadsCount');
    if (el) el.textContent = leadsData.length;
}
