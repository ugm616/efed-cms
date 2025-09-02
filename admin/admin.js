// Efed CMS Admin JavaScript

// Global state
let currentUser = null;
let csrfToken = null;
let currentSection = 'dashboard';
let currentEntityData = {};

// Initialize the application
document.addEventListener('DOMContentLoaded', async function() {
    await init();
});

async function init() {
    try {
        // Get CSRF token
        await refreshCSRFToken();
        
        // Check if user is already authenticated
        const user = await getCurrentUser();
        if (user) {
            currentUser = user;
            showAdminInterface();
            showSection('dashboard');
        } else {
            showLogin();
        }
    } catch (error) {
        console.error('Initialization error:', error);
        showLogin();
    } finally {
        hideLoading();
    }
}

// Authentication functions
async function refreshCSRFToken() {
    try {
        const response = await fetch('/api/csrf');
        const data = await response.json();
        csrfToken = data.token;
    } catch (error) {
        console.error('Failed to get CSRF token:', error);
    }
}

async function getCurrentUser() {
    try {
        const response = await fetch('/api/auth/user');
        if (response.ok) {
            return await response.json();
        }
        return null;
    } catch (error) {
        return null;
    }
}

async function login(email, password) {
    try {
        const response = await fetch('/api/auth/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: email,
                password: password,
                csrf_token: csrfToken
            })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Login failed');
        }
        
        if (data.requires_2fa) {
            show2FAForm();
            return { requires2fa: true };
        }
        
        currentUser = data.user;
        showAdminInterface();
        showSection('dashboard');
        showNotification('Login successful', 'success');
        
        return data;
    } catch (error) {
        showNotification(error.message, 'error');
        throw error;
    }
}

async function verify2FA(token) {
    try {
        const response = await fetch('/api/auth/2fa/verify', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                token: token,
                csrf_token: csrfToken
            })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || '2FA verification failed');
        }
        
        currentUser = data.user;
        showAdminInterface();
        showSection('dashboard');
        showNotification('2FA verification successful', 'success');
        
        return data;
    } catch (error) {
        showNotification(error.message, 'error');
        throw error;
    }
}

async function logout() {
    try {
        await fetch('/api/auth/logout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                csrf_token: csrfToken
            })
        });
        
        currentUser = null;
        showLogin();
        showNotification('Logged out successfully', 'success');
    } catch (error) {
        console.error('Logout error:', error);
        currentUser = null;
        showLogin();
    }
}

async function seedOwner(email, password) {
    try {
        const response = await fetch('/api/auth/seed-owner', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: email,
                password: password,
                csrf_token: csrfToken
            })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Failed to create owner');
        }
        
        showNotification('Owner created successfully. Please login.', 'success');
        document.getElementById('seed-owner-section').style.display = 'none';
        
        return data;
    } catch (error) {
        showNotification(error.message, 'error');
        throw error;
    }
}

// UI state management
function showLoading() {
    document.getElementById('loading').style.display = 'flex';
    hideAll();
}

function hideLoading() {
    document.getElementById('loading').style.display = 'none';
}

function hideAll() {
    document.getElementById('login-form').style.display = 'none';
    document.getElementById('twofa-form').style.display = 'none';
    document.getElementById('admin-interface').style.display = 'none';
}

function showLogin() {
    hideAll();
    document.getElementById('login-form').style.display = 'flex';
    
    // Check if we should show seed owner option
    checkForSeedOwner();
}

function show2FAForm() {
    hideAll();
    document.getElementById('twofa-form').style.display = 'flex';
}

function showAdminInterface() {
    hideAll();
    document.getElementById('admin-interface').style.display = 'block';
    updateUserInfo();
    loadDashboardStats();
}

async function checkForSeedOwner() {
    try {
        const response = await fetch('/api/auth/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: 'test@example.com',
                password: 'invalid',
                csrf_token: csrfToken
            })
        });
        
        const data = await response.json();
        
        // If we get a specific error about no owner existing, show seed form
        if (data.message && data.message.includes('Owner user already exists')) {
            // Owner exists, don't show seed form
        } else {
            // Check if there's an owner by trying to get users count
            document.getElementById('seed-owner-section').style.display = 'block';
        }
    } catch (error) {
        // If there's an error, assume we might need to seed
        document.getElementById('seed-owner-section').style.display = 'block';
    }
}

function updateUserInfo() {
    if (currentUser) {
        document.getElementById('user-info').textContent = 
            `${currentUser.email} (${currentUser.role_name})`;
    }
}

