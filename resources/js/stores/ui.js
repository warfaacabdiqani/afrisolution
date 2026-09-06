import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useUiStore = defineStore('ui', () => {
    const notice = ref('The application foundation is ready. Clinical modules are not available yet.');

    function dismissNotice() {
        notice.value = '';
    }

    return { notice, dismissNotice };
});
