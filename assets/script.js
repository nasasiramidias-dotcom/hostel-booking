// Complete Student Dashboard JavaScript
// Handles page navigation, bookings, payments, browse hostels feature

document.addEventListener('DOMContentLoaded', function() {
    // Page Navigation
    const navLinks = document.querySelectorAll('.sidebar-nav a[data-page]');
    const pageSections = document.querySelectorAll('.page-section');

    function showPage(pageName) {
        // Hide all pages
        pageSections.forEach(section => section.classList.remove('active'));
        // Remove active from all nav links
        navLinks.forEach(link => link.classList.remove('active'));
        
        // Show selected page
        const targetPage = document.getElementById(pageName);
        if (targetPage) {
            targetPage.classList.add('active');
        }
        
        // Activate nav link
        const targetLink = document.querySelector(`a[data-page="${pageName}"]`);
        if (targetLink) {
            targetLink.classList.add('active');
        }

        // Auto-load content for specific pages
        if (pageName === 'browse-hostels') {
            loadHostels();
        } else if (pageName === 'new-booking') {
            // Reset booking form
            document.getElementById('bookingForm').reset();
            document.getElementById('roomSelect').disabled = true;
            document.getElementById('roomSelect').innerHTML = '<option value=\"\">First select a hostel...</option>';
        }
    }

    // Nav click handlers
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const page = this.getAttribute('data-page');
            showPage(page);
        });
    });

    // Set initial active page
    const initialPage = window.location.hash.replace('#', '') || 'dashboard';
    showPage(initialPage);

// Professional Browse Hostels - Enhanced Functionality
let allHostels = [];
let currentPage = 1;
let hostelsPerPage = 12;
let filteredHostels = [];
let searchTerm = '';
let filters = { price: '', location: '', rating: 0, minPrice: 0, maxPrice: 2000000 };
let currentSort = 'name';
let currentView = 'grid';

function loadHostels(initial = true) {
    const loading = document.getElementById('hostels-loading');
    const skeleton = document.getElementById('skeleton-loaders');
    const container = document.getElementById('hostels-list');
    const resultsCount = document.getElementById('resultsCount');
    
    if (initial) {
        skeleton.style.display = 'grid';
        loading.style.display = 'none';
        container.innerHTML = '';
    } else {
        loading.style.display = 'flex';
    }

    fetch(`../api/get_hostels.php?page=${currentPage}&limit=${hostelsPerPage}`)
        .then(res => {
            if (!res.ok) throw new Error(`Server error: ${res.status}`);
            return res.json();
        })
        .then(data => {
            loading.style.display = 'none';
            skeleton.style.display = 'none';
            
            if (initial) {
                allHostels = data.hostels || data;
                filteredHostels = [...allHostels];
                applyFiltersAndSort();
            } else {
                if (data.length > 0) {
                    filteredHostels = [...filteredHostels, ...data];
                    renderHostels(filteredHostels.slice(0, (currentPage * hostelsPerPage)));
                } else {
                    document.getElementById('loadMoreBtn').style.display = 'none';
                }
            }
            
            updateResultsCount();
        })
        .catch(err => {
            loading.style.display = 'none';
            skeleton.style.display = 'none';
            container.innerHTML = `<p class="error">Failed to load hostels: ${err.message}</p>`;
        });
}

function applyFiltersAndSort() {
    let results = [...allHostels];
    
    // Search filter
    if (searchTerm) {
        results = results.filter(h => 
            h.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            h.location.toLowerCase().includes(searchTerm.toLowerCase())
        );
    }
    
    // Price filter
    if (filters.price === 'low') results = results.filter(h => (h.min_price || 0) < 500000);
    if (filters.price === 'medium') results = results.filter(h => (h.min_price || 0) >= 500000 && (h.min_price || 0) <= 1000000);
    if (filters.price === 'high') results = results.filter(h => (h.min_price || 0) > 1000000);
    
    // Location filter
    if (filters.location) {
        results = results.filter(h => h.location.toLowerCase().includes(filters.location.toLowerCase()));
    }
    
    // Price range slider
    results = results.filter(h => (h.min_price || 0) >= filters.minPrice && (h.min_price || 0) <= filters.maxPrice);
    
    // Rating filter
    if (filters.rating > 0) {
        results = results.filter(h => (h.rating || 0) >= filters.rating);
    }
    
    // Sort
    results.sort((a, b) => {
        switch (currentSort) {
            case 'price': return (a.min_price || 0) - (b.min_price || 0);
            case 'available': return (b.available_rooms || 0) - (a.available_rooms || 0);
            case 'rating': return (b.rating || 0) - (a.rating || 0);
            default: return a.name.localeCompare(b.name);
        }
    });
    
    filteredHostels = results;
    renderHostels(results.slice(0, currentPage * hostelsPerPage));
    updateResultsCount();
}

