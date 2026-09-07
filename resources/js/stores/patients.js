import { defineStore } from 'pinia';
import { patientService } from '../services/patients';
export const usePatientStore = defineStore('patients', {
    state: () => ({ rows: [], stats: null, meta: null, patient: null, busy: false, error: null, notice: '', generation: 0 }),
    actions: {
        async list(filters) {
            const generation = ++this.generation; this.busy = true; this.error = null; this.rows = []; this.stats = null;
            try { const { data } = await patientService.list(filters); if (generation === this.generation) { this.rows = data.data; this.meta = data.meta; this.stats = data.stats; } }
            catch (error) { if (generation === this.generation) this.error = error; }
            finally { if (generation === this.generation) this.busy = false; }
        },
        async load(id) {
            this.patient = null; this.busy = true; this.error = null;
            try { this.patient = (await patientService.get(id)).data.data; }
            catch (error) { this.error = error; }
            finally { this.busy = false; }
        },
    },
});
