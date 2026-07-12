# WorkEddy Developer Specification

Occupational Health Prevention, Ergonomic Risk Assessment, Corrective Action, and Evidence Generation Platform

| **Document Version**       | 1.0                                                                                                                                             |
|----------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------|
| **Prepared For**           | WorkEddy Development Team                                                                                                                       |
| **Primary Build Approach** | Manual assessment + rules based recommendations + human review; AI assisted features added in phases                                            |
| **Core Objective**         | Build a prevention evidence system that identifies ergonomic risk, tracks corrective action, documents improvement, and protects worker privacy |

# Table of Contents

- 1\. User Roles and Access Control
- 2\. Task Video Capture
- 3\. AI Assisted Ergonomic Risk Scoring
- 4\. Body Region Heat Map
- 5\. Corrective Action Recommendation Engine
- 6\. Before and After Comparison
- 7\. Worker Voice and Discomfort Reporting
- 8\. Privacy and Trust Features
- 9\. Pilot Study Dashboard
- 10\. Validation and Reviewer Module
- 11\. PDF Report Generator
- 12\. De Identified Research Export
- 13\. Public Health Impact Tracker
- 14\. National Importance Dashboard
- 15\. Methodology and Limitations Page
- Final MVP Build Order and Acceptance Gate

# Platform Purpose

Build WorkEddy as an occupational health prevention, ergonomic risk assessment, corrective action, and evidence generation platform. The platform must not function as a basic safety checklist app. It should help organizations identify ergonomic hazards, document task level risks, recommend corrective actions, verify improvements, protect worker privacy, and generate reports that can support pilots, employer adoption, research, grant applications, and evidence.

The system should be built in phases. The MVP should rely on manual assessment, rules based logic, and human review. AI should support posture detection and scoring in later stages, but the platform must remain functional even without advanced AI.

## Core Software Goal

- Record a short work task video.
- Analyze ergonomic risk.
- Show affected body regions.
- Recommend corrective actions.
- Track whether corrections were completed.
- Compare before and after risk scores.
- Produce professional PDF reports.
- Export de identified data for pilot studies, research, and NIW evidence.

## Build Philosophy

| **Principle**       | **Developer Instruction**                                                                                                                            |
|---------------------|------------------------------------------------------------------------------------------------------------------------------------------------------|
| Prevention first    | Every workflow should move from identification of risk to corrective action and follow up, not merely documentation.                                 |
| Explainability      | Risk scores, corrective actions, and impact estimates must be traceable to stored inputs, rules, reviewer decisions, and timestamps.                 |
| Human review        | Safety related conclusions should be reviewed and locked by a qualified reviewer before appearing as final in reports.                               |
| Privacy by design   | Consent, access limits, face blurring, secure storage, and audit logs must be included from the start.                                               |
| Evidence generation | Every assessment, correction, validation, export, and report should create usable evidence for pilots, research, employer adoption, and NIW support. |

# 1. User Roles and Access Control

Create a secure multi user, multi organization role based access system. Each user must belong to an organization. Each organization may contain multiple worksites, departments, job roles, tasks, assessments, videos, corrective actions, reports, and dashboards.

## Required Roles and Permissions

| **Role**                        | **Functional Permissions**                                                                                                                                                                                  |
|---------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Organization Admin              | Create and manage organization profile; create worksites and departments; invite, deactivate, and assign users; manage privacy settings; view all dashboards; export reports; view audit logs.              |
| Safety Manager                  | Create assessments; upload or review task videos; complete risk factor checklists; approve or adjust scores; assign corrective actions; review evidence uploads; verify actions; generate reports.          |
| Supervisor                      | Upload task videos; view assigned tasks; respond to corrective actions; upload evidence; mark actions completed; add completion notes; respond to follow up assessment requests.                            |
| Worker                          | Submit task feedback; report discomfort; complete consent forms; upload task videos only when organization settings allow; view only their own submissions unless the submission is anonymous.              |
| External Reviewer or Ergonomist | Review assessments; compare estimated scores with professional observation; approve or adjust scores; add reviewer notes; attach credentials; approve final reports without changing organization settings. |

## How to Develop It

- Implement role based access control through a permission model rather than hard coding permissions into page components.
- Protect every route and every API endpoint with authorization checks. UI hiding alone is not sufficient.
- Use organization_id on all core records so users only access records that belong to their organization.
- Create an audit log entry for score edits, video views, report downloads, data exports, corrective action changes, and user permission changes.
- Allow Organization Admins to disable accounts without deleting historical records.