function renderHostels(hostels) {
    const tbody = document.getElementById('hostels-tbody');
    
    if (hostels.length === 0) {
        document.getElementById('empty-state').style.display = 'block';
        if (tbody) tbody.innerHTML = '';
        return;
    }
    
    document.getElementById('empty-state').style.display = 'none';
    
    if (tbody) {
        tbody.innerHTML = hostels.map(hostel => `
            <tr data-hostel-id="${hostel.id}" tabindex="0" role="row" data-label="Hostel: ${escapeHtml(hostel.name)}">
                <td data-label="Hostel Name">
                    <strong>${escapeHtml(hostel.name)}</strong>
                </td>
                <td data-label="Location">${escapeHtml(hostel.location)}</td>
                <td data-label="Available Rooms" style="text-align: center;">
                    <span class="badge badge-confirmed">${hostel.available_rooms || 0} / ${hostel.total_rooms || 0}</span>
                </td>
                <td data-label="Price Range">
                    UGX ${parseFloat(hostel.min_price || 0).toLocaleString()} 
                    <small style="color: var(--text-light);">– max</small>
                </td>
                <td data-label="Rating" style="text-align: center;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 0.25rem;">
                        <span aria-label="Rating: ${(hostel.rating || 0).toFixed(1)}">${generateStars(hostel.rating || 0)}</span>
                        <span style="font-weight: 600; color: var(--warning-color); font-size: 0.875rem;">${(hostel.rating || 0).toFixed(1)}</span>
                    </div>
                </td>
                <td data-label="Status">
                    <span class="badge ${hostel.status === 'active' ? 'badge-confirmed' : 'badge-pending'}">
                        ${hostel.status === 'active' ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td data-label="Action">
                    <button class="btn btn-primary btn-sm" onclick="openRoomsModal(${hostel.id}, '${escapeHtml(hostel.name)}'); event.stopPropagation();">
                        View Rooms
                    </button>
                </td>
            </tr>
        `).join('');
        
        // Attach professional table interactions
        attachTableInteractions();
    }
}

function getPlaceholderImage() {
    const placeholders = [
        'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=400',
        'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=400',
        'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=400'
    ];
    return placeholders[Math.floor(Math.random() * placeholders.length)];
}

function generateStars(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        stars += i <= Math.floor(rating) ? '★' : (i <= rating ? '☆' : '☆');
    }
    return stars;
}

function getAmenities(hostel) {
    const amenities = ['WiFi', 'Laundry', 'Parking', 'Study Room', 'Gym'];
    return amenities.slice(0, 3).map(a => `<span class="amenity-tag">${a}</span>`).join('');
}

function updateResultsCount() {
    const count = filteredHostels.length;
    const total = allHostels.length;
    document.getElementById('resultsCount').textContent = 
        count === 0 ? 'No hostels match your criteria' :
        count === 1 ? '1 hostel found' :
        `${count} of ${total} hostels found`;
    
    const hasMore = filteredHostels.length > currentPage * hostelsPerPage;
    document.getElementById('loadMoreBtn').style.display = hasMore ? 'inline-block' : 'none';
}

function loadMoreHostels() {
    currentPage++;
    loadHostels(false);
}

function applyFilters() {
    searchTerm = document.getElementById('hostelSearch').value;
    filters.price = document.getElementById('priceFilter').value;
    filters.location = document.getElementById('locationFilter').value;
    
    currentPage = 1;
    applyFiltersAndSort();
}

function clearFilters() {
    document.getElementById('hostelSearch').value = '';
    document.getElementById('priceFilter').value = '';
    document.getElementById('locationFilter').value = '';
    document.getElementById('sortSelect').value = 'name';
    
    searchTerm = '';
    Object.keys(filters).forEach(key => filters[key] = key === 'minPrice' ? 0 : key === 'maxPrice' ? 2000000 : '');
    currentSort = 'name';
    
    currentPage = 1;
    applyFiltersAndSort();
}

