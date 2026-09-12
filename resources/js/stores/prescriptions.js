import { defineStore } from 'pinia';
import { prescriptionService as service } from '../services/prescriptions';
export const usePrescriptionStore = defineStore('prescriptions', {
    state: () => ({ rows: [], meta: null, stats: null, options: null, busy: false, error: null, notice: '', request: 0, optionsRequest: 0, scope: '' }),
    actions: {
        initialize(scope) {
            this.request++; this.optionsRequest++;
            this.rows = []; this.meta = null; this.stats = null; this.options = null; this.error = null;
            if (scope !== this.scope) this.notice = '';
            this.scope = scope;
        },
        async list(filters) {
            const request = ++this.request;
            this.busy = true; this.error = null;
            try {
                const [list, stats] = await Promise.all([service.list(filters), service.stats({ branch_id: filters.branch_id })]);
                if (request !== this.request) return;
                this.rows = list.data.data; this.meta = list.data.meta; this.stats = stats.data.data;
            } catch (error) { if (request === this.request) { this.rows = []; this.error = error; } }
            finally { if (request === this.request) this.busy = false; }
        },
        async loadOptions(branch) {
            const request = ++this.optionsRequest;
            const result = (await service.options(branch)).data.data;
            if (request === this.optionsRequest) this.options = result;
        },
    },
});
