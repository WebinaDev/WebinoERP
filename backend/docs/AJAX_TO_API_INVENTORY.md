# WordPress AJAX → Laravel REST mapping

Maps `webinocrm` `wp_ajax_*` hooks to proposed `/api/v1/...` endpoints. Aligns with [REVIEW_API_SPECIFICATION.md](../../REVIEW_API_SPECIFICATION.md).

**وضعیت پیاده‌سازی مسیرها:** [API_ROUTE_VERIFICATION.md](./API_ROUTE_VERIFICATION.md)

## Conventions

- Auth: `Authorization: Bearer {token}` (Sanctum).
- Prefix: `/api/v1/{module}/...` where `module` is `core`, `crm`, `projects`, `accounting`, `integrations`.

---

## Login & auth (`class-login-ajax-handler.php`)

| WordPress action | Method | REST route |
|------------------|--------|------------|
| `webino_send_login_otp` | POST | `/api/v1/core/auth/otp/send` |
| `webino_verify_login_otp` | POST | `/api/v1/core/auth/otp/verify` |
| `crm_login_with_password` | POST | `/api/v1/core/auth/login` (existing) |
| `webino_set_password` | POST | `/api/v1/core/auth/password/set` |
| `webino_auto_login` | POST | `/api/v1/core/auth/auto-login` (signed token, single-use) |
| — | POST | `/api/v1/core/auth/auto-login/issue` (admin mints token for target user — TTL 5m) |
| `webino_send_email_otp` | POST | `/api/v1/core/auth/email-otp/send` |
| `webino_verify_email_otp` | POST | `/api/v1/core/auth/email-otp/verify` |
| `webinocrm_register_with_password` | POST | `/api/v1/core/auth/register` |

---

## SMS (`class-sms-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webino_send_custom_sms` | POST `/api/v1/integrations/sms/send` |
| `webino_save_settings` (SMS) | PUT `/api/v1/integrations/sms/settings` |

---

## Leads (`class-lead-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webino_get_leads` | GET `/api/v1/crm/leads` |
| `webino_add_lead` | POST `/api/v1/crm/leads` |
| `webino_delete_lead` | DELETE `/api/v1/crm/leads/{id}` |
| `webino_edit_lead` | PATCH `/api/v1/crm/leads/{id}` |
| `webino_change_lead_status` | PATCH `/api/v1/crm/leads/{id}/status` |
| `webino_add_lead_status` | POST `/api/v1/crm/statuses` |
| `webino_delete_lead_status` | DELETE `/api/v1/crm/statuses/{id}` |
| `webino_assign_lead` | PATCH `/api/v1/crm/leads/{id}/assign` |
| `webinocrm_get_lead_assignees` | GET `/api/v1/crm/leads/assignees` |
| `webinocrm_get_lead_for_contract` | GET `/api/v1/crm/leads/{id}/for-contract` |
| `webinocrm_create_customer_from_lead` | POST `/api/v1/crm/leads/{id}/convert` |

---

## Settings (`class-settings-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webinocrm_get_settings` | GET `/api/v1/core/settings` |
| `webino_save_settings` | PUT `/api/v1/core/settings` |
| `webino_save_white_label_settings` | PUT `/api/v1/core/settings/white-label` |
| `save_auth_settings` | PUT `/api/v1/core/settings/auth` |
| `webino_save_user_preference` | PATCH `/api/v1/core/users/me/preferences` |
| `webino_manage_canned_response` | POST/PUT `/api/v1/core/canned-responses` |
| `webino_delete_canned_response` | DELETE `/api/v1/core/canned-responses/{id}` |
| `webino_manage_position` | POST/PUT `/api/v1/core/positions` |
| `webino_delete_position` | DELETE `/api/v1/core/positions/{id}` |
| `webino_manage_task_category` | POST/PUT `/api/v1/core/task-categories` |
| `webino_delete_task_category` | DELETE `/api/v1/core/task-categories/{id}` |

---

