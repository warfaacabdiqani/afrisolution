import { defineStore } from 'pinia';
import { staffService } from '../services/staff';

export const useStaffStore = defineStore('staff', {
    state: () => ({
        rows: [],
        meta: null,
        stats: null,
        options: null,
        staff: null,
        busy: false,
        error: null,
        notice: '',
        generation: 0,
    }),
    actions: {
        async list(params) {
            const generation = ++this.generation;
            this.busy = true;
            this.error = null;
            this.rows = [];
            this.stats = null;

            try {
                const { data } = await staffService.list(params);
                if (generation === this.generation) {
                    this.rows = data.data;
                    this.meta = data.meta;
                    this.stats = data.stats;
                }
            } catch (error) {
                if (generation === this.generation) this.error = error;
            } finally {
                if (generation === this.generation) this.busy = false;
            }
        },
        async loadOptions() {
            this.options = null;
            this.options = (await staffService.options()).data.data;
        },
        async load(id) {
            this.staff = null;
            this.busy = true;
            this.error = null;
            try {
                this.staff = (await staffService.get(id)).data.data;
            } catch (error) {
                this.error = error;
            } finally {
                this.busy = false;
            }
        },
    },
});
