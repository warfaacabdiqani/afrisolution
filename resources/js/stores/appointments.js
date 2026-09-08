import { defineStore } from 'pinia';
import { appointmentService } from '../services/appointments';
import { useClinicContextStore } from './clinicContext';
export const useAppointmentStore=defineStore('appointments',{
    state:()=>({rows:[],counts:[],meta:null,todayRows:[],todayMeta:null,summary:{},hours:{schedules:[],leaves:[]},options:null,busy:false,error:null,notice:'',generation:0,scope:''}),
    actions:{
        async load(params,view){
            const scope=`${useClinicContextStore().data.clinic.id}:${params.branch_id}`;if(this.scope!==scope){this.options=null;this.scope=scope;}
            const version=++this.generation;this.busy=true;this.error=null;this.rows=[];this.todayRows=[];this.counts=[];this.summary={};
            try {
                const jobs=await Promise.allSettled([
                    view==='schedule'?appointmentService.list(params):appointmentService.calendar({...params,page:1}),
                    appointmentService.today({...params,start:undefined,end:undefined,page:1}),
                    appointmentService.hours(params),appointmentService.options(params.branch_id),
                ]);
                for(const job of jobs)if(job.status==='rejected')throw job.reason;
                let [main,today,hours,options]=jobs.map(job=>job.value.data);
                const rows=[...main.data];
                if(view!=='schedule')for(let page=2;page<=main.meta.last_page;page++){
                    if(version!==this.generation)return;
                    rows.push(...(await appointmentService.calendar({...params,page})).data.data);
                }
                if(version!==this.generation)return;
                this.rows=rows;this.meta=main.meta;this.counts=main.counts||[];this.todayRows=today.data;this.todayMeta=today.meta;this.summary=today.summary;this.hours=hours.data;this.options=options.data;
            }catch(error){if(version===this.generation)this.error=error;}
            finally{if(version===this.generation)this.busy=false;}
        },
        async todayPage(params,page){const version=this.generation;try{const {data}=await appointmentService.today({...params,page});if(version===this.generation){this.todayRows=data.data;this.todayMeta=data.meta;}}catch(error){if(version===this.generation)this.error=error;}},
    },
});
