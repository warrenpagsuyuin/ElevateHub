# ELEVATE DISCIPLESHIP GROUP MANAGEMENT SYSTEM

## USER MANUAL

**System name:** Elevate Discipleship Group Management System: Predictive Analytics and Sentiment Analysis for Seeker Tracking and Member Engagement  
**Version:** Current Laravel application build, reviewed 17 September 2026  
**Date:** 17 September 2026  
**Prepared by:** Project documentation  
**Intended users:** DGM Administrators, satellite/district staff, leaders, volunteers, members, and authorized system evaluators

> **Source-of-truth note:** This manual describes the running Laravel application and its current code. It does not assume that every feature mentioned in a thesis is implemented. Items that are absent, placeholder-only, or incomplete are listed in Section 25.

## 1. INTRODUCTION

The system stores and organizes information used for discipleship-group ministry. It supports:

- recording members and their satellite information;
- recording and tracking seekers;
- assigning seekers to a district/satellite and an active DGroup Leader;
- converting an assigned seeker into a new member;
- recording follow-ups for seekers and members;
- reviewing member engagement classifications generated from recorded indicators;
- viewing the CART result and its decision explanation;
- assigning human-reviewed follow-up recommendations;
- collecting system evaluation feedback;
- analyzing eligible feedback with a multilingual lexicon/rule-based sentiment engine; and
- reviewing research datasets, model evaluation results, and exports.

The system is decision support. It does not replace pastoral judgment, human review, or appropriate safeguarding procedures.

## 2. USER ROLES

The application uses the `role` value on the user account and the user's district assignment. The account-creation form offers `leader`, `staff`, `volunteer`, `member`, and `satellite`. Existing accounts may also use `admin`, `district`, `district_user`, or `satellite_user`.

| User role | Main responsibilities | Access | Important limits |
|---|---|---|---|
| Administrator (`admin`) | Manage system-wide data, users, satellites, analytics, research review, and recommendation catalogue | Admin Dashboard, Satellites, Members, Seekers, Users, Reports, Analytics, Sentiment Analysis, Feedback | Admin pages are restricted by `role:admin`. The user form does not create another admin account. |
| Satellite management user (`district`, `district_user`, `satellite`, `satellite_user`, `leader`, `staff`) | Manage records for the assigned satellite/district | Satellite Dashboard, Members, Seekers, Follow-Ups, Engagement, Recommendations, Feedback | Can only access the satellite whose slug matches the account's district assignment. Manage actions require one of the management roles listed above. |
| Satellite view-only account or other authenticated role | View permitted pages and submit feedback | Access depends on the account's district and role | View-only users cannot create/update/delete satellite records, generate classifications, or assign recommendations. |
| Volunteer/member/normal account | Use the general authenticated area and submit feedback | `/home` and Feedback; a satellite shell may appear if a district is assigned | No admin access. No satellite management unless the account also has an approved management role. |

A user must have both an authenticated session and the correct role/district relationship. A failed authorization check returns an unauthorized page.

## 3. HOW TO ACCESS THE SYSTEM

### Login
**Purpose:** Sign in using the login method assigned to the account.

**Steps:**

1. Open a supported web browser.
2. Go to the system URL, or open `/` or `/login`.
3. Enter the registered email address.
4. Click **Continue** or the email-check button.
5. Administrators enter their password on the next step.
6. Non-administrator accounts receive a six-digit one-time password (OTP) by email.
7. Enter the six-digit OTP and submit it.
8. The system redirects the user to the correct dashboard.

**Administrator sign-in:** Email plus password.  
**Non-administrator sign-in:** Email plus OTP. The OTP expires after five minutes. Use **Resend OTP** when available. Use the reset/back option to restart the login process.

**Expected result:** Administrators go to `/admin/dashboard`. An assigned satellite user goes to `/satellites/{satellite}/dashboard`. Other authorized roles go to their general dashboard.

**Common login messages:**

- Unknown email: no account was found.
- Incorrect administrator password: enter the password again.
- Invalid or expired OTP: request a new code.
- OTP unavailable: contact the system administrator if email delivery fails.

**Logout:** Click **Sign Out** in the sidebar. The session is invalidated and the login page opens.

**Screenshot placeholder: Login Page**

## 4. DASHBOARD

### Administrator Dashboard
**Route:** `/admin/dashboard`  
**Menu:** **Dashboard**

The current administrator dashboard displays four movement-wide cards:

| Card | Meaning |
|---|---|
| **DGROUP LEADERS** | Active member records whose Dgroup Role is `Dgroup Leader`. |
| **DGROUPS** | The current application represents one active DGroup per active DGroup Leader, so this total equals the active DGroup Leader total. |
| **DGROUP MEMBERS** | Active member records whose Dgroup Role is `Member`. |
| **CAMPUSES** | The number of satellite records. In this build, satellites are the organizational records used for districts/campuses. |

Each card also shows the current hub breakdown: **MAIN**, **NCP**, and **ICP**. A hub with no matching records displays zero.

**Important DGroup rule:** The application does not have a separate `dgroups` database table or standalone DGroup management screen. DGroups are represented through active DGroup Leader member records and member-to-leader name assignments. Therefore, the dashboard's DGroup number is data-derived from active leaders, not a cosmetic fixed number.

**Screenshot placeholder: Administrator Dashboard**  
Capture `/admin/dashboard` with all four cards and the MAIN/NCP/ICP breakdown visible.

### Satellite Dashboard
**Route:** `/satellites/{satellite}/dashboard`  
**Menu:** **Dashboard**

A satellite dashboard shows the selected satellite's DGroup Members, DGroup Leaders, Seekers, and New Members. It also shows quick actions, assigned accounts, engagement summary, seeker summary, and recent follow-up activity.

The month selector displays the current month and the available months, but the page does not provide a separate historical month-report workflow.

## 5. USER MANAGEMENT

### 5.1 View Users
**Who can use it:** Administrator only.  
**Route:** `/admin/users`  
**Menu:** **Users**