function attachTableInteractions() {
    const tableRows = document.querySelectorAll('#hostels-tbody tr[role="row"]');
    
    tableRows.forEach(row => {
        // Row click - select & open modal
        row.addEventListener('click', function(e) {
            if (!e.target.closest('button')) {
                // Remove previous selection
                tableRows.forEach(r => r.classList.remove('selected'));
                // Select current row
                this.classList.add('selected');
                // Open rooms modal
                const hostelId = this.dataset.hostelId;
                const hostelName = this.querySelector('td:first-child strong').textContent;
                openRoomsModal(hostelId, hostelName);
            }
        });
        
        // Keyboard navigation
        row.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                tableRows.forEach(r => r.classList.remove('selected'));
                this.classList.add('selected');
                const hostelId = this.dataset.hostelId;
                const hostelName = this.querySelector('td:first-child strong').textContent;
                openRoomsModal(hostelId, hostelName);
            }
        });
        
        // Hover feedback
        row.addEventListener('mouseenter', function() {
            if (!this.classList.contains('selected')) {
                this.style.outline = '2px solid rgba(30, 64, 175, 0.3)';
            }
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.outline = 'none';
        });
    });
    
    // Header sorting
    document.querySelectorAll('.data-table thead th[data-sortable]').forEach(th => {
        th.addEventListener('click', function() {
            const sortKey = this.dataset.sortable;
            const currentSortDir = this.dataset.sort;
            
            // Reset all headers
            document.querySelectorAll('.data-table thead th[data-sortable]').forEach(h => {
                h.dataset.sort = '';
                h.classList.remove('sorted');
            });
            
            let sortDir = 'asc';
            if (currentSortDir === 'asc') sortDir = 'desc';
            
            this.dataset.sort = sortDir;
            this.classList.add('sorted');
            
            // Update global sort
            currentSort = sortKey + (sortDir === 'desc' ? ':desc' : '');
            applyFiltersAndSort();
        });
    });
}