// Navigation
function showSection(sectionName) {
    // Update navigation
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.section === sectionName) {
            btn.classList.add('active');
        }
    });
    
    // Hide all sections
    document.querySelectorAll('.section').forEach(section => {
        section.style.display = 'none';
    });
    
    // Show selected section
    const section = document.getElementById(`${sectionName}-section`);
    if (section) {
        section.style.display = 'block';
        currentSection = sectionName;
        
        // Load section data
        if (sectionName === 'dashboard') {
            loadDashboardStats();
        } else {
            loadEntityList(sectionName);
        }
    }
}

// Dashboard functions
async function loadDashboardStats() {
    try {
        const entities = ['wrestlers', 'companies', 'events', 'matches'];
        
        for (const entity of entities) {
            try {
                const response = await fetch(`/api/${entity}?limit=1`);
                if (response.ok) {
                    const data = await response.json();
                    document.getElementById(`stat-${entity}`).textContent = 
                        data.pagination.total.toLocaleString();
                }
            } catch (error) {
                document.getElementById(`stat-${entity}`).textContent = 'Error';
            }
        }
    } catch (error) {
        console.error('Failed to load dashboard stats:', error);
    }
}

async function rebuildManifests() {
    try {
        const response = await fetch('/api/build', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                csrf_token: csrfToken
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showNotification('Manifests rebuilt successfully', 'success');
        } else {
            throw new Error(data.message || 'Failed to rebuild manifests');
        }
    } catch (error) {
        showNotification(error.message, 'error');
    }
}

// Entity management
async function loadEntityList(entityType, page = 1, search = '', orderBy = '', orderDirection = '') {
    try {
        const params = new URLSearchParams({
            page: page,
            limit: 20
        });
        
        if (search) params.append('search', search);
        if (orderBy) params.append('order_by', orderBy);
        if (orderDirection) params.append('order_direction', orderDirection);
        
        const response = await fetch(`/api/${entityType}?${params}`);
        const data = await response.json();
        
        if (response.ok) {
            renderEntityList(entityType, data);
            renderPagination(entityType, data.pagination);
        } else {
            throw new Error(data.message || 'Failed to load entities');
        }
    } catch (error) {
        showNotification(error.message, 'error');
    }
}

function renderEntityList(entityType, data) {
    const listElement = document.getElementById(`${entityType}-list`);
    
    if (data.items.length === 0) {
        listElement.innerHTML = '<p class="text-muted">No items found.</p>';
        return;
    }
    
    listElement.innerHTML = data.items.map(item => {
        return `
            <div class="entity-item">
                <div class="entity-info">
                    <div class="entity-name">${escapeHtml(item.name)}</div>
                    <div class="entity-meta">
                        <span>Slug: ${escapeHtml(item.slug)}</span>
                        ${item.active !== undefined ? `<span class="entity-badge ${item.active ? 'badge-active' : 'badge-inactive'}">${item.active ? 'Active' : 'Inactive'}</span>` : ''}
                        ${item.is_championship ? '<span class="entity-badge badge-championship">Championship</span>' : ''}
                        ${item.elo ? `<span>ELO: ${item.elo}</span>` : ''}
                        ${item.points ? `<span>Points: ${item.points}</span>` : ''}
                        ${item.date ? `<span>Date: ${item.date}</span>` : ''}
                        <span>Created: ${formatDate(item.created_at)}</span>
                    </div>
                    ${item.tags && item.tags.length > 0 ? `
                        <div class="tag-list">
                            ${item.tags.map(tag => `<span class="tag">${escapeHtml(tag.name)}</span>`).join('')}
                        </div>
                    ` : ''}
                </div>
                <div class="entity-actions">
                    <button class="btn btn-sm btn-primary" onclick="editEntity('${entityType}', ${item.id})">Edit</button>
                    ${currentUser && currentUser.role >= 4 ? `<button class="btn btn-sm btn-danger" onclick="deleteEntity('${entityType}', ${item.id}, '${escapeHtml(item.name)}')">Delete</button>` : ''}
                </div>
            </div>
        `;
    }).join('');
}