## Recommended Database Tables

| **Table**        | **Purpose**                                    | **Key Fields**                                                                                |
|------------------|------------------------------------------------|-----------------------------------------------------------------------------------------------|
| users            | Stores platform users.                         | user_id, organization_id, name, email, password_hash, role_id, status, created_at, last_login |
| roles            | Stores user role names.                        | role_id, role_name, description                                                               |
| permissions      | Stores specific actions that can be granted.   | permission_id, permission_name, description                                                   |
| role_permissions | Maps roles to permissions.                     | role_id, permission_id                                                                        |
| organizations    | Stores employer or pilot organization account. | organization_id, organization_name, industry, address, admin_user_id, created_at              |
| worksites        | Stores each organization location.             | worksite_id, organization_id, name, location, active_status                                   |

## Acceptance Criteria

- A Worker cannot see another worker’s identifiable submission.
- A Supervisor cannot view organization wide dashboards unless the role is granted permission.
- A Safety Manager can assign and verify corrective actions.
- An External Reviewer can review assessments but cannot change organization settings.
- Every sensitive action is written to the audit log.

# 2. Task Video Capture

Build a mobile friendly video capture and upload feature. The MVP should work as a responsive web application or PWA so users can record or upload a short work task video from a phone, tablet, or desktop browser.

## Required Fields

| **Field Group**         | **Fields to Capture**                                                                                                   |
|-------------------------|-------------------------------------------------------------------------------------------------------------------------|
| Task information        | Task name, task description, industry, worksite, department, job role, shift                                            |
| Exposure information    | Task duration, frequency of task, load weight if lifting is involved, object weight category, number of workers exposed |
| Health and risk context | Body part most affected, task difficulty notes, observed risk factors if known                                          |
| Privacy and consent     | Worker consent checkbox, privacy notice checkbox, face blur request, data retention option if enabled                   |
| System fields           | Video upload date, uploader ID, storage path, thumbnail path, status                                                    |

## Functional Workflow

- User selects worksite, department, job role, and task.
- User records a new video or uploads an existing video.
- System validates file type, file size, and video duration.
- User confirms consent and privacy notice before submission.
- System stores video in secure object storage and creates a thumbnail.
- System marks the assessment as Pending Review.
- If face blurring is requested, the video is marked Face Blurring Required before reviewer access.

## How to Develop It

- Use browser media capture APIs for direct recording.
- Store files in secure object storage such as AWS S3, Azure Blob, or Google Cloud Storage.
- Use signed URLs for access; do not store or expose public URLs.
- Create a background job for thumbnail generation and later face blurring.
- Store only file paths or storage keys in the database, not public file links.

| **Table**   | **Purpose**                                    | **Key Fields**                                                                                                                   |
|-------------|------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------|
| task_videos | Stores video metadata linked to an assessment. | video_id, assessment_id, file_path, thumbnail_path, uploaded_by, upload_date, face_blur_status, retention_status, storage_status |
| tasks       | Stores the task being assessed.                | task_id, organization_id, worksite_id, department, job_role, task_name, task_description, industry, created_at                   |

## Acceptance Criteria

- Videos cannot be accessed through public links.
- Video must be connected to a task and assessment.
- Consent and privacy notice must be captured before video submission.
- The platform must support face blurring request in the MVP and automated face blurring in a later phase.

# 3. AI Assisted Ergonomic Risk Scoring

Build the scoring module so the MVP works without advanced AI. The first version should support manual scoring fields, risk factor checklists, and reviewer confirmation. AI posture detection can be added as a support layer.

## MVP Scoring Scope

- Manual ergonomic scoring inputs.
- REBA informed whole body risk fields.
- RULA informed upper limb risk fields.
- Manual material handling risk factors.
- NIOSH lifting related inputs where applicable.
- General task risk level derived from selected risk factors and score ranges.
- Reviewer confirmation workflow.

## Risk Inputs to Capture

| **Risk Category**       | **Inputs**                                                                                     |
|-------------------------|------------------------------------------------------------------------------------------------|
| Posture                 | Bending, twisting, reaching, overhead work, squatting, kneeling, wrist posture, static posture |
| Force and load          | Load weight, force level, push or pull effort, carry distance                                  |
| Repetition and duration | Task frequency, repetition count, duration, recovery time                                      |
| Exposure scope          | Number of workers exposed, job role, department, shift                                         |
| Worker feedback         | Discomfort body region, discomfort intensity, seven day pain, thirty day pain                  |

## Future AI Assisted Layer