1. Open **Users**.
2. Review Name, Email, Role, and District.
3. Search by name or email.
4. Filter by Role or District.
5. Click a column heading to sort.
6. Use the page controls to change the page size: 10, 25, 50, or 100.
7. Click **Export CSV** to export the filtered user list.

The page excludes administrator accounts from the visible user directory.

### 5.2 Add User
1. Open **Users**.
2. Click **Add User** or the plus/new-user control.
3. Enter **Name**. Required.
4. Enter **Email**. Required and must be unique and valid.
5. Select **Role**: `leader`, `staff`, `volunteer`, `member`, or `satellite`.
6. Select an optional **District** from the available satellite names.
7. Submit the form.

The system creates the account with an initial application password value. Confirm the organization's password-delivery procedure with the administrator before giving the user access.

### 5.3 Edit User
1. Open **Users**.
2. Open the user's account/details page.
3. Select a new **District**, or clear the district assignment.
4. Save the change.

The current edit action changes the district assignment only. It does not provide a general role, email, name, password, activation, or deactivation editor.

### 5.4 Bulk District Assignment
1. Select users, or choose the matching filtered selection.
2. Choose the bulk action to assign a district.
3. Select the district or clear the assignment.
4. Submit the action.

### 5.5 Delete, Activate, and Deactivate
These actions are not implemented in the current administrator user interface. The system has no user-delete or account-status control in this workflow.

## 6. MEMBER MANAGEMENT

### 6.1 Administrator Member Breakdown
**Route:** `/admin/members`  
**Menu:** **Members**

The administrator page is a satellite/member breakdown, not a full individual-member editor. It shows Total Members, Total Satellites, Total Leaders, and New Members, followed by satellite rows.

1. Search satellite names in **Search satellites...**.
2. Sort by Satellite, Members, or New Members.
3. Click the row action menu for **View members**, **View satellite details**, or **Export satellite members**.
4. Use pagination and **Export CSV**.

### 6.2 Satellite Member List
**Route:** `/satellites/{satellite}/members`  
**Menu:** **Members**

1. Open the satellite **Members** page.
2. Search using the member search field.
3. Filter by Status, Dgroup Role, Campus/School, District, or Area when available.
4. Sort by Name, Status, Dgroup Role, or Campus/School.
5. Open the detail control beside a member to review the stored profile.
6. Use the row edit action when authorized.

Available page sizes are 10, 25, 50, and 100.

### 6.3 Add Member
**Who can use it:** A user who can manage the satellite, including an administrator only where the current satellite page grants access, or an authorized satellite management user.

The current member form contains these fields:

- Status: `Active`, `Inactive`, or `New`.
- Dgroup Role: `Dgroup Leader` or `Member`.
- Last Name.
- First Name.
- Full Name.
- Gender: `Male` or `Female`.
- Email Address.
- Contact Number.
- Facebook Link.
- Birthdate.
- Age.
- District.
- Area.
- Campus/School.
- Name of DLeader.
- No. of Active Disciples.
- Year Joined.
- Ministry Involvement.
- How many Dgroups do you lead each week?
- Generation.
- Refresh Date.
- Year.
- Month.

1. Open **Members** for the correct satellite.
2. Click **Add Member**.
3. Complete the required fields shown by the form. The form validates the selected role, status, email, dates, URLs, and numeric limits.
4. For a DGroup member, enter the **Name of DLeader** when known.
5. For a leader, select **Dgroup Leader** and do not use the member's own name as their DGroup leader.
6. Save the member.

The system refreshes the satellite member, new-member, and leader counters after saving.

### 6.4 Edit Member
1. Open the satellite's **Members** page.
2. Open the member row's edit action.
3. Update the required information.
4. Save.

### 6.5 Delete Member
Authorized satellite managers can delete a member through the member action. Verify the record before deleting. Deletion is a database delete; the current form does not provide a separate member archive workflow.

### 6.6 Import Members
1. Open the satellite **Members** page.
2. Use **Download Import Template** when a template is needed.
3. Prepare a CSV or XLSX file using the supported headers.
4. Choose the file and click **Preview Import**.
5. Review valid rows, duplicate rows, and issues.
6. Submit **Import Members** only after checking the preview.

The supported import headers are: Status, Dgroup Role, Last Name, First Name, Full Name, Gender, Email Address, Contact Number, Facebook Link, Birthdate, Age, District, Area, Campus/School, Name of DLeader, No. of Active Disciples, Year Joined, Ministry Involvement, How many Dgroups do you lead each week?, Generation, Refresh Date, Year, and Month.

### 6.7 Assign a Member to a DGroup
There is no standalone DGroup picker or DGroup ID field. Assignment is stored through the member's **Name of DLeader** field. To assign a member, edit the member and enter/select the appropriate active leader name where the page provides the leader option.

## 7. DGROUP MANAGEMENT

### What is implemented
The application currently manages DGroup information through:

- active member records with Dgroup Role `Dgroup Leader`;
- member records with a `Name of DLeader` value;
- satellite leader and member counters;
- seeker-to-leader assignment; and
- the administrator dashboard relationship of one active leader to one active DGroup.

### What is not implemented
There is no standalone **DGroups** menu, DGroup table, DGroup name form, DGroup create button, DGroup archive/delete control, or DGroup transfer screen.

### 7.1 Review DGroups Through the Current Data
1. Open **Members** for a satellite.
2. Filter **Dgroup Role** to `Dgroup Leader` to review leader records.
3. Review member rows and their **Dgroup Leader** values.
4. Open the administrator dashboard to review movement-wide DGroup totals.

### 7.2 Current Leader Rule
The corrected dashboard uses:

**1 active DGroup Leader = 1 active DGroup**

A valid leader must be an active member with Dgroup Role `Dgroup Leader`. The current application does not create an additional DGroup row when a leader is added; the leader record is the DGroup representation.