## Projects & contracts (`class-project-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webino_manage_project` | POST/PUT `/api/v1/projects/projects` |
| `webino_delete_project` | DELETE `/api/v1/projects/projects/{id}` |
| `webino_manage_contract` | POST/PUT `/api/v1/projects/contracts` |
| `webino_cancel_contract` | POST `/api/v1/projects/contracts/{id}/cancel` |
| `webino_add_project_to_contract` | POST `/api/v1/projects/contracts/{id}/projects` |
| `webino_add_services_from_product` | POST `/api/v1/projects/contracts/from-product` |
| `webino_get_projects_for_customer` | GET `/api/v1/projects/projects` (filter) |
| `webino_delete_contract` | DELETE `/api/v1/projects/contracts/{id}` |
| `webinocrm_get_contracts` | GET `/api/v1/projects/contracts` |
| `webinocrm_get_contract` | GET `/api/v1/projects/contracts/{id}` |
| `webinocrm_get_projects` | GET `/api/v1/projects/projects` |
| `webinocrm_get_project` | GET `/api/v1/projects/projects/{id}` |
| `webinocrm_get_project_templates` | GET `/api/v1/projects/project-templates` |
| `webinocrm_get_project_assignees` | GET `/api/v1/projects/projects/{id}/assignees` |
| `webinocrm_get_product_projects_preview` | GET `/api/v1/projects/products/{id}/projects-preview` |
| `webinocrm_get_assignable_employees` | GET `/api/v1/projects/assignable-users` |

---

## Tickets (`class-ticket-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webino_new_ticket` | POST `/api/v1/projects/tickets` |
| `ticket_reply` | POST `/api/v1/projects/tickets/{id}/replies` |
| `webino_convert_ticket_to_task` | POST `/api/v1/projects/tickets/{id}/convert-task` |
| `webino_submit_ticket_rating` | POST `/api/v1/projects/tickets/{id}/rating` |
| `webino_get_canned_response` | GET `/api/v1/core/canned-responses/{id}` |
| `webinocrm_get_tickets` | GET `/api/v1/projects/tickets` |
| `webinocrm_get_ticket` | GET `/api/v1/projects/tickets/{id}` |

---

## Payments (`class-payment-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webino_initiate_payment` | POST `/api/v1/integrations/payments/initiate` |
| `webino_verify_payment` | POST `/api/v1/integrations/payments/verify` |

---

## Dashboard & logs (`class-dashboard-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webinocrm_get_dashboard_stats` | GET `/api/v1/core/dashboard/stats` |
| `webinocrm_get_dashboard_full` | GET `/api/v1/core/dashboard` |
| `webinocrm_get_team_member_stats` | GET `/api/v1/core/dashboard/stats/team-member` |
| `webinocrm_get_client_stats` | GET `/api/v1/core/dashboard/stats/client` |
| `webinocrm_get_logs` | GET `/api/v1/core/logs` |
| `webinocrm_get_reports` | GET `/api/v1/core/reports` |
| `webinocrm_get_system_logs` | GET `/api/v1/core/logs/system` |
| `webinocrm_get_user_logs` | GET `/api/v1/core/logs/user` |
| `webinocrm_log_console` | POST `/api/v1/core/logs/console` |
| `webinocrm_log_user_action` | POST `/api/v1/core/logs/user-actions` |
| `webinocrm_delete_system_logs` | DELETE `/api/v1/core/logs/system` |
| `webinocrm_export_reports_csv` | GET `/api/v1/core/reports/export.csv` |
| `webinocrm_track_visit` | POST `/api/v1/core/visitor-stats/track` |
| `webinocrm_get_visitor_stats` | GET `/api/v1/core/visitor-stats` |

---

