# Chapter 3 Table Completion Guide

**System:** Elevate Discipleship Group Management System  
**Reviewed:** 18 September 2026

This guide uses the live application for research tables and the Laravel test database for Table 14 functional testing. Do not create test feedback in the live database.

## Current status before you begin

The current live feedback queue is **not ready** for Tables 11, 12, 18, 19, or 20.

| Unit | Current state | Action |
|---|---|---|
| F001 | Granted; human label exists; review incomplete | Complete actual scope, record type, language, independence, and period evidence. |
| F002 | Granted; human label exists; review incomplete | Complete actual scope, record type, language, independence, and period evidence. |
| F003 | Granted; split parent | Do not include the parent. Review child units only if they are present. |
| F004-F009 | Granted; review incomplete | Complete actual scope, record type, language, human sentiment, independence, and period evidence. |
| F010-F011 | Denied | Do not change consent. Exclude permanently from research evaluation. |

Current live results:

- Eligible research units: `0`.
- Table 11 total: `0`.
- Table 18-20 evaluation N: `0`.
- Sentiment evaluation: `UNTESTED`.

Do not call this 0% accuracy. It is an unevaluated cohort.

# Part 1: Prepare feedback records

## Step 1: Open the research review page

1. Sign in as an authorized administrator.
2. Open **Sentiment Analysis** from the administrator sidebar.
3. Open **Human Feedback Review**.
4. Direct route: `/admin/analytics/sentiment/research/review`.
5. The page lists feedback codes such as `F001`, `F002`, and so on.
6. Click **Review** beside one unit.

A unit detail page uses this route pattern:

`/admin/analytics/sentiment/research/units/{unit}`

Screenshot to take:

- Review list showing the feedback code, consent, scope, language, human reference, review status, inclusion, and prediction visibility.
- Do not show raw personal feedback text, names, emails, or private notes in the thesis screenshot.

## Step 2: Check consent first

On the unit detail page, read the **Consent evidence** section.

### If consent is Granted

Continue to the next checks only if the stored consent evidence is genuine.

### If consent is Denied

1. Do not change it.
2. Do not submit a research review for it.
3. Do not include it in Table 11, 12, 18, 19, or 20.
4. Leave F010 and F011 excluded.

Never change consent to increase the sample size.

## Step 3: Record the research scope

In **Human review / explicit correction**, set **System relevance** to:

`Relevant to developed system`

Use this only when the text is actually about the developed system, such as its usability, member workflow, seeker workflow, follow-up workflow, engagement result, or system limitation.

If it is unrelated:

1. Choose **Not relevant / exclude**.
2. Enter a truthful scope note.
3. Save the review.
4. Exclude it from Tables 11, 12, 18, 19, and 20.

If the meaning is unclear:

1. Choose **Needs clarification**.
2. Enter a truthful scope note.
3. Save the review.
4. Exclude it until clarified.

## Step 4: Confirm record type

Choose **Actual evaluation feedback** when the response came from an actual system evaluator or respondent.

Choose **Developer/test feedback** only when the record is genuinely a developer or test fixture. A developer/test record cannot enter the thesis evaluation cohort.

For real thesis feedback, select:

`Actual evaluation feedback`

Do not mark a real response as a test record merely because it is difficult to classify.

## Step 5: Review language manually

In **Human-reviewed language**, choose exactly one:

- `English`
- `Filipino`
- `Filipino-English`

Use the meaning of the whole feedback text, not the automatic language signal.

Examples:

- English: `The system is easy to use.`
- Filipino: `Madaling gamitin ang system.`
- Filipino-English: `Madaling gamitin and very helpful yung system.`

If the language cannot be determined honestly, leave the review as clarification/pending. Do not guess.

## Step 6: Assign the human sentiment reference

Under **Human Reference Sentiment**, choose exactly one:

- `Positive`
- `Neutral`
- `Negative`

Use the overall meaning of the original text:

- Positive: mainly satisfaction, usefulness, ease, approval, or favorable experience.
- Neutral: mainly factual, descriptive, balanced, or without a clear positive/negative evaluation.
- Negative: mainly dissatisfaction, difficulty, error, frustration, limitation, or confusion.

Important:

