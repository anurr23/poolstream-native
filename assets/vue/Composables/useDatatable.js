import { ref, computed } from 'vue';

export function useDatatable(dataArrayRef, searchKeys = ['name']) {
    const searchQuery = ref('');
    const currentPage = ref(1);
    const itemsPerPage = ref(10);

    const filteredData = computed(() => {
        let items = dataArrayRef.value || [];
        if (!searchQuery.value) return items;
        
        const query = searchQuery.value.toLowerCase();
        return items.filter(item => {
            return searchKeys.some(key => {
                const val = item[key];
                return val && String(val).toLowerCase().includes(query);
            });
        });
    });

    const totalPages = computed(() => {
        return Math.ceil(filteredData.value.length / itemsPerPage.value) || 1;
    });

    // Reset to page 1 if search query changes
    const paginatedData = computed(() => {
        if (currentPage.value > totalPages.value) {
            currentPage.value = 1;
        }
        const start = (currentPage.value - 1) * itemsPerPage.value;
        const end = start + itemsPerPage.value;
        return filteredData.value.slice(start, end);
    });

    const nextPage = () => {
        if (currentPage.value < totalPages.value) currentPage.value++;
    };

    const prevPage = () => {
        if (currentPage.value > 1) currentPage.value--;
    };

    const goToPage = (page) => {
        if (page >= 1 && page <= totalPages.value) currentPage.value = page;
    };

    return {
        searchQuery,
        currentPage,
        itemsPerPage,
        filteredData,
        paginatedData,
        totalPages,
        nextPage,
        prevPage,
        goToPage
    };
}