## Tasks (`class-task-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webino_add_task`, `webino_create_task` | POST `/api/v1/projects/tasks` |
| `webino_quick_add_task` | POST `/api/v1/projects/tasks/quick` |
| `webino_update_task_status` | PATCH `/api/v1/projects/tasks/{id}/status` |
| `webino_delete_task` | DELETE `/api/v1/projects/tasks/{id}` |
| `webino_update_task_assignee` | PATCH `/api/v1/projects/tasks/{id}/assignee` |
| `webino_add_task_comment` | POST `/api/v1/projects/tasks/{id}/comments` |
| `webino_save_task_content` | PATCH `/api/v1/projects/tasks/{id}/content` |
| `webino_manage_checklist` | POST `/api/v1/projects/tasks/{id}/checklist` |
| `crm_log_time` | POST `/api/v1/projects/tasks/{id}/time-logs` |
| `webino_quick_edit_task` | PATCH `/api/v1/projects/tasks/{id}` |
| `get_tasks_for_views` | GET `/api/v1/projects/tasks` |
| `webino_add_task_link` | POST `/api/v1/projects/tasks/{id}/links` |
| `webino_remove_task_link` | DELETE `/api/v1/projects/tasks/{id}/links/{linkId}` |
| `webino_search_tasks_for_linking` | GET `/api/v1/projects/tasks/search` |
| `webino_bulk_edit_tasks` | PATCH `/api/v1/projects/tasks/bulk` |
| `webino_save_task_as_template` | POST `/api/v1/projects/task-templates` |
| `webino_get_tasks_calendar` | GET `/api/v1/projects/tasks/calendar` |
| `webino_get_tasks_gantt` | GET `/api/v1/projects/tasks/gantt` |
| `webinocrm_get_tasks` | GET `/api/v1/projects/tasks` |

---

## Users (`class-user-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webino_manage_user` | POST/PUT `/api/v1/core/users` |
| `webino_delete_user` | DELETE `/api/v1/core/users/{id}` |
| `webino_update_my_profile` | PATCH `/api/v1/core/users/me` |
| `webino_search_users` | GET `/api/v1/core/users/search` |
| `webinocrm_get_users` | GET `/api/v1/core/users` |
| `webinocrm_send_bale_message` | POST `/api/v1/integrations/bale/messages` |
| `webinocrm_send_bale_bulk_message` | POST `/api/v1/integrations/bale/messages/bulk` |

---

## Workflow (`class-workflow-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webino_save_status_order` | PUT `/api/v1/projects/workflow/status-order` |
| `webino_add_new_status` | POST `/api/v1/projects/workflow/statuses` |
| `webino_delete_status` | DELETE `/api/v1/projects/workflow/statuses/{id}` |
| (position / task category duplicates) | same as settings routes |

---

## Services / Woo (`class-services-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webinocrm_list_subscriptions` | GET `/api/v1/projects/subscriptions` |
| `webinocrm_list_products` | GET `/api/v1/projects/products` |
| `webinocrm_convert_subscription_to_contract` | POST `/api/v1/projects/subscriptions/{id}/convert-contract` |
| `webinocrm_update_product_task_template` | PUT `/api/v1/projects/products/{id}/task-template` |
| `webinocrm_get_task_templates` | GET `/api/v1/projects/task-templates` |

---

## Notifications (`class-notification-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webino_get_notifications` | GET `/api/v1/core/notifications` |
| `webino_mark_notification_read` | PATCH `/api/v1/core/notifications/{id}/read` |

---

## Modals (`class-modal-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webino_get_project_details` | GET `/api/v1/projects/projects/{id}/details` |
| `webino_get_customer_360` | GET `/api/v1/crm/accounts/{id}/360` |
| `webino_get_contract_details` | GET `/api/v1/projects/contracts/{id}/details` |

---

## Import/export (`class-import-export-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webino_export_leads` | GET `/api/v1/crm/leads/export` |
| `webino_export_customers` | GET `/api/v1/crm/accounts/export` |
| `webino_export_projects` | GET `/api/v1/projects/projects/export` |
| `webino_import_leads` | POST `/api/v1/crm/leads/import` |
| `webino_import_customers` | POST `/api/v1/crm/accounts/import` |

---

## Forms / invoices / appointments (`class-form-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webino_manage_pro_invoice` | POST/PUT `/api/v1/projects/invoices` |
| `webino_generate_pro_invoice_pdf` | POST `/api/v1/projects/invoices/{id}/pdf` |
| `webinocrm_get_invoices` | GET `/api/v1/projects/invoices` |
| `webinocrm_get_invoice` | GET `/api/v1/projects/invoices/{id}` |
| `webino_manage_appointment` | POST/PUT `/api/v1/projects/appointments` |
| `webino_delete_appointment` | DELETE `/api/v1/projects/appointments/{id}` |
| `webino_client_request_appointment` | POST `/api/v1/projects/appointments/request` |

