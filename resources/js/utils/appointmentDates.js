// Calendar dates are clinic-local civil dates, independent of the browser timezone.
export const weekDays=['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];
export const terminalStatuses=['completed','cancelled','no_show'];
export const dayDate=value=>new Date(`${value}T12:00:00Z`);
export const dateKey=date=>date.toISOString().slice(0,10);
export function addDays(value,days){const date=dayDate(value);date.setUTCDate(date.getUTCDate()+days);return dateKey(date);}
export function weekStart(value){return addDays(value,-((dayDate(value).getUTCDay()+1)%7));}
export function monthStart(value){return `${value.slice(0,7)}-01`;}
export function moveMonth(value,delta){const date=dayDate(monthStart(value));date.setUTCMonth(date.getUTCMonth()+delta);return dateKey(date);}
export function calendarRange(date,view){
    if(view==='day')return {start:date,end:date};
    if(view==='month'){const start=weekStart(monthStart(date));return {start,end:addDays(start,41)};}
    const start=weekStart(date);return {start,end:addDays(start,6)};
}
export const formatDate=(value,options={month:'short',day:'numeric',year:'numeric'})=>dayDate(value).toLocaleDateString('en-GB',{...options,timeZone:'UTC'});
export const minutes=time=>Number(time.slice(0,2))*60+Number(time.slice(3,5));
export const clock=value=>`${String(Math.floor(value/60)).padStart(2,'0')}:${String(value%60).padStart(2,'0')}`;
export const appointmentLink=a=>({path:`/app/appointments/${a.id}`,query:{date:a.starts_at.slice(0,10),branch_id:String(a.branch_id)}});
