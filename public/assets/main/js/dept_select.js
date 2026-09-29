/**
 * Department & Building Selection Filtering Logic
 * File: assets/js/dept_select.js
 */
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('deptSearch');
    const filterBtns = document.querySelectorAll('.btn-filter');
    const deptItems = document.querySelectorAll('.dept-item');
    const noResults = document.getElementById('noResults');

    if (deptItems.length === 0) return;

    let currentBuildingFilter = 'all';

    function filterDepartments() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        let visibleCount = 0;

        deptItems.forEach(item => {
            const building = item.dataset.building || '';
            const name = (item.dataset.name || '').toLowerCase();
            const code = (item.dataset.code || '').toLowerCase();

            // Check building match and text query match
            const matchesBuilding = (currentBuildingFilter === 'all' || building === currentBuildingFilter);
            const matchesSearch = name.includes(query) || code.includes(query);

            if (matchesBuilding && matchesSearch) {
                item.classList.remove('d-none');
                visibleCount++;
            } else {
                item.classList.add('d-none');
            }
        });

        if (noResults) {
            noResults.classList.toggle('d-none', visibleCount > 0);
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterDepartments);
    }

    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentBuildingFilter = this.dataset.filter || 'all';
            filterDepartments();
        });
    });
});