---

## Consultations (`class-consultation-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webinocrm_get_consultations` | GET `/api/v1/crm/consultations` |
| `webino_manage_consultation` | POST/PUT `/api/v1/crm/consultations` |
| `webino_convert_consultation_to_project` | POST `/api/v1/crm/consultations/{id}/convert-project` |

---

## Agile (`class-agile-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webino_get_backlog_tasks` | GET `/api/v1/projects/sprints/backlog` |
| `webino_add_task_to_sprint` | POST `/api/v1/projects/sprints/{id}/tasks` |

---

## PDF (`class-pdf-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webino_generate_contract_pdf` | POST `/api/v1/projects/contracts/{id}/pdf` |
| `webino_generate_invoice_pdf` | POST `/api/v1/projects/invoices/{id}/pdf` |
| `webino_download_pdf` | GET `/api/v1/core/files/pdf/{token}` |

---

## Licenses (`class-license-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webinocrm_get_licenses` | GET `/api/v1/core/licenses` |
| `webinocrm_add_license` | POST `/api/v1/core/licenses` |
| `webinocrm_update_license` | PATCH `/api/v1/core/licenses/{id}` |
| `webinocrm_renew_license` | POST `/api/v1/core/licenses/{id}/renew` |
| `webinocrm_cancel_license` | POST `/api/v1/core/licenses/{id}/cancel` |
| `webinocrm_delete_license` | DELETE `/api/v1/core/licenses/{id}` |

---

## Email (`class-email-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webino_send_contract_email` | POST `/api/v1/projects/contracts/{id}/email` |
| `webino_send_invoice_email` | POST `/api/v1/projects/invoices/{id}/email` |

---

## Attachments (`class-attachment-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webino_upload_task_attachment` | POST `/api/v1/projects/tasks/{id}/attachments` |
| `webino_delete_task_attachment` | DELETE `/api/v1/projects/tasks/{taskId}/attachments/{id}` |
| `webino_get_task_attachments` | GET `/api/v1/projects/tasks/{id}/attachments` |

---

## Appointments (extended) (`class-appointment-ajax-handler.php`)

| Action | REST |
|--------|------|
| `webino_get_appointments_calendar` | GET `/api/v1/projects/appointments/calendar` |
| `webino_quick_create_appointment` | POST `/api/v1/projects/appointments/quick` |
| `webino_update_appointment_date` | PATCH `/api/v1/projects/appointments/{id}/date` |
| `webino_get_customers_list` | GET `/api/v1/crm/accounts/list` |
| `webinocrm_get_appointments` | GET `/api/v1/projects/appointments` |
| `webinocrm_get_appointment` | GET `/api/v1/projects/appointments/{id}` |

---

## Navigation (new, backend-driven menu)

| — | GET `/api/v1/core/navigation` — items + categories per Spatie roles (parity with `class-sidebar-menu-builder.php`). |

---

## Field Security (`class-field-security.php`)

| Action | REST |
|--------|------|
| `webinocrm_update_field_permissions` | PUT `/api/v1/core/field-permissions` |
| — | GET `/api/v1/core/field-permissions` |
| — | DELETE `/api/v1/core/field-permissions/{id}` |
| — | GET `/api/v1/core/field-permissions/viewable?entity=lead` |

---

## White Label / Branding (`class-white-label.php`)

| Behavior | REST |
|----------|------|
| `apply_color_scheme` / `add_custom_css` | GET `/api/v1/core/branding.css` (runtime theme CSS) |
| `get_primary_colors` / favicon / logos | GET `/api/v1/core/branding` (JSON manifest) |

---

## Scheduled jobs (parity with plugin cron hooks)

| Cron hook | Laravel command | Schedule |
|-----------|-----------------|----------|
| `webino_daily_reminder_hook` / `webino_create_daily_tasks_hook` | `webino:reminders` | daily 09:00 |
| `webinocrm_weekly_db_optimize` | `webino:db-optimize` | weekly Sun 03:15 |

---

This inventory is the contract for implementing `/api/v1` controllers and policies.
