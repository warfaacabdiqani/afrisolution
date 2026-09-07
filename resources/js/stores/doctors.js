import { defineStore } from 'pinia';
import { doctorService } from '../services/doctors';
export const useDoctorStore = defineStore('doctors', {
    state: () => ({ rows: [], meta: null, stats: null, doctor: null, options: null, busy: false, error: null, notice: '', generation: 0 }),
    actions: {
        async list(params) { const version = ++this.generation; this.busy = true; this.error = null; this.rows = []; try { const {data} = await doctorService.list(params); if(version === this.generation) { this.rows=data.data;this.meta=data.meta;this.stats=data.stats; } } catch(error) { if(version === this.generation)this.error=error; } finally { if(version === this.generation)this.busy=false; } },
        async loadOptions() { this.options = null; this.options = (await doctorService.options()).data.data; },
        async load(id) { this.doctor=null;this.busy=true;this.error=null;try { this.doctor=(await doctorService.get(id)).data.data; } catch(error) { this.error=error; } finally { this.busy=false; } },
    },
});