- Use pose estimation to detect body joints.
- Estimate trunk angle, arm elevation, squatting, kneeling, reach, and possible repetition count.
- Generate an estimated risk score and confidence level.
- Flag low confidence outputs for reviewer attention.
- Store model version with every AI output so results remain traceable.

## Platform Wording Rules

| **Use This Wording**              | **Avoid This Wording**         |
|-----------------------------------|--------------------------------|
| Estimated ergonomic risk score    | OSHA compliant                 |
| AI assisted assessment            | Guaranteed injury prevention   |
| Reviewer confirmation required    | Certified ergonomic assessment |
| Supports prevention documentation | Legally approved risk score    |

## How to Develop It

| **Table**               | **Purpose**                                                   | **Key Fields**                                                                                                      |
|-------------------------|---------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------|
| assessments             | Stores each ergonomic assessment.                             | assessment_id, task_id, assessment_type, scoring_method, total_score, risk_level, score_source, reviewer_id, status |
| risk_factors            | Stores the master list of risk factors.                       | risk_factor_id, name, category, description                                                                         |
| assessment_risk_factors | Stores risk factors selected for each assessment.             | assessment_id, risk_factor_id, severity, notes, source                                                              |
| ai_score_outputs        | Stores future AI output separately from final reviewed score. | assessment_id, ai_total_score, confidence_level, detected_postures, model_version, created_at                       |

## Acceptance Criteria

- The scoring system must work without AI.
- The report must distinguish between estimated, manually entered, and reviewer confirmed scores.
- No AI score should become final without a reviewer workflow.
- Score source and scoring method must be stored for every assessment.

# 4. Body Region Heat Map

Create a visual heat map that shows which body regions are affected by the task. This must be stored as structured data so it can be used in reports, comparison screens, pilot dashboards, and research exports.

## Required Body Regions

- Neck
- Shoulders
- Upper back
- Lower back
- Elbows
- Wrists and hands
- Hips
- Knees
- Ankles and feet

## Risk Levels

| **Risk Level** | **Meaning**                                                                      |
|----------------|----------------------------------------------------------------------------------|
| Low            | Minimal task related strain observed or reported.                                |
| Moderate       | Noticeable exposure that may require monitoring or minor control.                |
| High           | Meaningful exposure that should receive corrective action.                       |
| Very high      | Substantial exposure that should receive urgent corrective action and follow up. |

## How to Develop It

- Use an SVG body diagram with clickable regions.
- Allow the reviewer to assign severity for each region.
- Display both front and back views if feasible.
- Store body region score separately from total score.
- Render the heat map in the assessment report and before and after comparison report.

| **Table**          | **Purpose**                                           | **Key Fields**                                                                  |
|--------------------|-------------------------------------------------------|---------------------------------------------------------------------------------|
| body_region_scores | Stores risk level by body region for each assessment. | body_region_score_id, assessment_id, body_region, risk_score, risk_level, notes |

## Acceptance Criteria

- The heat map updates when risk levels are selected.
- Before and after heat maps can be shown side by side.
- The system can identify improved body regions after follow up assessment.
- The heat map appears correctly in the PDF report.

# 5. Corrective Action Recommendation Engine

Build the corrective action engine as a rules based expert system for the MVP. It should not depend fully on AI. The system should use selected assessment inputs, risk factors, severity levels, task type, and industry to recommend corrective actions that follow the hierarchy of controls.

Core logic:

Assessment inputs + risk factors + severity level + task type + industry = recommended corrective actions

## Required Modules

- Risk factor checklist
- Corrective action library
- Rule matching engine
- Hierarchy of controls ranking
- Recommendation review page
- Assignment and due date workflow
- Evidence upload
- Status tracking
- Follow up assessment scheduler
- Corrective action PDF report

## Corrective Action Library

Create a reusable database of recommended controls. This library is the heart of the engine and should be editable by authorized administrators.

| **Field**             | **Instruction**                                                                                                           |
|-----------------------|---------------------------------------------------------------------------------------------------------------------------|
| Action title          | Short action name such as Raise box storage height.                                                                       |
| Control type          | Engineering, workstation redesign, tool redesign, lift assist, administrative, staffing, training, follow up observation. |
| Risk factor addressed | Link to risk factor such as bending, twisting, heavy lifting, overhead reach, repetition.                                 |
| Task type             | Manual lifting, pushing/pulling, packaging, patient handling, food service prep, delivery handling, etc.                  |
| Industry              | Warehouse, health care, manufacturing, long term care, food service, delivery, or all industries.                         |
| Reason                | Plain language explanation of why the action is recommended.                                                              |
| Priority level        | Default priority, editable after recommendation.                                                                          |
| Default due date      | Default number of days to completion based on risk level.                                                                 |
| Evidence required     | Photo, video, receipt, note, worker feedback, or follow up observation.                                                   |
| Follow up period      | Default number of days after completion to repeat assessment.                                                             |