### 7.3 Assign a Leader to a Seeker
1. Open the satellite **Seekers** page.
2. Locate the seeker.
3. Choose the leader assignment control.
4. Select an active DGroup Leader from the same satellite.
5. Save the assignment.

The leader must be active and belong to the satellite. A leader from another satellite is not accepted.

### 7.4 Add a Member Under a Leader
1. Open the satellite **Members** page.
2. Add or edit the member.
3. Set Dgroup Role to `Member`.
4. Enter the active leader in **Name of DLeader**.
5. Save.

### DGroup limitations
The current system does not provide the requested standalone steps **Create DGroup**, **Assign Leader to DGroup**, **Transfer Member**, **Edit DGroup**, or **Archive/Delete DGroup**. Use the member and leader fields only as currently implemented.

**Screenshot placeholders:**

- **DGroup view through Members:** satellite member list filtered to Dgroup Leader.
- **Leader assignment:** seeker page with the active leader selector open.

## 8. SEEKER MANAGEMENT

### 8.1 Administrator Seeker List
**Route:** `/admin/seekers`  
**Menu:** **Seekers**

1. Open **Seekers**.
2. Search by name, email, or school.
3. Filter by status, district, or school.
4. Sort by Name, Email, School, District, or Status.
5. Use bulk selection to assign a district or change status.
6. Click **Sync Google Sheets** only when the configured source is available.
7. Use **Export CSV** for the filtered list.

The administrator page does not provide a general add-seeker form. New records are expected from the configured Google Sheets synchronization or existing data workflows.

### 8.2 Satellite Seeker List
**Route:** `/satellites/{satellite}/seekers`  
**Menu:** **Seekers**

The page shows active, unconverted, non-archived seekers for the satellite. It includes a pending notification count, seeker status control, DGroup Leader assignment, and conversion action for authorized managers.

### 8.3 Seeker Fields
The stored seeker model supports:

- Name, Last Name, First Name;
- Email, Gender, Contact Number, Facebook Link;
- Birthdate and Age;
- School and Area;
- Active Disciples, Year Joined, Ministry Involvement, Weekly Dgroups Led, Generation;
- Refresh Date, Year, and Month;
- Assigned District and Assigned Satellite;
- Dgroup Leader Name;
- Status; and
- conversion and archive markers.

### 8.4 Seeker Statuses
The actual statuses are:

- **Pending**
- **Successful**
- **Unsuccessful**

Changing a seeker to **Unsuccessful** moves the seeker to the archive and removes it from the active seeker list. A successful conversion marks the seeker Successful and records the converted member.

### 8.5 Assign District and Leader
1. Choose the correct satellite/district.
2. Assign the seeker to that district.
3. Select an active DGroup Leader from the same satellite.
4. Save.

### 8.6 Convert Seeker to Member
1. Assign a DGroup Leader first.
2. Open the seeker's conversion control.
3. Enter Last Name and First Name.
4. Enter required Email Address, District, and Campus/School.
5. Complete optional member information as available.
6. Submit the conversion.

The system creates a new member with Status `New`, Dgroup Role `Member`, and the selected DGroup Leader. The seeker becomes Successful and stores the new member ID. A seeker already converted cannot be converted again.

### 8.7 Seeker Source Tracking
The seeker model stores `source_key`, but the current user-facing seeker workflows do not expose a complete source/event/referral management form. Do not describe source categories that are not shown by the current page.

## 9. FOLLOW-UP MANAGEMENT

### 9.1 View Follow-Ups
**Route:** `/satellites/{satellite}/follow-ups`  
**Menu:** **Follow-Ups**

The page displays follow-up target, date, method, status, notes, sentiment, sentiment confidence, and a sentiment explanation when available.

Available filters:

- Status
- Method
- Sentiment

Available statuses:

- `Pending`
- `Completed`
- `Cancelled`

Available methods:

- `Phone`
- `Email`
- `In-Person`
- `Other`

### 9.2 Create Follow-Up
1. Open **Follow-Ups**.
2. Click **New Follow-Up**.
3. Select a target: a seeker or member.
4. Enter an optional **Follow-Up Date**.
5. Select the required **Method**.
6. Select the required **Status**.
7. Enter optional **Notes** of no more than 1,000 characters.
8. Click **Create Follow-Up**.

The system analyzes the note after saving when text is available.

### 9.3 Edit or Delete Follow-Up
1. Open **Follow-Up History**.
2. Expand **Edit Follow-Up** for the record.
3. Change date, method, notes, or status.
4. Save the update.

Authorized managers can delete a follow-up. Check the record before deleting because the current workflow does not provide a separate archive button.

### 9.4 Analyze Follow-Up Sentiment
Click **Analyze Sentiment** on the Follow-Up History page. The system updates sentiment for the satellite's follow-up entries. Results are Positive, Neutral, Negative, or Unanalyzed when no usable result exists.

**Screenshot placeholder: Follow-Up Management**  
Capture the filters, sentiment summary, New Follow-Up button, and one editable row.

## 10. MEMBER ENGAGEMENT CLASSIFICATION

**Route:** `/satellites/{satellite}/engagement`  
**Menu:** **Engagement**

The system uses a pruned Classification and Regression Tree (CART) model when a current approved evaluation is available. The output is decision support only.

The displayed classifications are:

- **Active:** available recorded indicators are consistent with the Active class.
- **Moderately Active:** available recorded indicators are consistent with the Moderately Active class.
- **Possible Need for Follow-Up:** available recorded indicators suggest authorized human review may be useful.
- **Insufficient Information:** there is not enough usable data for a reliable classification.

The classification does not measure faith, spiritual maturity, character, emotional condition, or personal worth.

### Generate classifications
1. Open **Engagement** for the satellite.
2. Review the summary cards and current result table.
3. If you have management permission, click **Generate Classifications**.
4. Wait for the success message.
5. Use **View Result** beside a member to inspect the result.

A member may show **Unclassified** or **No Engagement Result Available** when no result has been generated or no current model result exists.