1. Decide the human label before looking at the system prediction.
2. Do not change the label to match the prediction.
3. If you already saw the prediction, ask another authorized reviewer who has not seen it.
4. For a finalized review, select **Blinded** or **Blinded Re-Review** only if that is truthful.

## Step 7: Finalize the review

Complete these fields:

- **Review status:** `Final`.
- **Human review independence:** `Blinded` or `Blinded Re-Review`, only when true.
- **Independent review checkbox:** select it only when the label and language were assigned independently.
- **Reason / notes:** enter the actual review basis when required.
- **Quote permission:** select it only when the respondent's permission and de-identification conditions allow quotation.

Click **Save Review**.

Use **Save and Next** only after checking that the current record is correct.

### A record is ready only when all conditions are true

- Consent = Granted.
- Scope = System Feedback.
- Record type = Actual evaluation feedback.
- Language = finalized as English, Filipino, or Filipino-English.
- Human sentiment = finalized as Positive, Neutral, or Negative.
- Review status = Final.
- Reviewer identity and date/time are stored.
- Independence is honestly documented.
- The feedback is inside the approved evaluation period.
- The current system prediction exists after evaluation.
- The unit is not a split parent, duplicate, denied-consent record, or excluded unit.

The page must show the unit as **Eligible** after the eligibility service refreshes. Do not include a unit that still says **Excluded / Pending**.

## Step 8: Approved evaluation period

On **Sentiment Analytics**, use the **Approved evaluation period** section.

Route: `/admin/analytics/sentiment`

Only an actually approved evaluation period may be entered.

1. Enter the real **Start** date.
2. Enter the real **End** date.
3. Enter the actual approval evidence.
4. Confirm **These dates are approved for system evaluation**.
5. Click **Save approved period**.

Do not invent an approver, approval reason, or date. If the current approval does not identify a valid authorized approver, ask the responsible authority to supply the real approval record before proceeding.

# Part 2: Generate Table 11

## Step 1: Refresh eligibility

1. Return to `/admin/analytics/sentiment`.
2. Click **Import new stored feedback**.
3. This imports existing stored feedback units only. It does not create human labels.
4. Return to **Human Feedback Review**.
5. Confirm that every intended unit says **Eligible**.

## Step 2: Open the dataset page

1. Open **Evaluation Dataset** from the Sentiment Analysis page.
2. Direct route: `/admin/analytics/sentiment/research/dataset`.
3. Confirm that the page shows the eligible counts by language and human sentiment.
4. Do not proceed if any intended record is still Excluded / Pending.

## Table 11 worksheet

Use the same eligible unit IDs shown by the review service. Count each record once.

| Language Group | Positive | Neutral | Negative | Total |
|---|---:|---:|---:|---:|
| English | ___ | ___ | ___ | ___ |
| Filipino | ___ | ___ | ___ | ___ |
| Filipino-English | ___ | ___ | ___ | ___ |
| **Total** | **___** | **___** | **___** | **___** |

Also record:

- Eligible record count: `___`.
- Eligible feedback codes: `___`.
- Positive total: `___`.
- Neutral total: `___`.
- Negative total: `___`.
- English total: `___`.
- Filipino total: `___`.
- Filipino-English total: `___`.

### Table 11 completion test

Mark **TABLE 11 COMPLETE** only when:

1. Every included unit is marked Eligible.
2. Every included unit has a human label and language.
3. The sum of all cells equals the eligible record count.
4. Positive + Neutral + Negative equals the eligible record count.
5. English + Filipino + Filipino-English equals the eligible record count.
6. No F010 or F011 record is included.
7. No split parent is included.
8. The page's validation says **PASS**.

Screenshot:

- Capture the Evaluation Dataset page showing the final language/sentiment distribution and eligible count.
- Include the page title and date if visible.
- Do not include raw comments or private reviewer notes.

## Step 3: Run the fixed sentiment evaluation

Only after Table 11 is complete:

1. Return to `/admin/analytics/sentiment`.
2. Click **Predict / Evaluate reviewed feedback**.
3. Wait for the success message.
4. Return to **Evaluation Dataset**.
5. Confirm every Table 11 record has a current system prediction.
6. If any prediction fails, stop. The evaluation is not complete.

# Part 3: Generate Table 12

Use exactly the same eligible records as Table 11. Do not add a new record because it has an interesting comment.

