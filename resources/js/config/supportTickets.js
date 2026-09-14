export const ticketStatus = Object.freeze({ open: 'Open', inProgress: 'In Progress', waiting: 'Waiting for Customer', resolved: 'Resolved', closed: 'Closed' });
export const ticketPriority = Object.freeze({ low: 'Low', normal: 'Normal', high: 'High', urgent: 'Urgent' });
export const messageSource = Object.freeze({ business: 'business', platform: 'platform' });
export const supportDate = value => {
    if (!value) return 'Not recorded';
    const date = new Date(value.includes('T') ? value : value.replace(' ', 'T') + 'Z');
    return Number.isNaN(date.getTime()) ? 'Not recorded' : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
};