## Rule Matching Examples

| **Condition**                                                                            | **Recommended Actions**                                                                                                                                                 |
|------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Task type = manual lifting; risk factor = bending; severity = high; frequency = repeated | Raise storage height; use adjustable lift table; reduce load weight; use cart or dolly; schedule follow up observation.                                                 |
| Risk factor = overhead reach; severity = moderate or high                                | Lower frequently used materials; rearrange storage layout; use height adjustable platform; reduce overhead task frequency; provide shoulder strain reporting education. |
| Risk factor = repetition; task frequency = frequent                                      | Job rotation; micro breaks; tool redesign; staffing support; task pacing review; follow up observation.                                                                 |

## Hierarchy of Controls Ranking

The system must rank recommendations in this order. Training should not appear as the first or only action when higher level controls are available.

- Engineering controls
- Workstation redesign
- Tool redesign
- Lift assist options
- Administrative controls
- Staffing and task pacing
- Training
- Follow up observation

## Recommendation Review Screen

After an assessment is completed, show a page titled Recommended Corrective Actions. Each recommendation should appear as an editable card with:

- Action title
- Control type
- Risk factor addressed
- Priority
- Reason
- Assigned to dropdown
- Due date auto filled but editable
- Evidence required
- Follow up date auto filled
- Status

The Safety Manager should be able to accept, edit, reject with reason, assign responsible person, change due date, upload evidence, mark completed, and request follow up assessment.

## How to Develop It

| **Table**                 | **Purpose**                                                  | **Key Fields**                                                                                                                                                           |
|---------------------------|--------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| corrective_action_library | Stores reusable recommended controls.                        | library_action_id, action_title, control_type, risk_factor_id, task_type, industry, default_priority, default_due_days, evidence_required, follow_up_days, action_reason |
| recommendation_rules      | Matches assessment conditions to library actions.            | rule_id, task_type, risk_factor_id, severity_minimum, industry, frequency_condition, load_condition, recommended_action_id, rule_weight                                  |
| corrective_actions        | Stores recommended and assigned actions for each assessment. | corrective_action_id, assessment_id, library_action_id, assigned_to, due_date, status, evidence_link, completion_date, verified_by, follow_up_assessment_date            |

## Corrective Action Workflow

- Assessment completed.
- Risk factors identified.
- System matches rules and ranks recommendations.
- Safety Manager reviews recommendations.
- Safety Manager accepts, edits, or rejects each recommendation.
- Accepted actions are assigned.
- Supervisor completes action.
- Evidence is uploaded.
- Safety Manager verifies completion.
- Follow up assessment is scheduled.
- Before and after comparison is generated.

## Status Values

- Open
- Assigned
- In progress
- Completed
- Verified
- Rejected
- Overdue

## Acceptance Criteria

- The system generates recommendations from selected risk factors.
- The Safety Manager can accept, edit, reject, and assign recommendations.
- Each corrective action has status tracking, responsible person, due date, evidence upload, and follow up date.
- Completed actions can be linked to before and after comparison reports.

# 6. Before and After Comparison

Build a comparison module that links a baseline assessment to a follow up assessment after corrective action completion. This is a key evidence feature because it shows whether WorkEddy helped document measurable improvement.

## Required Report Fields

- Original task score
- Corrected task score
- Risk level before
- Risk level after
- Body regions improved
- Corrective action completed
- Date of first assessment
- Date of follow up assessment
- Estimated percentage risk reduction

## Workflow

- Every assessment belongs to a task record.
- The first reviewed assessment is marked as baseline and locked.
- Corrective actions are linked to the baseline assessment.
- Once at least one corrective action is completed and verified, the system requests a follow up assessment.
- The follow up assessment uses the same scoring method as the baseline.
- The comparison engine calculates score difference, risk level change, body region improvement, and estimated percentage risk reduction.
- The system generates a side by side report and PDF export.

## Calculation Formula

Estimated risk reduction = ((Original score - Corrected score) / Original score) × 100

Example: Original score 10 and corrected score 6 gives an estimated risk reduction of 40 percent. Use the word estimated because this is a prevention support tool, not a guarantee that injury risk has been eliminated.