function renderPagination(entityType, pagination) {
    const paginationElement = document.getElementById(`${entityType}-pagination`);
    
    if (pagination.pages <= 1) {
        paginationElement.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // Previous button
    if (pagination.page > 1) {
        html += `<button class="pagination-btn" onclick="loadEntityList('${entityType}', ${pagination.page - 1})">Previous</button>`;
    } else {
        html += `<button class="pagination-btn disabled">Previous</button>`;
    }
    
    // Page numbers
    const startPage = Math.max(1, pagination.page - 2);
    const endPage = Math.min(pagination.pages, pagination.page + 2);
    
    if (startPage > 1) {
        html += `<button class="pagination-btn" onclick="loadEntityList('${entityType}', 1)">1</button>`;
        if (startPage > 2) {
            html += `<span class="pagination-info">...</span>`;
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        const activeClass = i === pagination.page ? 'active' : '';
        html += `<button class="pagination-btn ${activeClass}" onclick="loadEntityList('${entityType}', ${i})">${i}</button>`;
    }
    
    if (endPage < pagination.pages) {
        if (endPage < pagination.pages - 1) {
            html += `<span class="pagination-info">...</span>`;
        }
        html += `<button class="pagination-btn" onclick="loadEntityList('${entityType}', ${pagination.pages})">${pagination.pages}</button>`;
    }
    
    // Next button
    if (pagination.page < pagination.pages) {
        html += `<button class="pagination-btn" onclick="loadEntityList('${entityType}', ${pagination.page + 1})">Next</button>`;
    } else {
        html += `<button class="pagination-btn disabled">Next</button>`;
    }
    
    // Info
    html += `<span class="pagination-info">Page ${pagination.page} of ${pagination.pages} (${pagination.total} items)</span>`;
    
    paginationElement.innerHTML = html;
}

// Entity CRUD operations
function showEntityForm(entityType, entityId = null) {
    const isEdit = entityId !== null;
    const title = isEdit ? `Edit ${entityType.slice(0, -1)}` : `Add ${entityType.slice(0, -1)}`;
    
    document.getElementById('modal-title').textContent = title;
    
    // Generate form fields based on entity type
    const formFields = generateFormFields(entityType);
    document.getElementById('form-fields').innerHTML = formFields;
    
    // Load dropdown options
    loadDropdownOptions(entityType);
    
    // If editing, load existing data
    if (isEdit) {
        loadEntityData(entityType, entityId);
    }
    
    // Show modal
    document.getElementById('modal').style.display = 'flex';
    
    // Store current entity info
    currentEntityData = { type: entityType, id: entityId };
}

function generateFormFields(entityType) {
    const commonFields = `
        <div class="form-group">
            <label for="entity-name">Name *</label>
            <input type="text" id="entity-name" name="name" required>
        </div>
        <div class="form-group">
            <label for="entity-slug">Slug</label>
            <input type="text" id="entity-slug" name="slug" pattern="[a-z0-9-]+">
            <small>Leave empty to auto-generate from name</small>
        </div>
        <div class="form-group">
            <label for="entity-active">
                <input type="checkbox" id="entity-active" name="active" checked> Active
            </label>
        </div>
    `;
    
    switch (entityType) {
        case 'wrestlers':
            return commonFields + `
                <div class="form-group">
                    <label for="entity-record-wins">Record Wins</label>
                    <input type="number" id="entity-record-wins" name="record_wins" min="0" value="0">
                </div>
                <div class="form-group">
                    <label for="entity-record-losses">Record Losses</label>
                    <input type="number" id="entity-record-losses" name="record_losses" min="0" value="0">
                </div>
                <div class="form-group">
                    <label for="entity-record-draws">Record Draws</label>
                    <input type="number" id="entity-record-draws" name="record_draws" min="0" value="0">
                </div>
                <div class="form-group">
                    <label for="entity-elo">ELO Rating</label>
                    <input type="number" id="entity-elo" name="elo" value="1200">
                </div>
                <div class="form-group">
                    <label for="entity-points">Points</label>
                    <input type="number" id="entity-points" name="points" value="0">
                </div>
                <div class="form-group">
                    <label for="entity-profile-img-url">Profile Image URL</label>
                    <input type="url" id="entity-profile-img-url" name="profile_img_url">
                </div>
            `;
            
        case 'companies':
            return commonFields + `
                <div class="form-group">
                    <label for="entity-logo-url">Logo URL</label>
                    <input type="url" id="entity-logo-url" name="logo_url">
                </div>
                <div class="form-group">
                    <label for="entity-banner-url">Banner URL</label>
                    <input type="url" id="entity-banner-url" name="banner_url">
                </div>
                <div class="form-group">
                    <label for="entity-links">Links (JSON)</label>
                    <textarea id="entity-links" name="links" placeholder='{"website": "https://example.com", "twitter": "@example"}'></textarea>
                </div>
            `;
            
        case 'divisions':
            return commonFields + `
                <div class="form-group">
                    <label for="entity-eligibility">Eligibility (JSON)</label>
                    <textarea id="entity-eligibility" name="eligibility" placeholder='{"min_weight": 200, "max_weight": null, "gender": "any"}'></textarea>
                </div>
            `;
            
        case 'events':
            return commonFields + `
                <div class="form-group">
                    <label for="entity-company-id">Company *</label>
                    <select id="entity-company-id" name="company_id" required>
                        <option value="">Select a company</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="entity-date">Date *</label>
                    <input type="date" id="entity-date" name="date" required>
                </div>
                <div class="form-group">
                    <label for="entity-type">Type</label>
                    <select id="entity-type" name="type">
                        <option value="event">Event</option>
                        <option value="pay-per-view">Pay-Per-View</option>
                        <option value="tv-show">TV Show</option>
                        <option value="house-show">House Show</option>
                        <option value="special">Special</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="entity-venue">Venue</label>
                    <input type="text" id="entity-venue" name="venue">
                </div>
                <div class="form-group">
                    <label for="entity-attendance">Attendance</label>
                    <input type="number" id="entity-attendance" name="attendance" min="0">
                </div>
            `;
            
        case 'matches':
            return `
                <div class="form-group">
                    <label for="entity-slug">Slug</label>
                    <input type="text" id="entity-slug" name="slug" pattern="[a-z0-9-]+">
                    <small>Leave empty to auto-generate</small>
                </div>
                <div class="form-group">
                    <label for="entity-event-id">Event *</label>
                    <select id="entity-event-id" name="event_id" required>
                        <option value="">Select an event</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="entity-company-id">Company *</label>
                    <select id="entity-company-id" name="company_id" required>
                        <option value="">Select a company</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="entity-wrestler1-id">Wrestler 1 *</label>
                    <select id="entity-wrestler1-id" name="wrestler1_id" required>
                        <option value="">Select wrestler 1</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="entity-wrestler2-id">Wrestler 2 *</label>
                    <select id="entity-wrestler2-id" name="wrestler2_id" required>
                        <option value="">Select wrestler 2</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="entity-division-id">Division</label>
                    <select id="entity-division-id" name="division_id">
                        <option value="">Select a division</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="entity-is-championship">
                        <input type="checkbox" id="entity-is-championship" name="is_championship"> Championship Match
                    </label>
                </div>
                <div class="form-group">
                    <label for="entity-result-outcome">Result Outcome</label>
                    <select id="entity-result-outcome" name="result_outcome">
                        <option value="">Select outcome</option>
                        <option value="win">Win</option>
                        <option value="loss">Loss</option>
                        <option value="draw">Draw</option>
                        <option value="no_contest">No Contest</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="entity-result-method">Result Method</label>
                    <input type="text" id="entity-result-method" name="result_method" placeholder="e.g., Pinfall, Submission">
                </div>
                <div class="form-group">
                    <label for="entity-result-round">Result Round</label>
                    <input type="number" id="entity-result-round" name="result_round" min="1">
                </div>
                <div class="form-group">
                    <label for="entity-judges">Judges (JSON)</label>
                    <textarea id="entity-judges" name="judges" placeholder='["Judge 1", "Judge 2", "Judge 3"]'></textarea>
                </div>
            `;
            
        case 'tags':
            return `
                <div class="form-group">
                    <label for="entity-name">Name *</label>
                    <input type="text" id="entity-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="entity-slug">Slug</label>
                    <input type="text" id="entity-slug" name="slug" pattern="[a-z0-9-]+">
                    <small>Leave empty to auto-generate from name</small>
                </div>
            `;
            
        default:
            return commonFields;
    }
}

async function loadEntityData(entityType, entityId) {
    try {
        const response = await fetch(`/api/${entityType}/${entityId}`);
        const data = await response.json();
        
        if (response.ok) {
            // Load dropdown options first
            await loadDropdownOptions(entityType);
            
            // Populate form fields
            Object.keys(data).forEach(key => {
                const field = document.getElementById(`entity-${key.replace(/_/g, '-')}`);
                if (field) {
                    if (field.type === 'checkbox') {
                        field.checked = data[key];
                    } else if (field.tagName === 'TEXTAREA' && typeof data[key] === 'object') {
                        field.value = JSON.stringify(data[key], null, 2);
                    } else {
                        field.value = data[key] || '';
                    }
                }
            });
        }
    } catch (error) {
        showNotification('Failed to load entity data', 'error');
    }
}

async function saveEntity() {
    try {
        const form = document.getElementById('entity-form');
        const formData = new FormData(form);
        const data = {};
        
        // Convert FormData to object
        for (let [key, value] of formData.entries()) {
            if (key === 'active' || key === 'is_championship') {
                data[key] = form.querySelector(`[name="${key}"]`).checked;
            } else if (value !== '') {
                data[key] = value;
            }
        }
        
        // Add CSRF token
        data.csrf_token = csrfToken;
        
        const isEdit = currentEntityData.id !== null;
        const url = isEdit ? 
            `/api/${currentEntityData.type}/${currentEntityData.id}` : 
            `/api/${currentEntityData.type}`;
        const method = isEdit ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showNotification(`${currentEntityData.type.slice(0, -1)} ${isEdit ? 'updated' : 'created'} successfully`, 'success');
            closeModal();
            loadEntityList(currentEntityData.type);
        } else {
            if (result.errors) {
                showValidationErrors(result.errors);
            } else {
                throw new Error(result.message || 'Failed to save entity');
            }
        }
    } catch (error) {
        showNotification(error.message, 'error');
    }
}

async function deleteEntity(entityType, entityId, entityName) {
    if (!confirm(`Are you sure you want to delete "${entityName}"? This action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await fetch(`/api/${entityType}/${entityId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                csrf_token: csrfToken
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showNotification(`${entityType.slice(0, -1)} deleted successfully`, 'success');
            loadEntityList(entityType);
        } else {
            throw new Error(data.message || 'Failed to delete entity');
        }
    } catch (error) {
        showNotification(error.message, 'error');
    }
}

function editEntity(entityType, entityId) {
    showEntityForm(entityType, entityId);
}

// Search and filtering
function searchEntities(entityType) {
    const searchInput = document.getElementById(`${entityType}-search`);
    const search = searchInput.value.trim();
    
    // Debounce search
    clearTimeout(searchEntities.timeout);
    searchEntities.timeout = setTimeout(() => {
        loadEntityList(entityType, 1, search);
    }, 500);
}

function sortEntities(entityType) {
    const sortSelect = document.getElementById(`${entityType}-sort`);
    const [orderBy, orderDirection] = sortSelect.value.split('-');
    
    loadEntityList(entityType, 1, '', orderBy, orderDirection);
}

// 2FA Management
async function showTwoFASetup() {
    try {
        const response = await fetch('/api/auth/2fa/setup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                csrf_token: csrfToken
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            const modalContent = `
                <div class="qr-container">
                    <h4>Scan QR Code</h4>
                    <img src="${data.qr_url}" alt="QR Code">
                    <p>Or enter this code manually:</p>
                    <div class="manual-entry">${data.secret}</div>
                </div>
                <form id="enable-2fa-form">
                    <div class="form-group">
                        <label for="twofa-verification-token">Enter verification code:</label>
                        <input type="text" id="twofa-verification-token" pattern="[0-9]{6}" maxlength="6" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Enable 2FA</button>
                        <button type="button" class="btn btn-secondary" onclick="closeTwoFASetup()">Cancel</button>
                    </div>
                </form>
            `;
            
            document.getElementById('twofa-setup-content').innerHTML = modalContent;
            document.getElementById('twofa-setup-modal').style.display = 'flex';
            
            // Add form submit handler
            document.getElementById('enable-2fa-form').addEventListener('submit', enable2FA);
        } else {
            throw new Error(data.message || 'Failed to setup 2FA');
        }
    } catch (error) {
        showNotification(error.message, 'error');
    }
}

async function enable2FA(event) {
    event.preventDefault();
    
    try {
        const token = document.getElementById('twofa-verification-token').value;
        
        const response = await fetch('/api/auth/2fa/enable', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                token: token,
                csrf_token: csrfToken
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showNotification('2FA enabled successfully', 'success');
            closeTwoFASetup();
            // Update user info to reflect 2FA status
            currentUser.has_2fa = true;
        } else {
            throw new Error(data.message || 'Failed to enable 2FA');
        }
    } catch (error) {
        showNotification(error.message, 'error');
    }
}

function closeTwoFASetup() {
    document.getElementById('twofa-setup-modal').style.display = 'none';
}

// Modal management
function closeModal() {
    document.getElementById('modal').style.display = 'none';
    currentEntityData = {};
}

// Utility functions
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    document.getElementById('notifications').appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 5000);
}