## Table 12 columns

| Feedback Code | Language | Original Feedback | Human Reference | System Prediction |
|---|---|---|---|---|
| F___ | English/Filipino/Filipino-English | Approved de-identified text only | Positive/Neutral/Negative | Positive/Neutral/Negative |

## Table 12 steps

1. Open **Evaluation Dataset**.
2. Open the **Feedback Samples** or Table 12 section.
3. Use the displayed Feedback Code.
4. Verify the Language against the finalized human language review.
5. Verify the Human Reference against the saved human label.
6. Verify the System Prediction after running evaluation.
7. Copy original feedback only when **Quote permission** is Yes and the text has been manually de-identified.
8. If quote permission is No, use an approved de-identified version only, or leave the original text private and report the code without quotation.

Do not include F010 or F011. Do not include excluded, pending, duplicate, split-parent, test, or prediction-failed units.

### Table 12 completion test

Mark **TABLE 12 COMPLETE** only when:

1. Every Table 11 feedback code has exactly one Table 12 row.
2. No Table 12 row is absent from Table 11.
3. Every row has a finalized human label and current system prediction.
4. Every displayed quotation has permission and de-identification approval.
5. The row count equals the Table 11 eligible total.

Screenshot:

- Capture the Human Feedback Review or Evaluation Dataset page showing human reference and system prediction for the eligible records.
- If raw text is visible, redact names, contact information, private ministry details, and consent/reviewer notes before including the screenshot.

# Part 4: Generate Tables 18, 19, and 20

These tables use exactly the same Table 11 records.

## Table 18: Confusion matrix

1. Open `/admin/analytics/sentiment/research/dataset`.
2. Find **Table 18: Confusion Matrix of System-Feedback Sentiment Classification**.
3. Copy the Positive, Neutral, and Negative rows and columns.
4. Confirm the matrix total equals the Table 11 eligible count.
5. Confirm every Table 12 prediction is represented once.

| Actual / Predicted | Positive | Neutral | Negative |
|---|---:|---:|---:|
| Positive | ___ | ___ | ___ |
| Neutral | ___ | ___ | ___ |
| Negative | ___ | ___ | ___ |

Screenshot:

- Capture Table 18 with the Test N, Correct, Incorrect, and Accuracy text visible.

## Table 19: Overall sentiment performance

1. On the same Evaluation Dataset page, find **Table 19: Overall Sentiment Classification Performance**.
2. Record each class's Precision, Recall, F1 Score, and Support.
3. Record Macro Average, Weighted Average, and Overall Accuracy.
4. Confirm Table 18 and Table 19 use the same N.

| Sentiment / Average | Precision | Recall | F1 Score | Support |
|---|---:|---:|---:|---:|
| Positive | ___ | ___ | ___ | ___ |
| Neutral | ___ | ___ | ___ | ___ |
| Negative | ___ | ___ | ___ | ___ |
| Macro Average | ___ | ___ | ___ | ___ |
| Weighted Average | ___ | ___ | ___ | ___ |
| Overall Accuracy | - | - | ___ | ___ |

### Formula checks

For each class:

- Precision = true positives / all predicted positives.
- Recall = true positives / all actual records of that class.
- F1 = $2PR/(P+R)$ when $P+R > 0$.
- Accuracy = correct predictions / total evaluation records.

Mark **TABLE 19 COMPLETE** only when the page validation says **PASS**, Table 18 and Table 19 have identical N, and no metric is silently replaced with zero when N is zero.

Screenshot:

- Capture Table 19 with per-class metrics, macro/weighted averages, and accuracy visible.

## Table 20: Performance according to language

1. On the Evaluation Dataset page, find **Table 20: Sentiment Performance According to Language**.
2. Record English, Filipino, and Filipino-English separately.
3. Record N, Accuracy, Macro Precision, Macro Recall, and Macro F1.
4. If a language has N=0, write **UNTESTED**, not 0%.
5. Confirm the sum of language Ns equals the Table 19 evaluation N.

| Language | Test N | Accuracy | Macro Precision | Macro Recall | Macro F1 |
|---|---:|---:|---:|---:|---:|
| English | ___ | ___ | ___ | ___ | ___ |
| Filipino | ___ | ___ | ___ | ___ | ___ |
| Filipino-English | ___ | ___ | ___ | ___ | ___ |