## 11. EXPLAINABLE AI / XAI

The member engagement detail page explains the stored result rather than merely showing a label.

**Route:** `/satellites/{satellite}/engagement/{member}`

Review these areas:

- classification and confidence;
- model name and model version;
- generated date;
- data sufficiency;
- whether human review is required;
- factors used and their recorded values; and
- the CART Decision Path.

The decision path lists the recorded value, any training-fitted replacement for missing data, the threshold, branch taken, node, and final leaf. For example, a rule such as `completed follow-ups in the observation window <= threshold` means that the model compared the recorded completed-follow-up count with the stored threshold. It does not prove why a person behaved as they did.

The training-leaf confidence is a model output, not a calibrated probability that a person is truly engaged.

**Required interpretation:** Review the underlying records and use human judgment before action. The system does not automatically contact, discipline, exclude, or change a member because of a classification.

**Screenshot placeholder: Explainable Engagement Result**  
Capture the classification banner, Factors Used table, and expanded CART Decision Path.

## 12. FOLLOW-UP RECOMMENDATIONS

Recommendations are human-reviewed actions associated with an engagement result.

### 12.1 Create/Assign Recommendation
1. Generate an engagement result for the member.
2. Open the member's engagement detail.
3. Select an approved action from the recommendation options.
4. Select an authorized assignee.
5. Add optional assignment notes and an expiry date.
6. Submit the recommendation.

A member can have only one pending recommendation review for the same engagement result. The system does not automatically contact the member or change member status.

### 12.2 Review Recommendation
**Route:** `/satellites/{satellite}/recommendations`  
**Access:** Satellite managers, or the assigned reviewer for the recommendation.

1. Open **Recommendations** from the available engagement/recommendation link.
2. Search or filter by member, action, assignee, or status.
3. Open the recommendation.
4. Read the engagement result and action wording.
5. Choose a review status.
6. Add review notes.
7. If choosing **Modified**, enter the modified action description.
8. Save the review.

Recommendation statuses are:

- **Pending Review**
- **Approved**
- **Modified**
- **Rejected**
- **Expired**

Review history records who reviewed the recommendation and when. Approved action wording comes from the administrator-maintained Recommendation Catalogue.

### 12.3 Maintain Recommendation Catalogue
**Route:** `/admin/recommendation-actions`  
**Admin access:** **Recommendation Catalogue** is reached from the administrator analytics area when linked by the current interface.

Administrators can add or edit an action with:

- Action Title;
- Action Description;
- optional Condition Metadata JSON;
- Activate action; and
- Approval confirmed.

Only active/approved actions are available for recommendation assignment. The catalogue has search, status filters, sorting, pagination, and CSV export.

**Screenshot placeholder: Follow-Up Recommendation Review**

## 13. SYSTEM FEEDBACK

**Routes:** `/feedback/create` and `/feedback`  
**Menu:** **Feedback**

Any authenticated user who can see Feedback can submit system evaluation feedback.

### Submit feedback
1. Open **Feedback**.
2. Click **New Feedback**.
3. Answer all five required questions:
   - How easy was it to use the developed system?
   - How helpful were the member, seeker, and follow-up workflows?
   - How clear were the engagement classification and explanation results?
   - What problems, confusion, or missing features did you notice?
   - Overall, how satisfied are you with the developed system?
4. Add optional additional comments.
5. Choose whether to grant optional research/evaluation consent.
6. Submit the form.

The current form uses written answers. It does not currently display a separate numeric 1-5 rating scale.

### Consent
- **Granted:** the feedback may be eligible for the research review process, subject to later human eligibility review.
- **Denied:** the feedback remains stored for the submitter's history but must not be treated as eligible research feedback.

Do not grant consent on behalf of another respondent. A respondent can update their own consent from **My Feedback** by selecting the actual decision, confirming the consent statement, and clicking **Save consent**.

## 14. SENTIMENT ANALYSIS

The system analyzes feedback and follow-up notes using a **multilingual lexicon- and rule-based sentiment method**. It is not a transformer or generative AI system.

The sentiment labels are:

- Positive
- Neutral
- Negative

### User feedback result
Open **My Feedback** to see:

- submission date;
- predicted sentiment;
- confidence when available;
- original feedback excerpt;
- research consent status; and
- the stored “Why This Sentiment?” decision path.

### Administrator sentiment page
**Route:** `/admin/analytics/sentiment`  
**Menu:** **Sentiment Analysis**

The administrator can view feedback counts, engine/version information, consent and inclusion warnings, eligible sentiment totals by language, and the research review list. The page provides:

- **Import new stored feedback**;
- **Predict / Evaluate reviewed feedback**;
- **Export Sentiment Evaluation Data**; and
- links to **Human Feedback Review** and **Evaluation Dataset**.

Predictions do not replace human labels. The current system deliberately hides the system prediction from a reviewer until the independent human review is saved.

## 15. SENTIMENT RESEARCH REVIEW

**Review route:** `/admin/analytics/sentiment/research/review`  
**Dataset route:** `/admin/analytics/sentiment/research/dataset`

### 15.1 Configure the approved evaluation period
1. Open **Sentiment Analysis**.
2. Enter Period Start and Period End in `YYYY-MM-DD` format.
3. Enter the Approval Evidence/reason.
4. Confirm the attestation that the dates are approved.
5. Click **Save approved period**.

### 15.2 Synchronize feedback
Click **Import new stored feedback**. This imports existing feedback analysis units for eligibility review. It does not invent human labels.

### 15.3 Review one analysis unit
Open **Review** for a feedback unit and inspect:

- original feedback;
- consent decision and evidence;
- system relevance;
- record type: Actual evaluation feedback or Developer/test feedback;
- human-reviewed language;
- Human Reference Sentiment;
- Review Status;
- review independence;
- reason/notes; and
- quote permission.

Actual selectable values are:

- **System relevance:** Relevant to developed system, Needs clarification, Not relevant / exclude.
- **Language:** English, Filipino, Filipino-English.
- **Human sentiment:** Positive, Neutral, Negative.
- **Review status:** Final, Needs Clarification.
- **Independence:** Blinded, Blinded re-review.

A reviewer must attest that the human reference and language were assigned independently of the system prediction. This protects the evaluation from changing labels just to improve accuracy.

### 15.4 Split genuinely separate opinions
Use **Create analysis units** only when the original response contains genuinely separate opinions. Preserve all original wording and separate clauses with a line containing only `---`. Each child unit requires its own review; the original remains stored and excluded.

### 15.5 Evaluate and export
1. Complete the required eligibility and human review fields.
2. Click **Predict / Evaluate reviewed feedback**.
3. Review the tables for counts, language, reference labels, predictions, and evaluation metrics.
4. Click **Export Sentiment Evaluation Data** to download the XLSX research export.

## 16. ENGAGEMENT RESEARCH REVIEW AND SYSTEM EVALUATION

### Engagement reference review
**Routes:** `/admin/analytics/engagement/review` and `/admin/analytics/engagement/dataset`

This page is for authorized administrators/research reviewers. It provides:

- reference records and source;
- verification status and review status;
- reference labels: Active, Moderately Active, Possible Need for Follow-Up, or Needs Clarification;
- evidence snapshots and observation window;
- per-satellite distributions;
- training and held-out test split; and
- CART evaluation metrics when an evaluation exists.

A reference record can be opened for **Authorized human verification**. The reviewer selects an outcome, enters actual review evidence/reason, confirms the attestation, and clicks **Save human verification**. Verification creates audit history and invalidates the active model revision when the reference data changes.

### Train and finalize
- **Train / Evaluate CART:** runs the current evaluation workflow.
- **Mark Human Verified Final:** becomes available only when the dataset's required records have the necessary verified status, reviewer, timestamp, and reason.
- **Export Engagement Evaluation Data:** downloads the current XLSX evaluation data.

If the page says **No reference dataset prepared**, the dataset-preparation command and additive database migration have not been completed. This is an administrator/developer setup task, not a normal staff workflow.

### System Evaluation form
The feedback form in Section 13 is the implemented system-evaluation tool. It contains five required written questions and optional comments. There is no separate current 1-to-5 rating control in the form.

## 17. REPORTS

### Satellites report
**Route:** `/admin/satellites`  
Shows satellite/district records, hubs, assigned users, leaders, members, seekers, and new members. It supports filtering, sorting, pagination, and CSV export.

### Member breakdown report
**Route:** `/admin/members`  
Shows member totals by satellite, new members, and leaders. It supports satellite search, sorting, pagination, and CSV export.

### Seeker report
**Route:** `/admin/seekers`  
Shows seeker records with search, status/district/school filters, bulk actions, Google Sheets synchronization, and CSV export.

### Reports Dashboard
**Route:** `/admin/reports`  
This page is currently a placeholder-style satellite report. Its summary counters are set to zero and rows display **No reports** with zero report count. The CSV export likewise exports no-report rows. Do not treat it as a completed follow-up or DGroup reporting system.

### Analytics reports
**Route:** `/admin/analytics`  
The overview includes chart/report surfaces for MAIN, NCP, and ICP. The current `AdminAnalyticsController` supplies fixed historical chart arrays rather than calculating those charts from the live database. Treat them as configured historical/demo analytics unless the administrator confirms the data source.

### Engagement evaluation report
The Engagement Research **Evaluation Dataset** page provides tables, model metrics, confusion matrix, per-class performance, training/held-out split, and an XLSX export.

### Sentiment evaluation report
The Sentiment Research **Evaluation Dataset** page provides consent/inclusion counts, language tables, human reference and system prediction evaluation, missing-data/exclusion details, and an XLSX export.

## 18. EXPORTING DATA

| Page | Export | Format |
|---|---|---|
| Satellites | **Export CSV** | CSV |
| Members | **Export CSV** or satellite member export | CSV |
| Seekers | **Export CSV** | CSV |
| Users | **Export CSV** | CSV |
| Reports | **Export CSV** | CSV, currently no-report rows |
| Recommendation Catalogue | **Export CSV** | CSV |
| Engagement Research | **Export Engagement Evaluation Data** | XLSX |
| Sentiment Research | **Export Sentiment Evaluation Data** | XLSX |
| Table 9 engagement distribution | **Print Table 9 / Save PDF** | Browser print/PDF |

General export steps:

1. Open the required page.
2. Apply search and filters first.
3. Check the visible result count.
4. Click the page's export button.
5. Save the downloaded file according to the organization's privacy policy.

Do not email or upload exports to unauthorized locations. Exports can contain personal, contact, ministry, or research information.

## 19. SEARCHING, FILTERING, AND PAGINATION

The standard table controls work as follows:

1. Enter a search term in the page's search field.
2. Select a status, role, district, school, method, or sentiment where available.
3. Click **Apply Filters**, or wait for an automatic filter where the page uses auto-submit.
4. Click **Clear**, **Clear Filters**, or **Reset** to remove filters.
5. Click a column heading to sort ascending or descending.
6. Use **Previous**, a page number, or **Next** to move through results.
7. Select 10, 25, 50, or 100 results per page where the page offers that control.

A no-results message usually means the current filters do not match a record. Clear filters before creating a duplicate record.

## 20. AUDIT LOGS

There is no general-purpose Audit Logs menu in the current sidebar. Research audit history is implemented inside the research workflows:

- Engagement reference review shows reference audit history.
- Recommendation review history records recommendation assignment and review events.
- Sentiment research stores review history and reviewer information.

Research audits can include event, previous state, new state, actor/reviewer, and timestamp. Treat these pages as restricted research records.

## 21. ROLE-BASED ACCESS MATRIX