function showValidationErrors(errors) {
    let message = 'Validation errors:\n';
    Object.keys(errors).forEach(field => {
        message += `- ${field}: ${errors[field]}\n`;
    });
    showNotification(message, 'error');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString();
}

// Event listeners
document.addEventListener('click', function(e) {
    // Navigation
    if (e.target.classList.contains('nav-btn')) {
        const section = e.target.dataset.section;
        showSection(section);
    }
    
    // Close modal when clicking outside
    if (e.target.classList.contains('modal')) {
        closeModal();
        closeTwoFASetup();
    }
});

// Form submissions
document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    
    try {
        await login(email, password);
    } catch (error) {
        // Error already shown in login function
    }
});

document.getElementById('twofaForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const token = document.getElementById('twofa-token').value;
    
    try {
        await verify2FA(token);
    } catch (error) {
        // Error already shown in verify2FA function
    }
});

document.getElementById('seedOwnerForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const email = document.getElementById('owner-email').value;
    const password = document.getElementById('owner-password').value;
    
    try {
        await seedOwner(email, password);
    } catch (error) {
        // Error already shown in seedOwner function
    }
});

document.getElementById('entity-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    await saveEntity();
});

// Auto-generate slug from name
document.addEventListener('input', function(e) {
    if (e.target.id === 'entity-name') {
        const slugField = document.getElementById('entity-slug');
        if (slugField && !slugField.value) {
            const slug = e.target.value
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '');
            slugField.value = slug;
        }
    }
});

