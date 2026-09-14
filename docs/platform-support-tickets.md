# Platform Administration: Support Tickets

Platform administrators can manage existing tenant support tickets at `/app/admin/support-tickets`. The implementation extends the existing three support tables; it does not create another ticket system or conversation.

## User workflow

- Open **Platform Administration → Support Tickets**.
- Review platform-wide Open, In Progress, Waiting for Customer, Resolved, and Total Tickets counts. Total includes Closed tickets. Counts remain platform-wide when the list is filtered.
- Search ticket number, subject, business name, submitter name, or submitter email.
- Filter by business, business type, status, priority, category, and inclusive creation dates in UTC. Results are paginated on the server, 20 per page.
- Choose **View / Manage** to see business and branch context, submitter and current membership role, diagnostics, timestamps, attachments, and chronological conversation.
- Reply, optionally attaching a PNG, JPG, or PDF up to 2 MB. The same message appears in the existing tenant Help & Support conversation.
- Change status, priority, or category according to assigned permissions. Resolving and closing require a confirmation in the UI. Closed tickets must be reopened by an authorized administrator before either side can reply or add attachments.

The mobile list uses cards; tablet and desktop use a horizontally scrollable table. Detail pages use a single column on smaller screens. Message labels distinguish Business User from Platform Support / Admin.

Application version and user agent were not stored on existing tickets and are not invented or taken from the administrator's browser. Existing recorded diagnostic fields are displayed as text; no server configuration or secrets are included in ticket responses.

## Shared architecture and synchronization

Reused tables:

- `support_tickets`
- `support_ticket_messages`
- `support_ticket_attachments`

The new `SupportTicketService` centralizes creation, replies, conversation retrieval, attachment storage/downloads, and updates. The existing tenant controller delegates to it after tenant authorization; the platform controller delegates after platform authorization.

Both interfaces read the same ticket status, priority, category, attachments, and messages. The acting interface reloads the saved data automatically after a mutation. The other interface sees it on its next data load; this does not introduce polling, WebSockets, or real-time notifications.

Admin replies update `updated_at` and preserve the existing status. Tenant replies retain the existing transition to In Progress, except that Closed tickets reject replies. Reply and status operations use transactions and lock the ticket while updating it.

Ticket creation now uses the globally allocated ticket ID for its number, with a fallback for conflicting legacy numbers. Original numbers remain unchanged. Creation and the initial message are atomic; stored files are removed if the enclosing operation fails.

## Authorization

Every platform endpoint requires authentication, an active platform administrator, and `support_tickets.view`. Additional permissions:

| Permission | Allows |
| --- | --- |
| `support_tickets.reply` | Reply with an optional attachment |
| `support_tickets.update` | Update status or category |
| `support_tickets.close` | Close a ticket or reopen a closed ticket, in addition to update permission |
| `support_tickets.manage_priority` | Change priority |
| `support_tickets.view_attachments` | Download attachments |

Permissions are registered in the existing platform permission tables. Super Administrator receives all six; other roles can be configured through Roles & Permissions. The session resource exposes the authenticated administrator's permission names so the sidebar and action controls can reflect access. Backend checks remain authoritative.

Tenant endpoints continue to resolve ownership from the authenticated tenant session and active membership. Frontend `tenant_id`, user ID, status, or message source cannot override ownership or sender identity. Existing own-ticket versus tenant-wide visibility rules remain enforced. Business owners and tenant administrators cannot access the platform API or administration support pages.

Business type names, IDs, slugs, and categories come from the tenant's related business-type record. Platform support does not require a selected tenant, an active tenant subscription, or a clinic-specific context. Missing business-type information is displayed as not recorded.

## API endpoints