Mark **TABLE 20 COMPLETE** only when Table 19 and Table 20 use exactly the same evaluation records and the page validation says **PASS**.

Screenshot:

- Capture Table 20 with all three language rows and the same-record validation visible.

# Part 5: Table 14 recommendation functional test

Table 14 is a software functional test, not a research-feedback table. Use the Laravel test database only. Do not create recommendation fixtures in `database/database.sqlite`.

## Actual recommendation routes

- Engagement result and generation form: `/satellites/{satellite}/engagement/{member}`
- Recommendation list: `/satellites/{satellite}/recommendations`
- Recommendation review: `/satellites/{satellite}/recommendations/{recommendation}`
- Review submission: `PATCH /satellites/{satellite}/recommendations/{recommendation}/review`
- Administrator catalogue: `/admin/recommendation-actions`

The current live database has zero recommendation actions and zero recommendations, so the browser cannot complete a real live generation test without creating data. Do not create that data in the live research database. Use the isolated tests below or a separate local test database.

## Recommended test command

From `elevate-dgm/laravel-app`, run:

```text
php artisan test --filter=ThesisFunctionalEvidenceTest
```

This test class uses an in-memory SQLite database and synthetic fixtures. Its fixtures are test evidence only and must not be presented as live research participants or feedback.

## Table 14 test sequence

### Test 1: Recommendation generation

Automated test: `test_t14_generates_pending_recommendation_from_approved_matching_catalogue`

Browser-equivalent steps in an isolated test environment:

1. Log in as an authorized satellite manager assigned to the fixture satellite.
2. Open the fixture member's engagement result.
3. Confirm a classification exists.
4. Confirm an approved catalogue action is displayed.
5. Select the approved action.
6. Select an assigned reviewer.
7. Click **Assign for review**.
8. Open **View recommendations**.
9. Open the new recommendation.

Expected result:

- A recommendation is created with status **Pending Review**.
- The approved action title and description are shown.
- The assigned reviewer is shown.

Screenshot:

- Engagement result showing **Assign recommendation for human review** and the approved action.
- Recommendation page showing **Pending Review**.

Table 14 status: **PASS** in the isolated test: 1 test passed, 20 assertions.

### Test 2: Approve

Automated test: `test_t14_authorized_reviewer_can_approve_and_history_records_actor_action_time`

1. Open a Pending Review recommendation.
2. Confirm the assigned reviewer or authorized satellite manager is logged in.
3. In the review form, leave notes if desired.
4. Click **Approve**.
5. Confirm the status changes to **Approved**.
6. Open **Review history**.
7. Verify reviewer name, Approved action, and date/time.

Screenshot:

- Review form with **Approve**.
- Final page showing **Approved** and Review history.

Table 14 status: **PASS** in the isolated test: 1 test passed, 20 assertions.

### Test 3: Modify

Automated test: `test_t14_modified_wording_requires_notes_and_can_be_approved_with_history_preserved`

1. Create a separate Pending Review recommendation in the isolated test environment.
2. Open its review page.
3. Enter **Modified action wording**.
4. Enter review notes explaining the change.
5. Click **Modify**.
6. Confirm the status becomes **Modified**.
7. Confirm the changed wording appears.
8. Confirm the review history records the modification.
9. Optionally click **Approve** afterward to finalize the modified recommendation.

Required fields:

- Modified action wording.
- Review notes.

Screenshot:

- Review form with modified wording and notes.
- Final page showing **Modified** and the history entry.

Table 14 status: **PASS** in the isolated test: 1 test passed, 20 assertions.

### Test 4: Reject

Automated test: `test_t14_authorized_reviewer_can_reject_with_required_reason`

1. Create another separate Pending Review recommendation in the isolated test environment.
2. Open the review page.
3. Enter a truthful review note explaining why the action is rejected.
4. Click **Reject**.
5. Confirm the status becomes **Rejected**.
6. Confirm the note is visible in the review page/history.

Review notes are required for rejection.

Screenshot:

- Reject form with the review note.
- Final page showing **Rejected**.

Table 14 status: **PASS** in the isolated test: 1 test passed, 12 assertions.

### Test 5: Audit trail

Review history is shown on the recommendation detail page under **Review history**.

