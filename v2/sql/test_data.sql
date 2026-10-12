-- ============================================================
-- KRE Solar CRM — Test Data
-- Run in phpMyAdmin after schema.sql has been imported
-- Admin user (id=1) must exist before running this
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ─── Teams ───────────────────────────────────────────────────
INSERT INTO `teams` (`id`, `name`, `leader_id`, `is_active`) VALUES
(1, 'Alpha Sales Team', 1, 1);

-- ─── Team Members ────────────────────────────────────────────
INSERT INTO `team_members` (`team_id`, `user_id`) VALUES
(1, 1);

-- ─── Leads ───────────────────────────────────────────────────
INSERT INTO `leads`
  (`id`, `source_id`, `name`, `phone`, `email`, `location`,
   `bill_amount`, `potential_kw`, `stage`,
   `assigned_rep_id`, `tele_caller_id`, `created_by`,
   `next_followup_date`, `visit_scheduled_date`,
   `quote_amount`, `won_value`, `deleted_at`)
VALUES
(1, 1, 'Rajesh Kumar',     '9876543210', 'rajesh@example.com',  'Coimbatore, TN', 4500, 3.75, 'Won',              1, 1, 1, NULL,         NULL,         185000, 185000, NULL),
(2, 2, 'Priya Nair',       '9845012345', 'priya@example.com',   'Kochi, KL',      3200, 2.67, 'Quoted',           1, 1, 1, '2026-05-20', NULL,         142000, NULL,   NULL),
(3, 4, 'Suresh Reddy',     '9988776655', 'suresh@example.com',  'Hyderabad, TS',  6000, 5.00, 'Site Visit Completed', 1, 1, 1, '2026-05-18', NULL,    NULL,   NULL,   NULL),
(4, 5, 'Anitha Selvam',    '9712345678', 'anitha@example.com',  'Chennai, TN',    2800, 2.33, 'Visit Scheduled',  1, 1, 1, '2026-05-22', '2026-05-22', NULL,   NULL,   NULL),
(5, 3, 'Mohan Pillai',     '9600112233', 'mohan@example.com',   'Trivandrum, KL', 5500, 4.58, 'Contacted',        1, 1, 1, '2026-05-19', NULL,         NULL,   NULL,   NULL),
(6, 6, 'Kavitha Rajan',    '9551122334', 'kavitha@example.com', 'Madurai, TN',    3800, 3.17, 'New',              1, 1, 1, '2026-05-21', NULL,         NULL,   NULL,   NULL),
(7, 4, 'Deepak Varma',     '9445566778', NULL,                  'Bengaluru, KA',  7200, 6.00, 'Lost',             1, 1, 1, NULL,         NULL,         210000, NULL,   NULL),
(8, 2, 'Lakshmi Menon',    '9334455667', 'lakshmi@example.com', 'Ernakulam, KL',  4100, 3.42, 'On Hold',          1, 1, 1, '2026-06-01', NULL,         NULL,   NULL,   NULL);

-- ─── Activities ──────────────────────────────────────────────
INSERT INTO `activities` (`lead_id`, `user_id`, `type`, `notes`, `occurred_at`) VALUES
(1, 1, 'call',    'Initial call — customer interested in 4 kW system.',   '2026-04-10 10:00:00'),
(1, 1, 'meeting', 'Site visit done. Roof is RCC, 400 sqft available.',    '2026-04-15 11:30:00'),
(1, 1, 'quote',   'Quoted ₹1,85,000 for 3.75 kW system. Customer agreed.','2026-04-20 14:00:00'),
(2, 1, 'call',    'Customer asked for subsidy details — sent email.',      '2026-05-01 09:30:00'),
(3, 1, 'meeting', 'Site visit completed. Shading issue on west side noted.','2026-05-10 10:00:00'),
(5, 1, 'call',    'Follow-up call — interested but needs spouse approval.','2026-05-12 16:00:00'),
(6, 1, 'note',    'New enquiry via Facebook ad. Schedule call tomorrow.',  '2026-05-16 08:00:00'),
(7, 1, 'note',    'Lost — customer chose competitor for lower price.',     '2026-04-28 15:00:00');

-- ─── Reminders ───────────────────────────────────────────────
INSERT INTO `reminders` (`lead_id`, `user_id`, `remind_at`, `message`, `is_done`) VALUES
(2, 1, '2026-05-20 09:00:00', 'Follow up on quote — check if customer needs clarification.',      0),
(3, 1, '2026-05-18 10:00:00', 'Send quotation after site visit analysis.',                        0),
(4, 1, '2026-05-22 08:30:00', 'Confirm visit appointment with Anitha.',                           0),
(5, 1, '2026-05-19 11:00:00', 'Call Mohan — spouse decision expected by today.',                  0),
(1, 1, '2026-04-25 09:00:00', 'Collect advance payment from Rajesh.',                             1);

