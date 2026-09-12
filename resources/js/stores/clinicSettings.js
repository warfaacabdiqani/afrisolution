import { defineStore } from 'pinia';
import { clinicSettingsService } from '../services/clinicSettings';
export const useClinicSettingsStore = defineStore('clinicSettings', {
    state: () => ({ data: null, error: null, busy: false, notice: '', scope: '', version: 0, beforeExit: null }),
    actions: {
        async load(scope) {
            const version = ++this.version;
            if (scope !== this.scope) { this.data = null; this.notice = ''; this.scope = scope; }
            this.busy = true; this.error = null;
            try { const response = await clinicSettingsService.get(); if (version === this.version) this.data = response.data.data; }
            catch (error) { if (version === this.version) this.error = error; }
            finally { if (version === this.version) this.busy = false; }
        },
    },
});
