import { minutes } from './appointmentDates';
// Shared lane layout for simultaneous appointments, independent of industry.
export function positionBookings(rows) {
    const events = rows.map(a => ({ ...a, start: minutes(a.starts_at.slice(11)), end: minutes(a.ends_at.slice(11)) })).sort((a, b) => a.start - b.start || a.end - b.end);
    let group = [], until = -1;
    function arrange() {
        const ends = [];
        for (const a of group) { let lane = ends.findIndex(end => end <= a.start); if (lane < 0) lane = ends.length; ends[lane] = a.end; a.lane = lane; }
        for (const a of group) a.lanes = ends.length;
    }
    for (const a of events) { if (a.start >= until) { arrange(); group = []; until = -1; } group.push(a); until = Math.max(until, a.end); }
    arrange(); return events;
}