## How to Develop It

| **Table**          | **Purpose**                                 | **Key Fields**                                                                                                                                                                                                                                                                |
|--------------------|---------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| tasks              | Stores the task being assessed.             | task_id, organization_id, worksite_id, department, job_role, task_name, task_description, industry                                                                                                                                                                            |
| comparison_reports | Stores before and after comparison results. | comparison_id, task_id, baseline_assessment_id, follow_up_assessment_id, original_score, corrected_score, score_difference, percentage_risk_reduction, risk_level_before, risk_level_after, body_regions_improved, corrective_actions_completed, report_date, pdf_report_link |

## Interface Requirements

Show the baseline and follow up results side by side. Include before screenshot, after screenshot, before heat map, after heat map, corrective action summary, reviewer notes, and Export to PDF button.

## Acceptance Criteria

- No comparison report should generate unless baseline and follow up assessments exist.
- The follow up assessment must use the same scoring method as baseline.
- The baseline must be locked after approval.
- The comparison must be exportable as PDF.

# 7. Worker Voice and Discomfort Reporting

Create a worker feedback form connected to each task assessment. This feature ensures WorkEddy includes worker experience and not only supervisor observation.

## Required Questions

- Where do you feel discomfort?
- How often do you perform this task?
- What part of the task feels most difficult?
- What change would help you do the task safely?
- Do you feel comfortable reporting discomfort?
- Has this task caused pain in the past 7 days?
- Has this task caused pain in the past 30 days?

## Functional Requirements

- Allow anonymous feedback at the organization level.
- If anonymous, do not display the worker’s name in dashboards or reports.
- Allow discomfort selection by body region.
- Allow discomfort intensity rating.
- Aggregate feedback by task, department, body region, and time period.
- Feed de identified feedback trends into pilot dashboard and impact tracker.

## How to Develop It

| **Table**       | **Purpose**                                           | **Key Fields**                                                                                                                                                                                          |
|-----------------|-------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| worker_feedback | Stores worker submitted discomfort and task feedback. | feedback_id, assessment_id, task_id, organization_id, anonymous_status, body_region, discomfort_level, seven_day_pain, thirty_day_pain, task_frequency, suggested_change, comfort_reporting, created_at |

## Acceptance Criteria

- Anonymous worker identity must not be exposed.
- Feedback must be linked to the task and assessment for prevention purposes.
- Reports should summarize feedback without unnecessary personal identifiers.
- Worker discomfort trends should be available by body region and task type.

# 8. Privacy and Trust Features

Build privacy and worker trust protections from the beginning. These features are not optional add ons because WorkEddy must be positioned as a prevention tool, not a worker surveillance or productivity monitoring system.

## Required Privacy Features

- Face blurring
- Worker consent checkbox
- Privacy notice checkbox
- No disciplinary use notice
- No productivity monitoring notice
- De identified reporting
- Role based permissions
- Secure video storage
- Option to delete video after scoring
- Audit log of who viewed each video
- Organization controlled data policy
- Data retention controls

## Required Platform Statement

Display this statement during video upload, feedback submission, and privacy settings review:

WorkEddy is designed for ergonomic risk prevention and safety improvement, not worker discipline or productivity surveillance.

## Consent Capture

Store consent as structured data, including the exact consent text version. This allows the platform to document what the worker or uploader agreed to at the time of submission.

| **Table** | **Purpose**                                        | **Key Fields**                                                                                |
|-----------|----------------------------------------------------|-----------------------------------------------------------------------------------------------|
| consents  | Stores consent decisions and consent text version. | consent_id, user_id, assessment_id, consent_type, consent_text_version, accepted, accepted_at |

## Face Blurring

| **Phase** | **Developer Instruction**                                                                                                                                              |
|-----------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| MVP       | Allow uploader to request face blurring. Mark video as Face Blurring Required before reviewer access.                                                                  |
| Advanced  | Use automated face detection and video processing to blur faces before reviewer access. Store processed and original video separately according to retention settings. |

## Audit Log

Track sensitive activity across the platform. At minimum, log who viewed a video, who downloaded a report, who changed a score, who assigned or verified corrective action, and who exported data.

| **Table**  | **Purpose**                     | **Key Fields**                                                                                 |
|------------|---------------------------------|------------------------------------------------------------------------------------------------|
| audit_logs | Records sensitive user actions. | audit_id, user_id, organization_id, action_type, object_type, object_id, timestamp, ip_address |

## Data Retention Controls