| Function | Admin | Satellite management user | Satellite view-only user | General member/volunteer |
|---|---:|---:|---:|---:|
| Administrator Dashboard | Yes | No | No | No |
| Satellite Dashboard | Only where the account also passes satellite access | Yes, assigned satellite | Yes, assigned satellite | Only if separately assigned and authorized |
| Manage satellites | Yes | No | No | No |
| Create user | Yes | No | No | No |
| Edit district assignment | Yes | No | No | No |
| View member list | Yes via admin pages / authorized satellite page | Yes, assigned satellite | Yes, assigned satellite | No general access |
| Add/edit/delete member | Through authorized satellite management access | Yes, assigned satellite | No | No |
| Import members | Authorized satellite manager | Yes | No | No |
| View seekers | Yes | Yes, assigned satellite | Yes, assigned satellite | No general access |
| Assign seeker district/status | Yes | Yes, assigned satellite | No | No |
| Assign DGroup Leader to seeker | Yes where satellite management access passes | Yes, assigned satellite | No | No |
| Convert seeker to member | Yes where satellite management access passes | Yes, assigned satellite | No | No |
| Create/edit/delete follow-up | No standalone admin route; available to satellite manager | Yes, assigned satellite | No | No |
| Analyze follow-up sentiment | Satellite management access | Yes | No | No |
| Generate engagement classification | Authorized satellite manager | Yes, assigned satellite | No | No |
| View engagement/XAI result | Admin research pages or assigned satellite access | Yes, assigned satellite | Yes, assigned satellite | No |
| Assign/review recommendation | Admin catalogue; satellite manager or assigned reviewer for satellite recommendations | Yes, according to assignment | Assigned reviewer only | No |
| Submit system feedback | Authenticated user | Yes | Yes | Yes if authenticated |
| View own feedback | Yes for own submissions | Yes for own submissions | Yes for own submissions | Yes for own submissions |
| Sentiment research review | Yes | No | No | No |
| Engagement research review/training | Yes | No | No | No |
| Export admin datasets | Yes | No | No | No |

Authorization is enforced server-side. Seeing a URL does not grant access.

## 22. COMMON TASKS QUICK GUIDE

### Add a member
1. Open the assigned satellite.
2. Choose **Members**.
3. Click **Add Member**.
4. Complete required fields and choose the Dgroup Role.
5. Save and confirm the row appears.

### Add a new seeker
The current system does not expose a standard seeker-create form. Use the configured Google Sheets source and ask an administrator to run **Sync Google Sheets**, or use an existing approved import workflow.

### Assign a seeker to a DGroup Leader
1. Open **Seekers** for the satellite.
2. Open the seeker's leader selector.
3. Choose an active leader from that satellite.
4. Save.

### Convert a seeker to a member
1. Assign a DGroup Leader.
2. Open the seeker's conversion action.
3. Complete name, email, district, and campus/school fields.
4. Submit the conversion.
5. Confirm the new member has Status `New`.

### Record a follow-up
1. Open **Follow-Ups**.
2. Click **New Follow-Up**.
3. Select the seeker/member target.
4. Choose method and status.
5. Add notes and save.

### Check engagement
1. Open **Engagement**.
2. Click **Generate Classifications** if authorized.
3. Find a member.
4. Click **View Result**.

### Review XAI
1. Open a member's engagement result.
2. Read the classification and confidence.
3. Review Factors Used.
4. Expand **View CART Decision Path**.
5. Compare the explanation with the member's actual records.

### Assign a recommendation
1. Open a generated engagement result.
2. Select an approved recommendation action.
3. Select an assignee.
4. Add notes/expiry if needed.
5. Submit for human review.

### Submit system feedback
1. Open **Feedback**.
2. Click **New Feedback**.
3. Answer all five questions.
4. Add optional comments.
5. Select actual research consent and submit.

### Review sentiment research
1. Open **Sentiment Analysis** as an administrator.
2. Import stored feedback if needed.
3. Open **Human Feedback Review**.
4. Record consent, relevance, record type, language, independent human sentiment, and review status.
5. Save the review before viewing the prediction.
6. Run evaluation after eligible reviews are complete.

### Generate a report
1. Open the relevant administrator table.
2. Search/filter the records.
3. Review the visible rows.
4. Click **Export CSV** or the applicable XLSX export.

## 23. COMMON ERRORS AND SOLUTIONS

| Problem | Possible cause | Solution |
|---|---|---|
| Login failed | Unknown email | Confirm the account email with an administrator. |
| Incorrect password | Wrong administrator password | Re-enter it; contact an administrator if it remains unknown. |
| OTP invalid or expired | Code older than five minutes or typed incorrectly | Request a new OTP and enter all six digits. |
| Unauthorized page | Role or district does not grant access | Ask an administrator to confirm the account role and district assignment. |
| Required field missing | A required form field was blank | Read the validation messages and complete the named fields. |
| Duplicate email | Another member/user already uses the email in the relevant scope | Search before creating; use the existing record or a corrected email. |
| Leader cannot be selected | Leader is inactive or belongs to another satellite | Use an active DGroup Leader from the same satellite. |
| Seeker cannot convert | No DGroup Leader is assigned, or it was already converted | Assign a leader first and check conversion status. |
| Search returns no results | A filter or page search is still active | Click **Clear**, **Clear Filters**, or **Reset**. |
| Engagement result unavailable | Classification has not been generated or no current model exists | Ask an authorized manager to generate a classification; check data sufficiency. |
| Insufficient Information | Required engagement indicators are missing or unusable | Review the source record; do not treat the label as a personal judgment. |
| Recommendation cannot be assigned | No result, no approved action, or a pending recommendation already exists | Generate the result, use an active catalogue action, or review the existing pending item. |
| Feedback excluded from research | Consent was Denied, missing, or the unit was marked out of scope | Confirm the actual consent and scope evidence; never change it just to increase sample size. |
| Sentiment evaluation is Untested | No eligible independently reviewed records or missing language/reference fields | Complete valid human review and consent records, then evaluate. |
| Export has no records | Filters match no rows, or the Reports Dashboard is still placeholder-only | Clear filters and verify the source page; Reports currently exports no-report rows. |
| DGroup screen cannot be found | No standalone DGroup module exists | Use Members, leader fields, seeker leader assignment, and the dashboard totals. |