Verify each history row contains:

- action/event;
- reviewer name;
- date/time;
- resulting status;
- review notes;
- modified wording when the action was Modified.

The underlying audit events are:

- `recommendation.assigned`
- `recommendation.approved`
- `recommendation.modified`
- `recommendation.rejected`

Table 14 status: **PASS** for approve, modify, and reject audit behavior in the isolated tests.

Screenshot:

- Recommendation page with the complete Review history table visible.

### Test 6: Unauthorized access

Automated test: `test_t14_permissions_deny_cross_district_and_unassigned_recommendation_access`

1. Create a recommendation in the isolated test environment.
2. Log out from the assigned satellite manager.
3. Log in as a user from another district, or as a user without management/assignment permission.
4. Attempt to open `/satellites/{satellite}/recommendations/{recommendation}`.
5. Attempt to submit the review form.

Expected result:

- The page returns `403 Forbidden`.
- The review submission is blocked.
- The recommendation remains Pending Review.

Table 14 status: **PASS** in the isolated test: 1 test passed, 12 assertions.

Screenshot:

- The browser's 403/Access Denied page, with no private record details visible.

### Test 7: No automatic contact or discipline

Automated test: `test_t14_recommendations_do_not_contact_discipline_or_exclude_members`

This test currently **FAILS** at `tests/Feature/ThesisFunctionalEvidenceTest.php:243` because the fixture's stored member/result attribute arrays are not identical after the workflow. Do not report this row as PASS until the regression is investigated and the test passes.

The intended test must verify:

1. Approve a recommendation in the isolated test database.
2. Confirm no new follow-up was created automatically.
3. Confirm the member record did not change status.
4. Confirm the engagement result did not change.
5. Confirm no email was sent or queued.
6. Confirm no notification was sent.
7. Confirm no queue job was pushed.
8. Confirm no outbound HTTP request was made.

The application page also states that recommendations do not automatically contact, discipline, or exclude members. The automated regression currently does not prove this successfully because of the failing attribute-equality assertion.

Table 14 status: **FAIL / needs regression repair** until the isolated test passes.

## Table 14 final worksheet

| Function | Expected Result | Actual Result | Status |
|---|---|---|---|
| Recommendation generation | Approved possible action displayed; Pending Review record created | Isolated test passes; live database has no actions/recommendations | **PASS in test DB / live UNTESTED** |
| Approve | Authorized reviewer can approve | Status and audit assertions pass in isolated test | **PASS** |
| Modify | Authorized reviewer can change wording | Wording, notes, and history assertions pass in isolated test | **PASS** |
| Reject | Authorized reviewer can reject | Rejection and required-note assertions pass in isolated test | **PASS** |
| Audit trail | Reviewer/action/time recorded | Audit assertions pass for approve/modify/reject | **PASS** |
| Access restriction | Unauthorized user blocked | Cross-district and unassigned access assertions pass | **PASS** |
| No automatic contact/discipline | No contact, discipline, exclusion, or member/result mutation | Regression test fails at line 243 | **FAIL** |

## Screenshots required for Table 14

1. Engagement result with approved recommendation action and **Assign for review**.
2. New recommendation showing **Pending Review**.
3. Approval form and resulting **Approved** status.
4. Modify form showing wording and review notes.
5. Modified recommendation and review history.
6. Reject form with required review note.
7. Rejected recommendation and review history.
8. Unauthorized user's 403 page.
9. Final Review history showing reviewer, action, and timestamp.

Label every screenshot as **isolated test database / synthetic functional fixture**. Do not present it as a live member research result.

## Final stop/go checklist

Do not generate final Tables 11, 12, 18, 19, or 20 until:

- Every intended Granted-consent leaf unit has completed actual scope review.
- F010 and F011 remain excluded.
- F003 remains excluded as a split parent; only properly reviewed child units may be considered.
- Actual evaluation/non-test status is documented.
- Language is manually finalized.
- Human sentiment is finalized independently.
- Independence and reviewer provenance are truthful.
- The approved evaluation period is valid and documented.
- Every included unit has a current system prediction.
- The Evaluation Dataset page shows Eligible records and PASS validations.

Do not call Table 14 fully passed until the no-automatic-action regression test is repaired and passes in the test database.