Organization Admins should be able to choose a retention policy. The system should support deleting raw video after scoring, retaining only screenshots, retaining video for pilot evidence, or retaining only de identified data.

## Acceptance Criteria

- No worker video is publicly accessible.
- Every video view is logged.
- Anonymous feedback remains anonymous.
- Research export excludes names, faces, personal identifiers, and raw videos.
- Privacy statement appears before video or feedback submission.
- Organization Admin can configure retention rules.

# 9. Pilot Study Dashboard

Create a dashboard that tracks pilot implementation and evaluation metrics. This dashboard should help WorkEddy generate usable evidence for publications, conference abstracts, expert letters, employer proposals, and NIW exhibits.

## Required Metrics

- Number of worksites enrolled
- Number of workers participating
- Number of task videos uploaded
- Number of assessments completed
- Number of high risk tasks identified
- Number of corrective actions assigned
- Number of corrective actions completed
- Average time to corrective action closure
- Before and after score changes
- Worker feedback trends
- Supervisor feedback trends
- Reviewer agreement rate
- Self reported discomfort trends

## How to Develop It

- Create dashboard cards for total worksites, assessments, high risk tasks, completed corrective actions, average risk reduction, reviewer agreement, discomfort trend, and overdue corrective actions.
- Pull dashboard metrics from assessments, corrective_actions, worker_feedback, comparison_reports, and validation_reviews.
- Include filters for organization, worksite, industry, department, job role, date range, risk level, and body region.
- Allow export of dashboard summary to PDF or Excel for pilot documentation.

| **Table**   | **Purpose**                              | **Key Fields**                                                                                  |
|-------------|------------------------------------------|-------------------------------------------------------------------------------------------------|
| pilot_sites | Stores pilot site enrollment and status. | pilot_site_id, organization_id, worksite_id, enrollment_date, pilot_status, target_worker_count |

## Acceptance Criteria

- Dashboard updates automatically from real platform activity.
- Dashboard can be filtered by site, date range, department, and body region.
- Pilot metrics can be exported for reports and presentations.
- The dashboard shows implementation progress and outcome related metrics separately.

# 10. Validation and Reviewer Module

Create a validation workflow where a human reviewer confirms or adjusts AI estimated or manually entered scores. This strengthens credibility and prevents unsupported AI conclusions from being treated as final.

## Reviewer Actions

- Approve score
- Adjust score
- Add notes
- Flag uncertainty
- Compare AI score with human observation
- Record confidence level
- Attach reviewer credentials

## Fields to Track

- AI score
- Human reviewer score
- Difference between scores
- Reviewer agreement percentage
- Reason for adjustment
- Reviewer name
- Reviewer credential
- Review date

## How to Develop It

| **Table**          | **Purpose**                                            | **Key Fields**                                                                                                                                                                         |
|--------------------|--------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| validation_reviews | Stores reviewer validation record for each assessment. | review_id, assessment_id, reviewer_id, ai_score, reviewer_score, score_difference, agreement_status, confidence_level, reason_for_adjustment, reviewer_notes, credentials, review_date |

## Workflow

- Assessment is submitted.
- AI or manual score is generated.
- Assessment status becomes Pending Review.
- Reviewer reviews video, risk factors, heat map, and score.
- Reviewer approves or adjusts the score.
- Assessment becomes Reviewed and Locked.
- PDF report uses the reviewed final score.

## Acceptance Criteria

- No assessment is marked final until reviewed.
- The system retains both estimated score and reviewed final score.
- The report shows whether the score was AI estimated, manually entered, or reviewer confirmed.
- Reviewer adjustments require a reason or note.

# 11. PDF Report Generator

Create a professional PDF generator for each assessment, corrective action record, before and after comparison, pilot summary, and impact summary. These reports should be suitable for employer proposals, pilot documentation, grant applications, and EB2 NIW evidence.

## Required Report Sections

- WorkEddy logo
- Organization name
- Worksite
- Task name
- Date of assessment
- Assessor name
- Reviewer name
- Task description
- Video screenshot or pose overlay
- Estimated risk score
- Reviewed final score
- Body region heat map
- Key risk factors
- Recommended corrective actions
- Corrective action status
- Before and after comparison if available
- Worker feedback summary
- Methodology note
- Limitations note
- Privacy note
- Audit trail summary

## How to Develop It

- Use backend PDF rendering from HTML templates.
- Create separate templates for assessment report, corrective action report, before and after comparison report, pilot summary report, and research export summary.
- Store generated PDF path and generation date.
- Ensure PDF reports use reviewed final score and not unapproved AI score.
- Include privacy and limitations notes in every assessment report.

