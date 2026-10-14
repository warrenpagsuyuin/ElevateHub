# Elevate DGM Quick Start Guide

**For:** DGM Administrators and authorized satellite users  
**Reviewed:** 17 September 2026

## 1. Sign in

1. Open `/login`.
2. Enter your registered email.
3. Administrators enter their password.
4. Other users enter the six-digit OTP sent by email.
5. Confirm that the correct dashboard opens.

Click **Sign Out** when finished.

## 2. Know your dashboard

Administrators use **Dashboard** for movement-wide totals. Satellite users use **Dashboard** for their assigned satellite. The administrator cards show **DGROUP LEADERS**, **DGROUPS**, **DGROUP MEMBERS**, and **CAMPUSES**, with MAIN/NCP/ICP breakdowns.

In the current data model, **1 active DGroup Leader = 1 active DGroup**. There is no standalone DGroups menu.

## 3. Add or update a member

1. Open **Members** for the assigned satellite.
2. Click **Add Member**, or open an existing row's edit action.
3. Enter the member's identity and contact details.
4. Select Status and Dgroup Role.
5. Enter Campus/School and the **Name of DLeader** when applicable.
6. Save.

Use **Import Members** only after downloading the template and reviewing the import preview.

## 4. Manage a seeker

1. Open **Seekers**.
2. Find the seeker and confirm the satellite.
3. Assign an active DGroup Leader from the same satellite.
4. Use the status control to set **Pending**, **Successful**, or **Unsuccessful**.
5. To convert, open the conversion action, complete the required member fields, and save.

An unsuccessful seeker is archived. A converted seeker becomes a `New` member and cannot be converted twice.

## 5. Record a follow-up

1. Open **Follow-Ups**.
2. Click **New Follow-Up**.
3. Select a seeker or member.
4. Select a method: Phone, Email, In-Person, or Other.
5. Select Pending, Completed, or Cancelled.
6. Add notes up to 1,000 characters.
7. Click **Create Follow-Up**.

Use **Analyze Sentiment** to update sentiment results for the satellite's follow-up notes.

## 6. Check engagement

1. Open **Engagement**.
2. Click **Generate Classifications** if you have management permission.
3. Review the Active, Moderately Active, Possible Need for Follow-Up, and Insufficient Information totals.
4. Click **View Result** beside a member.
5. Read the factors and expand **View CART Decision Path**.

The result is decision support. It is not a judgment of faith, character, or personal worth.

## 7. Review a recommendation

1. Open a member's engagement result.
2. Select an approved recommendation action.
3. Choose an assignee and add notes if needed.
4. Submit it for human review.
5. Open the recommendation and choose Approved, Modified, or Rejected.
6. Add review notes and save.

Recommendations do not automatically contact or discipline anyone.

## 8. Submit system feedback

1. Open **Feedback**.
2. Click **New Feedback**.
3. Answer all five questions.
4. Add optional comments.
5. Choose your actual research consent decision.
6. Submit.

Denied consent excludes the feedback from eligible research evaluation. You can review or update your own decision from **My Feedback**.

## 9. Administrator research review

### Engagement research
1. Open **Analytics** and the engagement research review page.
2. Review evidence and reference records.
3. Use **Save human verification** only after reviewing the actual evidence.
4. Use **Train / Evaluate CART** after the current dataset is ready.
5. Export with **Export Engagement Evaluation Data**.

### Sentiment research
1. Open **Sentiment Analysis**.
2. Confirm that documented approved-period metadata is already recorded. Period changes require audited maintenance; there is no Settings tab.
3. Open **Human Feedback Review** and click **Import new feedback**.
4. Review consent, scope, record type, language, and human sentiment independently of the prediction.
5. Save the review.
6. Ask the maintainer to run `php artisan sentiment:prepare-evaluation --predict --export`.
7. Download with **Export evaluation**.

## 10. Export records

1. Open the required administrator table.
2. Apply filters.
3. Confirm the rows shown.
4. Click **Export CSV**, or use the relevant XLSX research export.
5. Store the file securely.

The current **Reports Dashboard** is placeholder-only: its counters are zero and rows show **No reports**.

## 11. Important limits

- There is no standalone DGroup create/edit/archive page.
- The admin seeker page does not have a normal Add Seeker form; synchronization is the implemented intake workflow.
- There is no general Audit Logs page.
- Engagement classifications require human review.
- Human labels must not be changed to improve model accuracy.
- Never share passwords, OTPs, or exported personal information.