## 24. IMPORTANT WARNINGS

- Never share a password or OTP.
- Access only the member and seeker records needed for assigned responsibilities.
- Verify a record before editing or deleting it.
- Do not interpret engagement classifications as judgments of faith, character, maturity, emotional condition, or personal worth.
- Review every recommendation manually.
- Do not change a human reference label merely to improve model accuracy.
- Do not grant research consent on behalf of a respondent.
- Feedback with denied consent must not be used as eligible research feedback.
- Protect contact details, ministry information, follow-up notes, and research records.
- Do not publicly label, discipline, remove, or contact a person automatically because of an engagement result.
- Confirm that the selected satellite and DGroup Leader are correct before saving an assignment.
- Research exports may contain sensitive or identifying information; use approved storage only.

## 25. QUICK REFERENCE TABLE

| Task | Menu/page | Button/action |
|---|---|---|
| Open admin dashboard | Dashboard | Open **Dashboard** |
| Open satellite dashboard | Satellite Dashboard | Open **Dashboard** |
| Add satellite | Satellites | **Add Satellite** / create form |
| View members | Members | **View members** or satellite **Members** |
| Add member | Satellite Members | **Add Member** |
| Import members | Satellite Members | **Download Import Template**, **Preview Import**, **Import Members** |
| View seekers | Seekers | Open **Seekers** |
| Sync seekers | Admin Seekers | **Sync Google Sheets** |
| Assign seeker district | Admin Seekers | District assignment control |
| Assign seeker leader | Satellite Seekers | DGroup Leader selector and save |
| Convert seeker | Satellite Seekers | Convert/add to Members action |
| Record follow-up | Follow-Ups | **New Follow-Up** |
| Analyze follow-up notes | Follow-Ups | **Analyze Sentiment** |
| Check engagement | Engagement | **Generate Classifications** |
| View classification | Engagement | **View Result** |
| Review XAI | Engagement Result | **View CART Decision Path** |
| Assign recommendation | Engagement Result | Recommendation action form |
| Review recommendation | Recommendations | Open item and save review |
| Submit feedback | Feedback | **New Feedback** |
| Review sentiment | Sentiment Analysis | **Human Feedback Review** |
| Train CART evaluation | Engagement Research | **Train / Evaluate CART** |
| Export engagement evaluation | Engagement Research | **Export Engagement Evaluation Data** |
| Export sentiment evaluation | Sentiment Research | **Export Sentiment Evaluation Data** |
| Export table/data | Administrator tables | **Export CSV** or print/PDF |
| Leave system | Sidebar | **Sign Out** |

## 26. SCREENSHOT GUIDE

Capture the following screenshots for the final manual. Use test or de-identified records when preparing a thesis document.

1. **Login Page**  
   Route: `/login`  
   Action: Open the login page before entering an email.  
   Capture: email field and login step.  
   Caption: *Figure 1. Elevate login page.*

2. **Administrator Dashboard**  
   Route: `/admin/dashboard`  
   Action: Sign in as an administrator.  
   Capture: four summary cards and MAIN/NCP/ICP breakdown.  
   Caption: *Figure 2. Movement-wide administrator dashboard.*

3. **Satellite Dashboard**  
   Route: `/satellites/{satellite}/dashboard`  
   Action: Sign in as an assigned satellite manager.  
   Capture: summary cards and quick actions.  
   Caption: *Figure 3. Satellite dashboard.*

4. **Satellite Management**  
   Route: `/admin/satellites`  
   Action: Open Satellites as admin.  
   Capture: satellite table, filters, assigned users, and export control.  
   Caption: *Figure 4. Satellite and district management.*

5. **User Management**  
   Route: `/admin/users`  
   Action: Open Users as admin.  
   Capture: summary cards, directory, filters, and Add User control.  
   Caption: *Figure 5. User directory.*

6. **Member Breakdown**  
   Route: `/admin/members`  
   Action: Open Members as admin.  
   Capture: total cards and satellite breakdown.  
   Caption: *Figure 6. Administrator member breakdown.*

7. **Member List and Form**  
   Route: `/satellites/{satellite}/members`  
   Action: Open Add Member and use de-identified values.  
   Capture: fields for status, Dgroup role, identity, satellite, campus, and leader.  
   Caption: *Figure 7. Satellite member management form.*

8. **Seeker List**  
   Route: `/satellites/{satellite}/seekers`  
   Action: Open Seekers with a pending seeker.  
   Capture: seeker row, status, leader assignment, and conversion controls.  
   Caption: *Figure 8. Satellite seeker management.*

9. **Seeker Conversion**  
   Route: `/satellites/{satellite}/seekers`  
   Action: Assign a leader and open conversion.  
   Capture: conversion fields and required values.  
   Caption: *Figure 9. Seeker-to-member conversion.*

10. **DGroup Representation**  
    Route: `/satellites/{satellite}/members`  
    Action: Filter Dgroup Role to Dgroup Leader.  
    Capture: active leader records and member leader values.  
    Caption: *Figure 10. Current member-based DGroup representation.*

11. **Follow-Up History**  
    Route: `/satellites/{satellite}/follow-ups`  
    Action: Open a satellite with follow-ups.  
    Capture: filters, sentiment summary, table, and edit action.  
    Caption: *Figure 11. Follow-up history and sentiment trends.*

12. **New Follow-Up**  
    Route: `/satellites/{satellite}/follow-ups/create`  
    Action: Open New Follow-Up.  
    Capture: target, date, method, status, notes, and Create Follow-Up button.  
    Caption: *Figure 12. New follow-up form.*

13. **Engagement Analytics**  
    Route: `/satellites/{satellite}/engagement`  
    Action: Generate classifications if authorized.  
    Capture: classification summary cards and member results.  
    Caption: *Figure 13. Member engagement analytics.*