## Acceptance Criteria

- PDF includes reviewed final score.
- PDF includes date generated and report type.
- PDF includes privacy and limitations notes.
- PDF excludes raw worker identifiers unless authorized.
- PDF can be regenerated after corrected data is approved.

# 12. De Identified Research Export

Build an export feature that allows authorized users to export de identified data as CSV and Excel. This feature supports pilot evaluation, research, conference abstracts, and NIW evidence while protecting workers.

## Required Export Fields

- Organization ID, de identified
- Worksite ID, de identified
- Industry
- Department
- Job role
- Task type
- Assessment date
- Risk score
- Risk level
- Body region affected
- Corrective action type
- Corrective action status
- Follow up date
- Before score
- After score
- Worker discomfort report
- Reviewer agreement
- Notes, de identified

## How to Develop It

- Create a de identification service that removes direct identifiers before export.
- Use generated codes such as ORG001, SITE001, TASK001, and WORKER001 only where worker level export is authorized.
- Exclude names, emails, faces, raw videos, street addresses, and identifying free text notes.
- Allow authorized users to select date range and filters before export.
- Show a field preview before export.
- Log every export in the audit log.
- Generate temporary download links rather than permanent public links.

## Acceptance Criteria

- Only authorized users can export data.
- Export excludes names, faces, emails, raw videos, and direct personal identifiers.
- Every export is recorded in audit log.
- CSV and Excel exports use clear column labels and consistent date formats.

# 13. Public Health Impact Tracker

Create a dashboard called Impact Tracker. It should show observed platform activity and clearly labeled estimated impacts without overclaiming.

## Required Metrics

- High risk tasks identified
- High risk tasks reduced
- Corrective actions completed
- Average risk reduction
- Departments improved
- Workers reached
- Repeat high risk tasks
- Potential injuries prevented, marked as estimate
- Potential lost workdays avoided, marked as estimate
- Potential cost savings, marked as estimate

## How to Develop It

- Pull data from assessments, corrective_actions, comparison_reports, worker_feedback, tasks, and worksites.
- Separate observed metrics from estimated impact metrics.
- Use cautious labels such as estimated, potential, preliminary, observed improvement, and risk reduction estimate.
- Allow export of impact tracker summary as PDF.

## Language Rules

| **Use**                                      | **Avoid**                     |
|----------------------------------------------|-------------------------------|
| Estimated risk reduction                     | Guaranteed injuries prevented |
| Potential injuries prevented                 | Confirmed cost savings        |
| Preliminary platform findings                | Eliminated risk               |
| Observed improvement after corrective action | OSHA compliance achieved      |

## Acceptance Criteria

- Dashboard distinguishes observed activity from estimated impact.
- Estimated metrics are clearly labeled.
- User can export an impact summary PDF.
- No metric should imply guaranteed injury prevention.

# 14. National Importance Dashboard

Create a platform page called Why WorkEddy Matters for Workforce Health. This page should explain the larger workforce problem WorkEddy addresses and connect platform data to national occupational health priorities.

## Required Topic Areas

- Musculoskeletal strain
- Warehouse work
- Health care support work
- Manual material handling
- Long term care
- Food service
- Manufacturing
- Delivery work
- Repetitive and high strain jobs

## Two Layer Dashboard Structure

| **Layer**               | **Purpose**                                                                                       | **Data Source**                                                         |
|-------------------------|---------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------|
| Static national context | Allows authorized admins to add national injury statistics, industry burden data, and references. | Admin entered source fields                                             |
| Dynamic WorkEddy data   | Shows aggregate platform activity and trends from WorkEddy usage.                                 | Assessments, tasks, body region scores, corrective actions, comparisons |

## Static National Context Fields

- Statistic title
- Statistic value
- Source name
- Source year
- Source link
- Industry relevance
- Date updated

## Dynamic WorkEddy Metrics

- Number of industries represented
- Number of worksites assessed
- Number of high risk tasks identified
- Most common body regions affected
- Most common corrective actions
- Average risk reduction after correction
- Worker discomfort trends

## How to Develop It

| **Table**                  | **Purpose**                                               | **Key Fields**                                                                         |
|----------------------------|-----------------------------------------------------------|----------------------------------------------------------------------------------------|
| national_statistics        | Stores national context statistics and sources.           | statistic_id, title, value, source_name, source_year, source_url, category, date_added |
| platform_aggregate_metrics | Stores generated aggregate metrics for dashboard display. | metric_id, metric_name, value, date_range, industry, generated_at                      |

