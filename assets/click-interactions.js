// Click-based interactions for better UX
document.addEventListener('DOMContentLoaded', function() {
    
    // Table row click selection
    const tableRows = document.querySelectorAll('.data-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('click', function(e) {
            // Toggle active state
            tableRows.forEach(r => r.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Hostel card click expand
    const hostelCards = document.querySelectorAll('.hostel-card');
    hostelCards.forEach(card => {
        card.addEventListener('click', function(e) {
            e.preventDefault();
            this.classList.toggle('expanded');
        });
    });

    // Stat card click expand
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.addEventListener('click', function() {
            this.classList.toggle('stat-expanded');
        });
    });

    // Payment method indicator click
    const paymentIndicators = document.querySelectorAll('.payment-method-indicator');
    paymentIndicators.forEach(indicator => {
        indicator.addEventListener('click', function() {
            paymentIndicators.forEach(ind => ind.classList.remove('selected'));
            this.classList.add('selected');
        });
    });

    // Sidebar navigation click (already implemented but ensure it works)
    const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.hasAttribute('data-page')) {
                e.preventDefault();
                sidebarLinks.forEach(l => l.classList.remove('active'));
                this.classList.add('active');
            }
        });
    });
});