All endpoints below use the prefix `/api/v1/platform/support-tickets`:

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/` | Search, filters, pagination |
| GET | `/stats` | Platform-wide counts |
| GET | `/options` | Business/type filters and status/priority/category options |
| GET | `/{ticket}` | Details and shared conversation |
| POST | `/{ticket}/reply` | Reply; accepts multipart attachments |
| PUT | `/{ticket}/status` | Update status |
| PUT | `/{ticket}/priority` | Update priority |
| PUT | `/{ticket}/category` | Update category |
| GET | `/{ticket}/attachments/{attachment}/download` | Authorized private download |

Existing `/api/v1/clinic/support/*` routes are preserved for compatibility.

## Attachments, audit, and notifications

Files use the existing local private storage root, `storage/app/private/support_attachments`. This fixes uploads previously referring to an unconfigured `private` disk. Stored filenames are unique; metadata retains the original download name. Responses do not expose storage paths or public file URLs. Downloads require authorization and attachment-to-ticket matching and use private/no-store and nosniff response headers.

The existing platform audit service records actor, tenant, ticket ID/number, timestamp, and old/new field values. Events include:

- `support_ticket.admin_replied`
- `support_ticket.status_changed`
- `support_ticket.priority_changed`
- `support_ticket.category_changed`
- `support_ticket.closed`

Existing tenant creation/reply events remain. Audit entries do not include full conversation text or attachment contents. Support events appear under the Support Tickets audit module.

No existing support email/SMS/broadcast notification implementation was found. No notification delivery is simulated. The shared conversation is the communication channel.

Support API server failures return a generic message while Laravel retains server-side exception reporting. The shared frontend error component also hides server error details for HTTP 5xx responses.

## Migration

`2026_09_14_050000_extend_support_ticket_management.php` adds only the missing message `source` field and registers platform permissions. Existing messages default to Business User because they were submitted through the tenant workflow. New messages persist the source at submission time, so subsequent user-role changes do not rewrite conversation identity.

The migration has been applied to the local application database. Existing tenant ticket, priority, category, and status fields required no new schema.

## Files created

- `app/Http/Controllers/PlatformSupportTicketController.php`
- `app/Http/Requests/PlatformSupportTicketIndexRequest.php`
- `app/Services/SupportTicketService.php`
- `app/Support/SupportTicketOptions.php`
- `database/migrations/2026_09_14_050000_extend_support_ticket_management.php`
- `resources/js/services/platformSupport.js`
- `resources/js/config/supportTickets.js`
- `resources/js/pages/admin/support/Index.vue`
- `resources/js/pages/admin/support/Show.vue`
- `resources/js/components/admin/support/SupportTicketStats.vue`
- `resources/js/components/admin/support/SupportTicketFilters.vue`
- `resources/js/components/admin/support/SupportTicketTable.vue`
- `resources/js/components/admin/support/TicketManagement.vue`
- `resources/js/components/admin/support/AdminReplyBox.vue`
- `resources/js/components/support/TicketBadge.vue`
- `resources/js/components/support/TicketConversation.vue`
- `resources/js/components/support/AttachmentList.vue`
- `resources/js/views/ForbiddenView.vue`
- `tests/Feature/SupportTicketManagementTest.php`
- `tests/Browser/support.spec.js`
- `tests/Browser/support-fixtures.php`
- `docs/platform-support-tickets.md`

## Files modified

- `app/Http/Controllers/SupportController.php`
- `app/Http/Resources/SessionResource.php`
- `app/Services/PlatformService.php`
- `bootstrap/app.php`
- `config/platform_permissions.php`
- `routes/api.php`
- `resources/js/router/index.js`
- `resources/js/layouts/AdminLayout.vue`
- `resources/js/components/ui/AppIcon.vue`
- `resources/js/components/ui/FormErrors.vue`
- `resources/js/pages/support/Index.vue`
- `resources/js/pages/support/TicketShow.vue`

## Verification

- Full Laravel suite: 85 tests passed, 1,442 assertions, including 12 new support feature tests.
- Production build: `npm run build` passed. Vite reports a non-blocking main-bundle size warning.
- Playwright support workflow: passed with real backend requests and separate admin, tenant, and read-only support sessions; no JavaScript page errors.
- Browser checks cover navigation, real counts, search, business/type/status/priority filters, details, replies in both directions, private attachment downloads, status confirmations, closing behavior, tenant redirects, permission-controlled actions, and mobile layout without horizontal page overflow.
- Screenshots are generated at `test-results/support-desktop.png` and `test-results/support-mobile.png` (ignored test artifacts).
- PHP commands used `C:/php84/php.exe`; the default shell PHP is 8.2 and is older than this project's installed runtime requirements.
- Browser fixtures validate the dedicated test SQLite database path before writing and do not modify the application database.