## User Interface Requirements

- National problem summary section.
- Industry risk cards.
- WorkEddy platform activity cards.
- Common high strain tasks chart.
- Body region burden chart.
- Corrective action outcomes chart.
- Future research section.
- Export to PDF button.

## Acceptance Criteria

- National statistics are editable by authorized admins.
- Every statistic has source fields.
- Internal WorkEddy metrics update automatically.
- Dashboard supports PDF export for proposals and NIW evidence.
- Dashboard separates public national data from internal platform data.

# 15. Methodology and Limitations Page

Create a platform page called Methodology and Limitations. This page should make WorkEddy scientific, responsible, transparent, and credible. It should also appear as a brief note in PDF reports.

## Required Sections and Content

| **Section**                              | **Required Content**                                                                                                                                                                     |
|------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| What WorkEddy measures                   | Task level ergonomic risk factors including posture, force, repetition, reach, bending, twisting, manual handling, and reported discomfort.                                              |
| Scoring systems that inform the platform | REBA informed whole body risk, RULA informed upper limb risk, NIOSH lifting principles, manual material handling risk factors, worker discomfort feedback, and reviewer validation.      |
| How AI assisted scoring works            | AI may estimate posture or movement risk. AI outputs are not final by default. AI scores require human review. Confidence levels and model version should be stored.                     |
| Why reviewer validation is included      | Reviewer validation improves credibility, prevents unsupported AI conclusions, and documents professional judgment.                                                                      |
| What WorkEddy does not claim             | No guarantee of injury prevention, no medical diagnosis, no legal compliance certification, and no replacement for professional ergonomic judgment.                                      |
| How privacy is protected                 | Consent capture, face blurring, role based access, secure storage, audit logs, de identified exports, no disciplinary use statement, and no productivity monitoring statement.           |
| How data supports prevention planning    | Task scores identify high risk activities; body region heat maps show strain patterns; corrective actions create accountability; follow up assessments document improvement.             |
| How pilot evidence will be collected     | Baseline assessments, corrective actions, follow up assessments, before and after comparisons, worker feedback, reviewer agreement, risk reduction estimates, and de identified exports. |

## How to Develop It

- Build this page as a structured CMS style admin page.
- Allow authorized admins to edit section text and add references.
- Save version history whenever methodology language changes.
- Allow methodology sections to be included as short notes in PDF reports.
- Use careful, non overclaiming language throughout the page.

| **Table**              | **Purpose**                                               | **Key Fields**                                                                                |
|------------------------|-----------------------------------------------------------|-----------------------------------------------------------------------------------------------|
| methodology_sections   | Stores editable methodology content with version history. | section_id, section_title, section_body, version_number, updated_by, updated_at               |
| methodology_references | Stores references linked to methodology sections.         | reference_id, section_id, source_title, source_author, source_year, source_url, citation_text |

## Acceptance Criteria

- The page is visible inside the platform.
- PDF reports include a methodology and limitations note.
- The page avoids overclaiming and clearly states limitations.
- The platform saves version history when methodology content changes.
- References can be added and edited by authorized admins.

# Final MVP Build Order and Acceptance Gate

## Recommended Build Order

- User roles and organization setup
- Task records and worksite structure
- Task video upload
- Task assessment form
- Manual ergonomic scoring
- Body region heat map
- Corrective action library
- Rules based recommendation engine
- Corrective action workflow
- Before and after comparison
- Worker feedback form
- PDF report generator
- Privacy controls and audit trail
- Pilot dashboard
- De identified export
- Impact tracker
- National importance dashboard
- Methodology and limitations page
- AI assisted posture scoring integration

## Final Instruction 

Build WorkEddy as a prevention evidence system. Every feature should help answer whether the platform identifies ergonomic risk, helps organizations act on that risk, documents measurable improvement, protects worker privacy, scales across industries, and produces credible evidence for pilots, research, employer adoption, grant applications, and evidence support.

## Final Acceptance Gate

- Core workflows work from task creation to report generation.
- No sensitive video or worker feedback is publicly accessible.
- All final assessment scores can be traced to source inputs and reviewer validation.
- Corrective actions can be assigned, completed, verified, and linked to follow up assessment.
- Before and after reports calculate estimated risk reduction correctly.
- PDF reports include methodology, limitations, and privacy notes.
- Research exports are de identified and logged.
- Dashboards update from real system data rather than static placeholders.