14. **XAI Engagement Result**  
    Route: `/satellites/{satellite}/engagement/{member}`  
    Action: Open View Result and expand the decision path.  
    Capture: classification, factors, and CART path.  
    Caption: *Figure 14. Explainable engagement result.*

15. **Recommendation Review**  
    Route: `/satellites/{satellite}/recommendations/{recommendation}`  
    Action: Open a pending review item.  
    Capture: action, assignee, notes, status controls, and review history.  
    Caption: *Figure 15. Human-reviewed recommendation.*

16. **Feedback Form**  
    Route: `/feedback/create`  
    Action: Open New Feedback.  
    Capture: five questions, additional comments, and consent checkbox.  
    Caption: *Figure 16. System evaluation feedback form.*

17. **My Feedback**  
    Route: `/feedback`  
    Action: Submit or open existing feedback.  
    Capture: sentiment, consent, and Why This Sentiment explanation.  
    Caption: *Figure 17. Submitted feedback and sentiment explanation.*

18. **Sentiment Research Review**  
    Route: `/admin/analytics/sentiment/research/review`  
    Action: Open as admin after synchronization.  
    Capture: consent, scope, language, human label, review status, and prediction visibility.  
    Caption: *Figure 18. Human feedback sentiment review.*

19. **Sentiment Unit Review**  
    Route: `/admin/analytics/sentiment/research/units/{unit}`  
    Action: Open one review unit.  
    Capture: consent evidence and independent review form.  
    Caption: *Figure 19. Independent sentiment reference review.*

20. **Engagement Research Review**  
    Route: `/admin/analytics/engagement/review`  
    Action: Open with a prepared dataset.  
    Capture: reference verification progress and records.  
    Caption: *Figure 20. Human engagement reference review.*

21. **Engagement Evaluation Dataset**  
    Route: `/admin/analytics/engagement/dataset`  
    Action: Open after training/evaluation.  
    Capture: split, metrics, confusion matrix, and export button.  
    Caption: *Figure 21. CART evaluation dataset and results.*

22. **Reports Dashboard**  
    Route: `/admin/reports`  
    Action: Open as admin.  
    Capture: clearly mark the current No reports/zero placeholder state.  
    Caption: *Figure 22. Reports page current implementation status.*

23. **Logout**  
    Route: any authenticated page  
    Action: Open the sidebar.  
    Capture: Sign Out control.  
    Caption: *Figure 23. Sign Out control.*

## 27. MISSING OR INCOMPLETE FUNCTIONALITY

The following items should not be presented as completed features:

1. **Standalone DGroup management:** No DGroup table, DGroup name, create/edit/archive/delete workflow, transfer workflow, or DGroup assignment screen exists. Current DGroups are represented through active DGroup Leader members and `Name of DLeader` member assignments.
2. **Standalone DGroup leader assignment:** Leader assignment is implemented for seekers and through member leader-name fields, not through a DGroup record.
3. **Administrator seeker creation:** The admin seeker page has synchronization, assignment, status, bulk actions, and export, but no ordinary Add Seeker form.
4. **Reports Dashboard:** The page is currently a placeholder. Counters remain zero and rows say No reports.
5. **General audit log:** There is no general Audit Logs sidebar page. Audit history exists inside engagement/research and recommendation workflows.
6. **User lifecycle management:** No delete, soft-delete, activate/deactivate, role-edit, or password-reset UI is implemented in the administrator user directory.
7. **System settings:** No general System Settings page or route is present.
8. **Live analytics overview:** The overview chart controller contains configured historical arrays for MAIN, NCP, and ICP rather than a complete live-database reporting workflow.
9. **Numeric evaluation scale:** The system-evaluation form stores five written answers; it does not currently expose the thesis-style 1-to-5 rating controls.
10. **Engagement research preparation:** When no reference dataset exists, the page instructs an administrator/developer to run the documented preparation command and additive migration. This is not a normal first-time staff action.
11. **Automatic recommendation execution:** Recommendations are stored and reviewed; they do not automatically contact, discipline, remove, or change a person.
12. **Research eligibility automation:** Stored feedback is synchronized into review units, but consent, language, scope, record type, human label, independence, and quote permission require human review.

## 28. SYSTEM FLOW

The implemented end-to-end flow is:

```text
Login
  -> Role-based dashboard
  -> Satellite / Members / Seekers
  -> Assign seeker district and active DGroup Leader
  -> Record follow-up or convert seeker to New member
  -> Generate engagement classification
  -> Review XAI factors and CART decision path
  -> Assign and human-review a recommendation
  -> Submit system feedback
  -> Record actual research consent
  -> Human sentiment review and fixed-engine evaluation
  -> Engagement/sentiment exports and reports
  -> Sign Out
```

The flow does not include a standalone Create DGroup step because that module is not currently implemented.

## APPENDIX: ROUTE QUICK LIST

- Login: `/login`
- General dashboard redirect: `/dashboard`
- Admin dashboard: `/admin/dashboard`
- Admin satellites: `/admin/satellites`
- Admin members: `/admin/members`
- Admin seekers: `/admin/seekers`
- Admin users: `/admin/users`
- Admin reports: `/admin/reports`
- Admin analytics: `/admin/analytics`
- Admin sentiment: `/admin/analytics/sentiment`
- Engagement research review: `/admin/analytics/engagement/review`
- Engagement research dataset: `/admin/analytics/engagement/dataset`
- Sentiment research review: `/admin/analytics/sentiment/research/review`
- Feedback form: `/feedback/create`
- My Feedback: `/feedback`
- Satellite dashboard: `/satellites/{satellite}/dashboard`
- Satellite members: `/satellites/{satellite}/members`
- Satellite seekers: `/satellites/{satellite}/seekers`
- Follow-ups: `/satellites/{satellite}/follow-ups`
- Engagement: `/satellites/{satellite}/engagement`
- Recommendations: `/satellites/{satellite}/recommendations`
- Logout: `POST /logout`