// Additional table utility functions
function exportHostelsToCSV() {
    if (filteredHostels.length === 0) {
        alert('No hostels to export');
        return;
    }
    
    let csv = 'Hostel Name,Location,Available Rooms,Total Rooms,Min Price,Rating,Status\\n';
    filteredHostels.forEach(h => {
        csv += `"${h.name.replace(/"/g, '""')}","${h.location.replace(/"/g, '""')}",${h.available_rooms || 0},${h.total_rooms || 0},${h.min_price || 0},${h.rating || 0},"${h.status}"\\n`;
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `hostels_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}

let scrollObserver = null;
function initInfiniteScroll() {
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    if (loadMoreBtn && 'IntersectionObserver' in window) {
        scrollObserver = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && filteredHostels.length > currentPage * hostelsPerPage) {
                loadMoreHostels();
            }
        });
        scrollObserver.observe(loadMoreBtn);
    }
}

// Real-time hostel search
document.getElementById('hostelSearch')?.addEventListener('input', function() {
    searchTerm = this.value;
    currentPage = 1;
    loadHostels(true);
});

// Filter changes
document.getElementById('priceFilter')?.addEventListener('change', applyFilters);
document.getElementById('locationFilter')?.addEventListener('change', applyFilters);
document.getElementById('sortSelect')?.addEventListener('change', function() {
    currentSort = this.value;
    currentPage = 1;
    applyFiltersAndSort();
});

document.addEventListener('DOMContentLoaded', function() {
    initInfiniteScroll();
});

window.exportHostelsToCSV = exportHostelsToCSV;
window.loadMoreHostels = loadMoreHostels;
window.applyFilters = applyFilters;
window.clearFilters = clearFilters;

// ==================== LOGIN DEMO FILL ====================
function fillDemo(username, password) {
    const u = document.getElementById('usernameInput');
    const p = document.getElementById('passwordInput');
    if (u) u.value = username;
    if (p) p.value = password;
}

// Expose globally
window.fillDemo = fillDemo;

function openRoomsModal(hostelId, hostelName) {
    document.getElementById('roomsModalTitle').textContent = `Rooms at ${hostelName}`;
    document.getElementById('roomsModal').classList.add('active');
    loadRooms(hostelId);
}

function closeRoomsModal() {
    document.getElementById('roomsModal').classList.remove('active');
}

function loadRooms(hostelId) {
    const loading = document.getElementById('rooms-loading');
    const container = document.getElementById('rooms-list');
    const gallery = document.getElementById('roomGallery');
    
    loading.style.display = 'block';
    container.innerHTML = '';
    gallery.style.display = 'none';

    fetch(`../api/get_rooms.php?hostel_id=${hostelId}`)
        .then(res => {
            if (!res.ok) throw new Error(`Server error: ${res.status}`);
            return res.json();
        })
        .then(rooms => {
            loading.style.display = 'none';
            if (rooms.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon">🛏️</div><h3>No Available Rooms</h3><p>This hostel currently has no available rooms. Check back later or try another hostel.</p></div>';
                return;
            }

            // Render professional rooms table
            const tableHTML = `
                <div class="rooms-header">
                    <h4>Available Rooms (${rooms.length})</h4>
                    <div class="rooms-stats">
                        <span class="stat-badge">Starting from UGX ${Math.min(...rooms.map(r => parseFloat(r.price_per_semester || 0))).toLocaleString()}</span>
                    </div>
                </div>
                <div class="table-container">
                    <table class="professional-table rooms-table" role="grid" aria-label="Available rooms table">
                        <thead>
                            <tr>
                                <th scope="col" aria-sort="none">Room</th>
                                <th scope="col" aria-sort="none">Type</th>
                                <th scope="col" aria-sort="none">Beds</th>
                                <th scope="col" aria-sort="none">Price/Semester</th>
                                <th scope="col" aria-sort="none">Availability</th>
                                <th scope="col" aria-sort="none">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rooms.map((room, index) => {
                                const price = parseFloat(room.price_per_semester || 0).toLocaleString('en-UG');
                                return `
                                    <tr tabindex="0" role="row">
                                        <td data-label="Room"><strong>#${escapeHtml(room.room_number)}</strong></td>
                                        <td data-label="Type">${escapeHtml(room.room_type)}</td>
                                        <td data-label="Beds">${room.capacity || 'N/A'} beds</td>
                                        <td data-label="Price"><strong>UGX ${price}</strong></td>
                                        <td data-label="Availability"><span class="badge badge-approved">Available</span></td>
                                        <td data-label="Action">
                                            <button class="btn btn-success btn-small" onclick="bookRoom(${room.id}, ${hostelId})" aria-label="Book room ${room.room_number}">
                                                <span class="btn-icon">📝</span> Book Now
                                            </button>
                                        </td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            container.innerHTML = tableHTML;
            attachRoomsTableListeners(container);
        })
        .catch(err => {
            loading.style.display = 'none';
            container.innerHTML = `<div class="error-state"><h3>❌ Error Loading Rooms</h3><p>${err.message}</p><button class="btn btn-primary" onclick="openRoomsModal(${hostelId}, '${document.getElementById('roomsModalTitle').textContent}')">Retry</button></div>`;
        });
}

function attachRoomsTableListeners(container) {
    // Keyboard navigation for table rows
    container.querySelectorAll('tr[role="row"]').forEach((row, index) => {
        row.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const bookBtn = this.querySelector('.btn-success');
                if (bookBtn) bookBtn.click();
            }
        });
    });
    
    // Hover effects
    container.querySelectorAll('tr').forEach(row => {
        row.addEventListener('mouseenter', () => row.style.transform = 'scale(1.01)');
        row.addEventListener('mouseleave', () => row.style.transform = 'scale(1)');
    });
}

function bookRoom(roomId, hostelId) {
    closeRoomsModal();
    
    // Populate new booking form
    const hostelSelect = document.getElementById('hostelSelect');
    const roomSelect = document.getElementById('roomSelect');
    
    hostelSelect.value = hostelId;
    
    // Trigger room load
    hostelSelect.dispatchEvent(new Event('change'));
    
    // Wait a bit then select room
    setTimeout(() => {
        Array.from(roomSelect.options).forEach(option => {
            if (option.value == roomId) {
                option.selected = true;
            }
        });
        showPage('new-booking');
    }, 500);
}

// Utility function
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '<',
        '>': '>',
        '"': '"',
        "'": '&#039;'
    };
    return text.replace(/[&<>\\"']/g, function(m) { return map[m]; });
}

// Existing functionality from inline script (merged)
document.getElementById('hostelSelect')?.addEventListener('change', function() {
    const hostelId = this.value;
    const roomSelect = document.getElementById('roomSelect');
    
    if (hostelId) {
        fetch(`../api/get_rooms.php?hostel_id=${hostelId}`)
            .then(res => {
                if (!res.ok) throw new Error('Server error: ' + res.status);
                return res.json();
            })
        .then(data => {
            roomSelect.innerHTML = '<option value=\"\">Select a room...</option>';
            data.forEach(room => {
                roomSelect.innerHTML += `<option value=\"${room.id}\">${room.room_number} - ${room.room_type} (UGX ${room.price_per_semester})</option>`;
            });
            roomSelect.disabled = false;
        })
        .catch(err => {
            alert('Failed to load rooms: ' + err.message);
            roomSelect.innerHTML = '<option value=\"\">Error loading rooms</option>';
            roomSelect.disabled = true;
        });
    } else {
        roomSelect.innerHTML = '<option value=\"\">First select a hostel...</option>';
        roomSelect.disabled = true;
    }
});

}); // Close DOMContentLoaded event listener