-- ─── Site Visits ─────────────────────────────────────────────
INSERT INTO `site_visits`
  (`lead_id`, `rep_id`, `checkin_at`, `checkout_at`, `duration_secs`,
   `checkin_lat`, `checkin_lng`, `checkin_accuracy_m`,
   `checkout_lat`, `checkout_lng`, `checkout_accuracy_m`,
   `location_provided`, `voice_notes`, `roof_type`, `roof_area_sqft`, `shading_notes`)
VALUES
(1, 1, '2026-04-15 10:00:00', '2026-04-15 11:45:00', 6300,
 11.0168, 76.9558, 8.5, 11.0168, 76.9558, 9.2,
 1, 'RCC roof in good condition. No major shading. East facing.',
 'RCC', 420.00, 'Minor shadow from water tank — will not affect output significantly.'),

(3, 1, '2026-05-10 09:30:00', '2026-05-10 11:00:00', 5400,
 17.3850, 78.4867, 6.0, 17.3850, 78.4867, 7.5,
 1, 'West-facing roof. Shading from adjacent building after 3 PM.',
 'RCC', 380.00, 'Shadow from neighboring building affects western panels post 3 PM. Orient panels east.');

-- ─── Installations ───────────────────────────────────────────
INSERT INTO `installations`
  (`lead_id`, `installer_id`, `scheduled_date`, `completed_date`,
   `system_kw`, `panel_count`, `inverter_model`, `notes`, `status`)
VALUES
(1, 1, '2026-05-05', '2026-05-07', 3.75, 9, 'Solis 4G 5kW', 'Installation completed without issues. Customer briefed on app monitoring.', 'completed');

-- ─── Lead Financials ─────────────────────────────────────────
INSERT INTO `lead_financials`
  (`lead_id`, `total_amount`, `discount`, `payment_status`,
   `feasibility_status`, `structure_type`, `equipment_brand`, `notes`)
VALUES
(1, 185000, 5000, 'Advance Received',   'Approved', 'GI Structure', 'Vikram Solar',    'Subsidy applied. Net after discount: ₹1,80,000.'),
(2, 142000, 0,    'Pending',            'Submitted', 'Aluminium',   'Adani Solar',     'Bank loan in progress. Documents submitted.'),
(3, 168000, 3000, 'Pending',            'Not Started','GI Structure','Waaree',         'Quote to be finalised after shading analysis.');

-- ─── Lead Payment Installments ───────────────────────────────
INSERT INTO `lead_payment_installments`
  (`lead_id`, `installment_type`, `amount`, `received_date`,
   `cheque_status`, `cheque_amount`, `notes`, `created_by`)
VALUES
(1, 'advance',      50000, '2026-04-22', 'none',     NULL,  'Cash advance received.',                    1),
(1, 'installation', 90000, '2026-05-05', 'cleared',  90000, 'Cheque cleared before installation start.', 1),
(1, 'commissioning',40000, '2026-05-08', 'received', 40000, 'Final cheque received on completion.',      1);

-- ─── Operations ──────────────────────────────────────────────
INSERT INTO `operations`
  (`lead_id`, `team_id`, `operation_type`, `scheduled_at`, `done_at`,
   `interval_days`, `notes`, `performed_by_id`)
VALUES
(1, 1, 'Cleaning', '2026-05-07', '2026-05-07', 120, 'First cleaning done post-installation.',          1),
(1, 1, 'Cleaning', '2026-09-04', NULL,          120, 'Next scheduled cleaning — auto-generated.',      NULL);

-- ─── Audit Log ───────────────────────────────────────────────
INSERT INTO `audit_log`
  (`lead_id`, `user_id`, `action`, `entity`, `field_name`, `old_value`, `new_value`, `ip_address`)
VALUES
(1, 1, 'CREATE',       'lead',         NULL,            NULL,               'Lead created',       '127.0.0.1'),
(1, 1, 'STAGE_CHANGE', 'lead',         'stage',         'New',              'Contacted',          '127.0.0.1'),
(1, 1, 'STAGE_CHANGE', 'lead',         'stage',         'Contacted',        'Visit Scheduled',    '127.0.0.1'),
(1, 1, 'VISIT_CHECKIN','site_visit',   NULL,            NULL,               'Check-in at site',   '127.0.0.1'),
(1, 1, 'STAGE_CHANGE', 'lead',         'stage',         'Visit Scheduled',  'Site Visit Completed','127.0.0.1'),
(1, 1, 'STAGE_CHANGE', 'lead',         'stage',         'Site Visit Completed','Won',             '127.0.0.1'),
(1, 1, 'UPDATE',       'installation', 'status',        NULL,               'completed',          '127.0.0.1');

SET FOREIGN_KEY_CHECKS = 1;
