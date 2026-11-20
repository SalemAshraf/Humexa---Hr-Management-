
import { ref } from 'vue';

export function useSidebar() {
    const isCollapsed = ref(false);
    const isMobileOpen = ref(false);

    function toggleCollapsed() {
        isCollapsed.value = !isCollapsed.value;
    }

    function openMobile() {
        isMobileOpen.value = true;
    }

    function closeMobile() {
        isMobileOpen.value = false;
    }

    function toggleMobile() {
        isMobileOpen.value = !isMobileOpen.value;
    }

    return {
        isCollapsed,
        isMobileOpen,
        toggleCollapsed,
        openMobile,
        closeMobile,
        toggleMobile,
    };
}
