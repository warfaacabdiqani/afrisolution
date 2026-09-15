import { defineStore } from 'pinia';
import { salonBookingApi as api } from '../services/salonBookings';
import { useClinicContextStore } from './clinicContext';
export const useSalonBookingStore = defineStore('salonBookings', {
    state: () => ({ rows: [], today: [], counts: [], summary: {}, options: null, busy: false, error: null, generation: 0 }),
    actions: {
        clear() { ++this.generation; this.rows = []; this.today = []; this.counts = []; this.summary = {}; this.options = null; },
        async load(params) {
            this.clear(); const generation = this.generation;
            const scope = useClinicContextStore().data?.clinic.id;
            this.busy = true; this.error = null;
            try {
                const results = await Promise.all([api.get('appointments', params), api.get('appointments/options', { branch_id: params.branch_id })]);
                const main = results[0].data, rows = [...main.data];
                for (let page = 2; page <= main.meta.last_page; page++) {
                    if (generation !== this.generation) return;
                    rows.push(...(await api.get('appointments', { ...params, page })).data.data);
                }
                if (generation !== this.generation || scope !== useClinicContextStore().data?.clinic.id) return;
                this.rows = rows; this.today = main.today; this.counts = main.counts; this.summary = main.summary; this.options = results[1].data.data;
            } catch (e) { if (generation === this.generation) this.error = e; }
            finally { if (generation === this.generation) this.busy = false; }
        },
    },
});