// Load dropdown options for forms
async function loadDropdownOptions(entityType) {
    try {
        // Load companies for events and matches
        const companySelect = document.getElementById('entity-company-id');
        if (companySelect) {
            const companiesResponse = await fetch('/api/companies?limit=100');
            if (companiesResponse.ok) {
                const companiesData = await companiesResponse.json();
                companySelect.innerHTML = '<option value="">Select a company</option>';
                companiesData.items.forEach(company => {
                    companySelect.innerHTML += `<option value="${company.id}">${escapeHtml(company.name)}</option>`;
                });
            }
        }
        
        // Load events for matches
        const eventSelect = document.getElementById('entity-event-id');
        if (eventSelect) {
            const eventsResponse = await fetch('/api/events?limit=100');
            if (eventsResponse.ok) {
                const eventsData = await eventsResponse.json();
                eventSelect.innerHTML = '<option value="">Select an event</option>';
                eventsData.items.forEach(event => {
                    eventSelect.innerHTML += `<option value="${event.id}">${escapeHtml(event.name)}</option>`;
                });
            }
        }
        
        // Load wrestlers for matches
        const wrestler1Select = document.getElementById('entity-wrestler1-id');
        const wrestler2Select = document.getElementById('entity-wrestler2-id');
        if (wrestler1Select || wrestler2Select) {
            const wrestlersResponse = await fetch('/api/wrestlers?limit=100');
            if (wrestlersResponse.ok) {
                const wrestlersData = await wrestlersResponse.json();
                const wrestlerOptions = '<option value="">Select a wrestler</option>' + 
                    wrestlersData.items.map(wrestler => 
                        `<option value="${wrestler.id}">${escapeHtml(wrestler.name)}</option>`
                    ).join('');
                
                if (wrestler1Select) wrestler1Select.innerHTML = wrestlerOptions;
                if (wrestler2Select) wrestler2Select.innerHTML = wrestlerOptions;
            }
        }
        
        // Load divisions for matches
        const divisionSelect = document.getElementById('entity-division-id');
        if (divisionSelect) {
            const divisionsResponse = await fetch('/api/divisions?limit=100');
            if (divisionsResponse.ok) {
                const divisionsData = await divisionsResponse.json();
                divisionSelect.innerHTML = '<option value="">Select a division</option>';
                divisionsData.items.forEach(division => {
                    divisionSelect.innerHTML += `<option value="${division.id}">${escapeHtml(division.name)}</option>`;
                });
            }
        }
    } catch (error) {
        console.error('Failed to load dropdown options:', error);
    }
}

// Refresh CSRF token periodically
setInterval(refreshCSRFToken, 5 * 60 * 1000); // Every 5 minutes