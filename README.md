# Reviews System 🇵🇰

[![WordPress](https://img.shields.io/badge/WordPress-Plugin-21759B?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%20%7C%208.x-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6%2B%20%7C%20jQuery-F7DF1E?logo=javascript&logoColor=black)](https://developer.mozilla.org/)
[![Status](https://img.shields.io/badge/Status-Production%20Ready-brightgreen)](#)

> An end-to-end Cadet Reviews, Testimonials, and Verification Management System built for **Officers Academy** (Premier preparatory institute for Pakistan Armed Forces: PMA Long Course, AFNS, GD Pilot, TCC, Medical Cadet Course, PN Cadets, Lady Cadet Course, and ISSB).

---

## 🌟 Key Features

### 1. Front-End Cadet Reviews Portal
* **Trust Badges & Social Proof**: Header banner with verified recommendation seals, 4.9/5 overall cadet rating, and armed forces preparation highlights.
* **Interactive Course Filtering**: Real-time filter tabs for all 8 military courses:
  * `PMA Long Course`
  * `AFNS`
  * `GD Pilot`
  * `TCC`
  * `Medical Cadet Course`
  * `PN Cadets`
  * `Lady Cadet Course`
  * `ISSB`
* **Cadet Review Cards**: Displays cadet avatar initials, verified badge, course tag, star rating (1–5), review attempt number, masked receipt ID, and publication date.
* **Date-Wise Sequence**: Reviews are automatically sorted sequentially by date (`review_date DESC, id DESC`).

### 2. "Write a Review" Modal & Verification Workflow
* **Dedicated Trigger Button**: `+ Write a Review` with high-visibility styling.
* **Modal Input Fields**:
  1. `Student Full Name`
  2. `Course Dropdown` (All 8 designated armed forces courses)
  3. `Review Number` (1 to 5)
  4. `Interactive Star Picker` (Clickable 1 to 5 gold stars with dynamic labels)
  5. `Payment Receipt ID` (Enables transactional proof verification)
  6. `Review Text`
* **Animated Green Tick Confirmation :white_check_mark:**:
  * On submit, displays a smooth SVG animated green checkmark.
  * Shows verification notice:
    > *"Apka review add ho chuka he. Payment verify krne k bad yaha display kr dya jae ga."*  
    > *(Your review has been submitted. After verifying your payment receipt ID, it will be displayed here.)*
  * Stores submission in database with `pending_verification` status for admin approval.

### 3. WordPress Admin Panel (`OA Reviews`)
* **Bulk JSON Importer (Primary Feature)**:
  * Select target course for batch import.
  * Date range distribution: distributes batch reviews sequentially between `Start Date` and `End Date`.
  * Pre-sample JSON template box with **1-click Fill Sample** button.
  * **1-click Copy AI Prompt** for instant review generation with ChatGPT / Gemini / Claude.
* **Course & Date-Wise Deletion Tool**:
  * Bulk delete reviews by specific course.
  * Bulk delete reviews within a custom date range (`From Date` to `To Date`).
  * Filter deletion by status (`Approved` vs `Pending`).
* **High-Performance Admin Data Grid**:
  * **2-Word Preview Truncation**: Review text is truncated to the first 2 words + `...` (e.g. `Alhamdulillah cleared...`) preventing layout strain and ensuring instantaneous page loads.
  * **Hover Tooltip**: Full review text is accessible on hover without expanding row heights.
  * **Multi-Select Checkboxes**: Select all or specific reviews for batch deletion.
  * **1-Click Moderation**: Approve or delete pending reviews with receipt verification.

---

## 📂 Repository Structure

```text
officers-academy-reviews/
│
├── plugin/
│   ├── oa-reviews.zip                  # Ready-to-install WordPress plugin ZIP
│   └── oa-reviews/                     # Uncompressed WordPress plugin source
│       ├── oa-reviews.php              # Main plugin file & database schema
│       └── assets/
│           ├── css/
│           │   ├── admin.css           # Admin styling & deletion tools
│           │   └── frontend.css        # Front-end military theme & modal
│           └── js/
│               ├── admin.js            # Clipboard copy & checkbox logic
│               └── frontend.js         # AJAX submit, rating & animations
│
├── standalone-frontend/
│   └── index.html                      # Standalone interactive review page
│
├── sample-data/
│   └── sample_reviews.json             # Realistic bulk reviews dataset
│
├── ai-prompts/
│   └── ai_prompt_template.txt          # Ready-to-use LLM bulk prompt
│
├── .gitignore                          # Standard gitignore
├── LICENSE                             # GNU GPL v2.0
└── README.md                           # Documentation
```

---

## 🚀 WordPress Installation

1. Download [`oa-reviews.zip`](plugin/oa-reviews.zip) from the `plugin/` folder.
2. In your WordPress Admin Dashboard, navigate to **Plugins** ➔ **Add New** ➔ **Upload Plugin**.
3. Upload `oa-reviews.zip` and click **Install Now**.
4. Click **Activate Plugin**.

### Displaying Reviews on Any Page
Add the following shortcode to any WordPress page (Elementor, Gutenberg, or Classic Editor):
```text
[officers_academy_reviews]
```

---

## 🤖 AI Bulk Import Guide (ChatGPT / Gemini)

1. Open `ai-prompts/ai_prompt_template.txt` or copy the prompt from **OA Reviews** ➔ **Bulk JSON Import**.
2. Paste the prompt into ChatGPT or Gemini:
   ```text
   Please generate 20 realistic cadet reviews for Officers Academy in pure JSON format:
   Keys: student_name, review_text, review_number (1 to 5), course, stars (1 to 5), payment_receipt_id, date.
   ```
3. Copy the generated JSON array.
4. Go to **OA Reviews** ➔ **Bulk JSON Import** in WordPress, select your course, set the date range, paste the JSON, and click **Import & Publish All Reviews Now**.

---

## 🌐 Standalone Frontend Preview
To preview the front-end page directly in any web browser without WordPress:
1. Navigate to `standalone-frontend/index.html`.
2. Double-click to open in Google Chrome, Microsoft Edge, or Mozilla Firefox.

---

## 🛠️ Tech Stack & Architecture
* **Backend**: PHP 7.4+ / 8.x, WordPress Plugin API, Custom MySQL Table (`wp_oa_reviews`).
* **Frontend**: HTML5, Responsive CSS3 (CSS Grid & Flexbox), Vanilla JS / jQuery.
* **Security**: Nonce verification (`check_admin_referer`, `check_ajax_referer`), sanitized inputs (`sanitize_text_field`, `sanitize_textarea_field`), prepared SQL queries (`$wpdb->prepare`).

---

## 📄 License
This project is open-source software licensed under the [GNU General Public License v2.0](LICENSE).
