<?php
/**
 * Plugin Name: Officers Academy Reviews
 * Plugin URI: https://officersacademy.pk
 * Description: Official Cadet Reviews management system for Officers Academy. Features bulk JSON import, payment receipt verification workflow, trust badges, course filtering, and shortcode [officers_academy_reviews].
 * Version: 1.0.2
 * Author: Officers Academy
 * Author URI: https://officersacademy.pk
 * License: GPL2
 * Text Domain: officers-academy-reviews
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( 'OA_REVIEWS_VERSION', '1.0.2' );
define( 'OA_REVIEWS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OA_REVIEWS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * 1. Activation Hook: Create Database Table
 */
register_activation_hook( __FILE__, 'oa_reviews_install_table' );
function oa_reviews_install_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'oa_reviews';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        student_name varchar(255) NOT NULL,
        review_text text NOT NULL,
        review_number int(11) NOT NULL DEFAULT 1,
        course varchar(100) NOT NULL,
        stars int(11) NOT NULL DEFAULT 5,
        payment_receipt_id varchar(100) NOT NULL,
        status varchar(50) NOT NULL DEFAULT 'approved',
        review_date date NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

/**
 * 2. Helper: List of Academy Courses
 */
function oa_reviews_get_courses() {
    return array(
        'PMA Long Course',
        'AFNS',
        'GD Pilot',
        'TCC',
        'Medical Cadet Course',
        'PN Cadets',
        'Lady Cadet Course',
        'ISSB'
    );
}

/**
 * 3. Enqueue Admin Assets
 */
add_action( 'admin_enqueue_scripts', 'oa_reviews_admin_assets' );
function oa_reviews_admin_assets( $hook ) {
    if ( strpos( $hook, 'oa-reviews' ) === false ) {
        return;
    }
    wp_enqueue_style( 'oa-reviews-admin-css', OA_REVIEWS_PLUGIN_URL . 'assets/css/admin.css', array(), OA_REVIEWS_VERSION );
    wp_enqueue_script( 'oa-reviews-admin-js', OA_REVIEWS_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), OA_REVIEWS_VERSION, true );
}

/**
 * 4. Enqueue Frontend Assets
 */
add_action( 'wp_enqueue_scripts', 'oa_reviews_frontend_assets' );
function oa_reviews_frontend_assets() {
    wp_register_style( 'oa-reviews-frontend-css', OA_REVIEWS_PLUGIN_URL . 'assets/css/frontend.css', array(), OA_REVIEWS_VERSION );
    wp_register_script( 'oa-reviews-frontend-js', OA_REVIEWS_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), OA_REVIEWS_VERSION, true );

    wp_localize_script( 'oa-reviews-frontend-js', 'oaReviewsData', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'oa_review_submit_nonce' ),
        'success_msg' => 'apka review add ho chuka he. payment verify krne k bad yaha display kr dya jae ga'
    ) );
}

/**
 * 5. Admin Menu Setup (Bulk JSON Import & Review Management)
 */
add_action( 'admin_menu', 'oa_reviews_add_admin_menu' );
function oa_reviews_add_admin_menu() {
    // Primary Top-Level Menu
    add_menu_page(
        'Officers Academy Reviews',
        'OA Reviews',
        'manage_options',
        'oa-reviews-bulk',
        'oa_reviews_admin_bulk_page',
        'dashicons-star-filled',
        26
    );

    // Submenu 1: Bulk JSON Import (Primary)
    add_submenu_page(
        'oa-reviews-bulk',
        'Bulk JSON Import',
        'Bulk JSON Import',
        'manage_options',
        'oa-reviews-bulk',
        'oa_reviews_admin_bulk_page'
    );

    // Submenu 2: Manage All Reviews
    add_submenu_page(
        'oa-reviews-bulk',
        'Manage All Reviews',
        'Manage Reviews',
        'manage_options',
        'oa-reviews-manage',
        'oa_reviews_admin_manage_page'
    );
}

/**
 * 6. Admin Page: Bulk JSON Import (Only Bulk Import as requested)
 */
function oa_reviews_admin_bulk_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'oa_reviews';
    $courses = oa_reviews_get_courses();
    $notice = '';

    // Handle Form Submission for Bulk JSON Import
    if ( isset( $_POST['oa_bulk_import_submit'] ) && check_admin_referer( 'oa_bulk_import_action', 'oa_bulk_import_nonce' ) ) {
        $selected_course = sanitize_text_field( $_POST['target_course'] ?? '' );
        $json_raw = trim( stripslashes( $_POST['reviews_json'] ?? '' ) );
        $import_status = sanitize_text_field( $_POST['import_status'] ?? 'approved' );
        $start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
        $end_date = sanitize_text_field( $_POST['end_date'] ?? '' );

        if ( empty( $json_raw ) ) {
            $notice = '<div class="notice notice-error is-dismissible"><p><strong>Ghalti:</strong> Barah-e-karam JSON data paste karein!</p></div>';
        } else {
            $data = json_decode( $json_raw, true );
            if ( ! is_array( $data ) ) {
                $notice = '<div class="notice notice-error is-dismissible"><p><strong>Ghalti:</strong> Invalid JSON format! Barah-e-karam neechay diye gaye sample JSON format k mutabiq paste karein.</p></div>';
            } else {
                $count = count( $data );
                $imported = 0;

                // Calculate date increments if start and end dates are provided
                $start_ts = ! empty( $start_date ) ? strtotime( $start_date ) : false;
                $end_ts = ! empty( $end_date ) ? strtotime( $end_date ) : false;
                $step = 0;
                if ( $start_ts && $end_ts && $count > 1 ) {
                    $step = ( $end_ts - $start_ts ) / ( $count - 1 );
                }

                foreach ( $data as $index => $item ) {
                    $student_name = sanitize_text_field( $item['student_name'] ?? ( $item['name'] ?? 'Cadet' ) );
                    $review_text = sanitize_textarea_field( $item['review_text'] ?? ( $item['review'] ?? '' ) );
                    $review_number = isset( $item['review_number'] ) ? intval( $item['review_number'] ) : 1;
                    if ( $review_number < 1 || $review_number > 5 ) {
                        $review_number = 1;
                    }

                    // Determine course
                    $item_course = ! empty( $selected_course ) ? $selected_course : ( sanitize_text_field( $item['course'] ?? 'PMA Long Course' ) );
                    if ( ! in_array( $item_course, $courses ) && ! empty( $selected_course ) ) {
                        $item_course = $selected_course;
                    }

                    $stars = isset( $item['stars'] ) ? intval( $item['stars'] ) : 5;
                    if ( $stars < 1 || $stars > 5 ) {
                        $stars = 5;
                    }

                    $receipt_id = sanitize_text_field( $item['payment_receipt_id'] ?? ( $item['receipt_id'] ?? ( 'OA-' . rand( 10000, 99999 ) ) ) );

                    // Determine review date
                    if ( $start_ts && $end_ts && $count > 1 ) {
                        $current_ts = $start_ts + ( $step * $index );
                        $review_date = date( 'Y-m-d', $current_ts );
                    } elseif ( ! empty( $item['date'] ) ) {
                        $review_date = date( 'Y-m-d', strtotime( $item['date'] ) );
                    } elseif ( $start_ts ) {
                        $review_date = date( 'Y-m-d', $start_ts );
                    } else {
                        $review_date = current_time( 'Y-m-d' );
                    }

                    $inserted = $wpdb->insert(
                        $table_name,
                        array(
                            'student_name'       => $student_name,
                            'review_text'        => $review_text,
                            'review_number'      => $review_number,
                            'course'             => $item_course,
                            'stars'              => $stars,
                            'payment_receipt_id' => $receipt_id,
                            'status'             => $import_status,
                            'review_date'        => $review_date,
                            'created_at'         => current_time( 'mysql' )
                        ),
                        array( '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
                    );

                    if ( $inserted ) {
                        $imported++;
                    }
                }

                $notice = '<div class="notice notice-success is-dismissible"><p><strong>Mubarak!</strong> Total <strong>' . $imported . ' reviews</strong> kamyabi se import ho chuke hen aur frontend pr date sequence k mutabiq display ho jaen ge!</p></div>';
            }
        }
    }

    // Default sample JSON for template display
    $sample_json_text = json_encode( array(
        array(
            "student_name" => "Muhammad Hamza Khan",
            "review_text" => "Alhamdulillah cleared PMA Long Course Initial Test and ISSB with recommendation from Kohat! Officers Academy notes and mock interviews were 100% accurate.",
            "review_number" => 1,
            "course" => "PMA Long Course",
            "stars" => 5,
            "payment_receipt_id" => "OA-PAY-88210",
            "date" => date( 'Y-m-d' )
        ),
        array(
            "student_name" => "Ayesha Siddiqua",
            "review_text" => "AFNS initial test cleared in 1st attempt! The biology and intelligence tests prepared by Officers Academy were extremely helpful.",
            "review_number" => 2,
            "course" => "AFNS",
            "stars" => 5,
            "payment_receipt_id" => "OA-PAY-77149",
            "date" => date( 'Y-m-d', strtotime( '-3 days' ) )
        ),
        array(
            "student_name" => "Shahzaib Ali",
            "review_text" => "Got recommended for 158 GD Pilot from Gujranwala ISSB! The psychological guidance and GTO tasks practice in academy gave me tremendous confidence.",
            "review_number" => 3,
            "course" => "GD Pilot",
            "stars" => 5,
            "payment_receipt_id" => "OA-PAY-65431",
            "date" => date( 'Y-m-d', strtotime( '-7 days' ) )
        )
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

    ?>
    <div class="wrap oa-admin-wrap">
        <div class="oa-header-badge">
            <div class="oa-logo-title">
                <h1>Officers Academy - Bulk Reviews Importer</h1>
                <p class="subtitle">AI generated ya sample JSON file paste karein aur aik click me tamam reviews date sequence k sath frontend pr publish karein.</p>
            </div>
            <div class="oa-header-meta">
                <span class="oa-tag-pill"><i class="dashicons dashicons-yes-alt"></i> Shortcode: <code>[officers_academy_reviews]</code></span>
            </div>
        </div>

        <?php echo $notice; ?>

        <div class="oa-grid-container">
            <!-- Left Column: Bulk Import Form -->
            <div class="oa-card oa-main-form-card">
                <h2><i class="dashicons dashicons-upload"></i> Bulk Import Reviews via JSON</h2>
                <form method="post" action="">
                    <?php wp_nonce_field( 'oa_bulk_import_action', 'oa_bulk_import_nonce' ); ?>

                    <div class="oa-form-group">
                        <label for="target_course">
                            <strong>1. Select Course for Bulk Reviews (Aap kis course me add krna chahte hen?):</strong>
                        </label>
                        <select name="target_course" id="target_course" class="oa-select" required>
                            <option value="">-- Select Target Course --</option>
                            <?php foreach ( $courses as $c ) : ?>
                                <option value="<?php echo esc_attr( $c ); ?>"><?php echo esc_html( $c ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Agar aap course select karein ge tu JSON k tamam reviews is specific course me sequence se add honge.</p>
                    </div>

                    <div class="oa-form-row">
                        <div class="oa-form-group half">
                            <label for="start_date"><strong>2. Start Date (Shuru ki Tareekh):</strong></label>
                            <input type="date" name="start_date" id="start_date" value="<?php echo date( 'Y-m-d', strtotime( '-30 days' ) ); ?>" class="oa-input">
                        </div>
                        <div class="oa-form-group half">
                            <label for="end_date"><strong>3. End Date (Aakhri Tareekh):</strong></label>
                            <input type="date" name="end_date" id="end_date" value="<?php echo date( 'Y-m-d' ); ?>" class="oa-input">
                        </div>
                    </div>
                    <p class="description">Agar aap Start aur End date select karein ge tu bulk reviews naturally in tareekhon pr date sequence me distribute ho jaen ge.</p>

                    <div class="oa-form-group">
                        <label for="import_status"><strong>4. Publish Status:</strong></label>
                        <select name="import_status" id="import_status" class="oa-select">
                            <option value="approved">Approved & Live (Directly frontend pr display ho jaen)</option>
                            <option value="pending">Pending Verification (Payment check krne k bad manually approve karein)</option>
                        </select>
                    </div>

                    <div class="oa-form-group">
                        <div class="oa-label-row">
                            <label for="reviews_json"><strong>5. Paste JSON Array (Yahan JSON Paste Karein):</strong></label>
                            <button type="button" class="button button-small" id="oa-paste-sample-btn">Fill Sample JSON</button>
                        </div>
                        <textarea name="reviews_json" id="reviews_json" rows="14" class="oa-textarea-code" placeholder="Paste your JSON array here... e.g. [ { 'student_name': '...', 'review_text': '...', 'review_number': 1, 'stars': 5, 'payment_receipt_id': 'OA-12345' } ]"></textarea>
                    </div>

                    <div class="oa-submit-area">
                        <button type="submit" name="oa_bulk_import_submit" class="button button-primary button-hero oa-btn-submit">
                            <i class="dashicons dashicons-cloud-upload"></i> Import & Publish All Reviews Now
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right Column: AI Prompt & Sample Format -->
            <div class="oa-card oa-sidebar-card">
                <h2><i class="dashicons dashicons-welcome-learn-more"></i> Pre-Sample & AI Instructions</h2>
                <p>Neeche dya gya sample format he jo AI (ChatGPT ya Gemini) ko de kr bulk reviews generate krwae ja skte hen:</p>

                <div class="oa-sample-box">
                    <div class="oa-sample-header">
                        <span><strong>Sample JSON Template:</strong></span>
                        <button type="button" class="button button-small" id="oa-copy-sample-btn">Copy Sample</button>
                    </div>
                    <pre id="oa-sample-json-content"><?php echo esc_html( $sample_json_text ); ?></pre>
                </div>

                <div class="oa-ai-prompt-box">
                    <div class="oa-sample-header">
                        <span><strong>AI Prompt (ChatGPT / Gemini):</strong></span>
                        <button type="button" class="button button-small" id="oa-copy-prompt-btn">Copy AI Prompt</button>
                    </div>
                    <textarea id="oa-ai-prompt-text" rows="8" readonly class="oa-prompt-readonly">Please generate 15 realistic student reviews for Officers Academy in valid JSON format.
Courses to choose from: PMA Long Course, AFNS, GD Pilot, TCC, Medical Cadet Course, PN Cadets, Lady Cadet Course, ISSB.
Keys for each item:
- "student_name": Pakistani student name
- "review_text": Realistic feedback praising Officers Academy notes, faculty, initial test clearance & ISSB
- "review_number": Integer between 1 and 5
- "course": Exact course name
- "stars": 5 (or 4)
- "payment_receipt_id": Receipt format like "OA-PAY-XXXXX"
- "date": YYYY-MM-DD
Return ONLY the pure JSON array.</textarea>
                </div>

                <div class="oa-help-card">
                    <h4>💡 Important Tips:</h4>
                    <ul>
                        <li>AI se prompt copy kr k ChatGPT me paste karein.</li>
                        <li>Waha se JSON copy kr k yahan paste karein.</li>
                        <li>Course select kr k <strong>Import & Publish</strong> dabayein.</li>
                        <li>Frontend page pr reviews date-wise sequence me nazar aane lagen ge!</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * 7. Admin Page: Manage All Reviews (View receipt IDs, filter, delete, approve)
 */
function oa_reviews_admin_manage_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'oa_reviews';
    $courses = oa_reviews_get_courses();
    $notice = '';

    // 1. Handle Action: Delete by Course & Date Range
    if ( isset( $_POST['oa_delete_by_filter_submit'] ) && check_admin_referer( 'oa_delete_filter_action', 'oa_delete_filter_nonce' ) ) {
        $del_course = sanitize_text_field( $_POST['del_course'] ?? '' );
        $del_start_date = sanitize_text_field( $_POST['del_start_date'] ?? '' );
        $del_end_date = sanitize_text_field( $_POST['del_end_date'] ?? '' );
        $del_status = sanitize_text_field( $_POST['del_status'] ?? 'all' );

        $where_clauses = array();
        $params = array();

        if ( ! empty( $del_course ) && $del_course !== 'all' ) {
            $where_clauses[] = "course = %s";
            $params[] = $del_course;
        }
        if ( ! empty( $del_start_date ) ) {
            $where_clauses[] = "review_date >= %s";
            $params[] = $del_start_date;
        }
        if ( ! empty( $del_end_date ) ) {
            $where_clauses[] = "review_date <= %s";
            $params[] = $del_end_date;
        }
        if ( ! empty( $del_status ) && $del_status !== 'all' ) {
            $where_clauses[] = "status = %s";
            $params[] = $del_status;
        }

        if ( empty( $where_clauses ) ) {
            $notice = '<div class="notice notice-error is-dismissible"><p><strong>Ghalti:</strong> Barah-e-karam kam az kam Course ya Date Range muntakhib karein!</p></div>';
        } else {
            $sql = "DELETE FROM $table_name WHERE " . implode( ' AND ', $where_clauses );
            if ( ! empty( $params ) ) {
                $sql = $wpdb->prepare( $sql, $params );
            }
            $deleted_rows = $wpdb->query( $sql );
            $course_label = ( empty( $del_course ) || $del_course === 'all' ) ? 'Tamam Courses' : esc_html( $del_course );
            $date_label = ( ! empty( $del_start_date ) || ! empty( $del_end_date ) ) ? " (Date: {$del_start_date} se {$del_end_date} tak)" : '';
            $notice = '<div class="notice notice-warning is-dismissible"><p><strong>Success:</strong> Total <strong>' . intval( $deleted_rows ) . ' reviews</strong> Course [' . $course_label . ']' . $date_label . ' k mutabiq kamyabi se delete kr diye gye hen!</p></div>';
        }
    }

    // 2. Handle Action: Bulk Delete Selected via Checkboxes
    if ( isset( $_POST['oa_bulk_delete_selected'] ) && check_admin_referer( 'oa_bulk_manage_action', 'oa_bulk_manage_nonce' ) ) {
        if ( ! empty( $_POST['selected_review_ids'] ) && is_array( $_POST['selected_review_ids'] ) ) {
            $ids = array_map( 'intval', $_POST['selected_review_ids'] );
            $ids_str = implode( ',', $ids );
            $deleted_cnt = $wpdb->query( "DELETE FROM $table_name WHERE id IN ($ids_str)" );
            $notice = '<div class="notice notice-warning is-dismissible"><p>Total <strong>' . intval( $deleted_cnt ) . ' selected reviews</strong> kamyabi se delete kr diye gye hen.</p></div>';
        }
    }

    // 3. Handle Action: Approve Single
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'approve' && isset( $_GET['review_id'] ) && check_admin_referer( 'oa_manage_action' ) ) {
        $id = intval( $_GET['review_id'] );
        $wpdb->update( $table_name, array( 'status' => 'approved' ), array( 'id' => $id ) );
        $notice = '<div class="notice notice-success is-dismissible"><p>Review ID #' . $id . ' kamyabi se approve ho chuka he aur frontend pr live he!</p></div>';
    }

    // 4. Handle Action: Delete Single
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['review_id'] ) && check_admin_referer( 'oa_manage_action' ) ) {
        $id = intval( $_GET['review_id'] );
        $wpdb->delete( $table_name, array( 'id' => $id ) );
        $notice = '<div class="notice notice-warning is-dismissible"><p>Review ID #' . $id . ' delete kr dya gya he.</p></div>';
    }

    // 5. Handle Bulk Action: Delete All Reviews
    if ( isset( $_POST['oa_delete_all'] ) && check_admin_referer( 'oa_delete_all_action', 'oa_delete_all_nonce' ) ) {
        $wpdb->query( "TRUNCATE TABLE $table_name" );
        $notice = '<div class="notice notice-error is-dismissible"><p>Tamam reviews database se clear kr diye gye hen.</p></div>';
    }

    // Filter by course for view table
    $filter_course = sanitize_text_field( $_GET['course_filter'] ?? '' );
    $where = '1=1';
    if ( ! empty( $filter_course ) ) {
        $where .= $wpdb->prepare( " AND course = %s", $filter_course );
    }

    $reviews = $wpdb->get_results( "SELECT * FROM $table_name WHERE $where ORDER BY review_date DESC, id DESC LIMIT 300" );
    $total_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
    $approved_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE status = 'approved'" );
    $pending_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE status = 'pending' OR status = 'pending_verification'" );

    ?>
    <div class="wrap oa-admin-wrap">
        <div class="oa-header-badge">
            <div class="oa-logo-title">
                <h1>Officers Academy - Manage Reviews</h1>
                <p class="subtitle">Students k submit kiye gaye reviews verify karein, aur Course ya Date Range k mutabiq bulk reviews delete karein.</p>
            </div>
            <div class="oa-counts-summary">
                <span class="oa-stat-pill">Total: <strong><?php echo intval( $total_count ); ?></strong></span>
                <span class="oa-stat-pill success">Live/Approved: <strong><?php echo intval( $approved_count ); ?></strong></span>
                <span class="oa-stat-pill warning">Pending Verification: <strong><?php echo intval( $pending_count ); ?></strong></span>
            </div>
        </div>

        <?php echo $notice; ?>

        <!-- SPECIAL TOOL: Delete by Course & Date Range -->
        <div class="oa-card oa-delete-filter-card">
            <div class="oa-delete-header">
                <h3><span class="dashicons dashicons-trash"></span> Delete Reviews by Course & Date Range (Course & Tareekh k Mutabiq Delete Karein)</h3>
                <p class="description">Aap kisi b specific course k reviews ya specific date range (shuru se aakhri tareekh) k reviews aik click me delete kr skte hen:</p>
            </div>
            <form method="post" action="" onsubmit="return confirm('Kia aap waqai muntakhib karda Course aur Date Range k reviews delete krna chahte hen? Yeh amal wapis nahi aae ga.');">
                <?php wp_nonce_field( 'oa_delete_filter_action', 'oa_delete_filter_nonce' ); ?>
                <div class="oa-delete-form-grid">
                    <div class="oa-del-col">
                        <label for="del_course"><strong>1. Select Course:</strong></label>
                        <select name="del_course" id="del_course" class="oa-select">
                            <option value="all">-- All Courses (Tamam Courses) --</option>
                            <?php foreach ( $courses as $c ) : ?>
                                <option value="<?php echo esc_attr( $c ); ?>"><?php echo esc_html( $c ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="oa-del-col">
                        <label for="del_start_date"><strong>2. From Date (Is tareekh se):</strong></label>
                        <input type="date" name="del_start_date" id="del_start_date" class="oa-input">
                    </div>

                    <div class="oa-del-col">
                        <label for="del_end_date"><strong>3. To Date (Is tareekh tak):</strong></label>
                        <input type="date" name="del_end_date" id="del_end_date" class="oa-input">
                    </div>

                    <div class="oa-del-col">
                        <label for="del_status"><strong>4. Status:</strong></label>
                        <select name="del_status" id="del_status" class="oa-select">
                            <option value="all">All (Approved & Pending)</option>
                            <option value="approved">Only Approved (Live)</option>
                            <option value="pending">Only Pending Verification</option>
                        </select>
                    </div>

                    <div class="oa-del-col oa-del-btn-col">
                        <label>&nbsp;</label>
                        <button type="submit" name="oa_delete_by_filter_submit" class="button button-danger oa-btn-del-danger">
                            <span class="dashicons dashicons-trash"></span> Delete Filtered Reviews
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Course Filter Toolbar -->
        <div class="oa-manage-toolbar">
            <form method="get" action="" class="oa-filter-form">
                <input type="hidden" name="page" value="oa-reviews-manage">
                <label for="course_filter"><strong>Filter Table View by Course:</strong></label>
                <select name="course_filter" id="course_filter" onchange="this.form.submit()">
                    <option value="">-- All Courses (Tamam Courses) --</option>
                    <?php foreach ( $courses as $c ) : ?>
                        <option value="<?php echo esc_attr( $c ); ?>" <?php selected( $filter_course, $c ); ?>><?php echo esc_html( $c ); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <form method="post" action="" onsubmit="return confirm('Kia aap waqai database k TAMAM reviews delete krna chahte hen?');">
                <?php wp_nonce_field( 'oa_delete_all_action', 'oa_delete_all_nonce' ); ?>
                <button type="submit" name="oa_delete_all" class="button button-link-delete">Delete All Reviews (Clear Database)</button>
            </form>
        </div>

        <!-- Reviews Table with Checkboxes -->
        <form method="post" action="" onsubmit="return confirm('Kia aap waqai muntakhib karda tamam reviews delete krna chahte hen?');">
            <?php wp_nonce_field( 'oa_bulk_manage_action', 'oa_bulk_manage_nonce' ); ?>
            <div class="oa-card">
                <div style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <button type="submit" name="oa_bulk_delete_selected" class="button button-secondary">
                            <span class="dashicons dashicons-trash"></span> Delete Selected Reviews
                        </button>
                    </div>
                    <div style="font-size: 13px; color: #64748b;">
                        Showing latest reviews sorted date-wise sequence
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="30"><input type="checkbox" id="oa-select-all-reviews"></th>
                            <th width="50">ID</th>
                            <th width="140">Student Name</th>
                            <th width="150">Course</th>
                            <th width="100">Review # / Stars</th>
                            <th width="140">Payment Receipt ID</th>
                            <th width="160">Review Text</th>
                            <th width="110">Date</th>
                            <th width="120">Status</th>
                            <th width="130">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $reviews ) ) : ?>
                            <tr>
                                <td colspan="10" style="text-align:center; padding: 25px;">Koi review mojood nahi he. <a href="<?php echo admin_url( 'admin.php?page=oa-reviews-bulk' ); ?>">Bulk JSON Import</a> se add karein.</td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $reviews as $r ) : ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_review_ids[]" value="<?php echo esc_attr( $r->id ); ?>" class="oa-review-cb"></td>
                                    <td>#<?php echo esc_html( $r->id ); ?></td>
                                    <td><strong><?php echo esc_html( $r->student_name ); ?></strong></td>
                                    <td><span class="oa-course-badge"><?php echo esc_html( $r->course ); ?></span></td>
                                    <td>
                                        <div>Review #<?php echo esc_html( $r->review_number ); ?></div>
                                        <div class="oa-stars-text"><?php echo str_repeat( '★', intval( $r->stars ) ); ?></div>
                                    </td>
                                    <td>
                                        <code class="oa-receipt-code"><?php echo esc_html( $r->payment_receipt_id ); ?></code>
                                    </td>
                                    <td>
                                        <span class="oa-trimmed-review" title="<?php echo esc_attr( $r->review_text ); ?>" style="cursor:help; border-bottom: 1px dotted #94a3b8;">
                                            <?php echo esc_html( wp_trim_words( $r->review_text, 2, '...' ) ); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html( date( 'd M Y', strtotime( $r->review_date ) ) ); ?></td>
                                    <td>
                                        <?php if ( $r->status === 'approved' ) : ?>
                                            <span class="oa-badge-status approved"><i class="dashicons dashicons-yes"></i> Approved</span>
                                        <?php else : ?>
                                            <span class="oa-badge-status pending"><i class="dashicons dashicons-clock"></i> Pending Verify</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( $r->status !== 'approved' ) : ?>
                                            <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=oa-reviews-manage&action=approve&review_id=' . $r->id ), 'oa_manage_action' ); ?>" class="button button-small button-primary" title="Approve & Show on Front Page">Approve</a>
                                        <?php endif; ?>
                                        <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=oa-reviews-manage&action=delete&review_id=' . $r->id ), 'oa_manage_action' ); ?>" class="button button-small button-link-delete" onclick="return confirm('Delete this review?');" title="Delete Review">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
    <?php
}

/**
 * 8. Frontend Shortcode [officers_academy_reviews]
 */
add_shortcode( 'officers_academy_reviews', 'oa_reviews_shortcode_render' );
function oa_reviews_shortcode_render( $atts ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'oa_reviews';

    // Enqueue frontend styles and scripts
    wp_enqueue_style( 'oa-reviews-frontend-css' );
    wp_enqueue_script( 'oa-reviews-frontend-js' );

    $courses = oa_reviews_get_courses();

    // Fetch approved reviews ordered date-wise sequence
    $reviews = $wpdb->get_results( "SELECT * FROM $table_name WHERE status = 'approved' ORDER BY review_date DESC, id DESC" );

    // Calculate rating stats
    $total_reviews = count( $reviews );
    $avg_rating = 4.9;
    if ( $total_reviews > 0 ) {
        $sum = 0;
        foreach ( $reviews as $r ) {
            $sum += intval( $r->stars );
        }
        $avg_rating = round( $sum / $total_reviews, 1 );
    }

    ob_start();
    ?>
    <div class="oa-reviews-wrapper" id="oa-reviews-app">

        <!-- Academy Trust & Badges Banner -->
        <div class="oa-trust-header">
            <div class="oa-academy-tagline">
                <span class="oa-shield-icon">🛡️</span>
                <span class="oa-tag-text">Official Armed Forces Preparation Portal • Pakistan Army, Navy, PAF</span>
            </div>

            <div class="oa-main-title-row">
                <div>
                    <h2 class="oa-title">Officers Academy Cadet Reviews</h2>
                    <p class="oa-subtitle">Real feedback from recommended cadets across Pakistan. Verified preparation excellence for PMA, AFNS, GD Pilot, Navy & ISSB.</p>
                </div>
                <div class="oa-action-header-btn">
                    <button type="button" class="oa-btn-write-review" id="oa-open-modal-btn">
                        <span class="oa-icon-pen">✍️</span> Write a Review
                    </button>
                </div>
            </div>

            <!-- Trust Badges Bar -->
            <div class="oa-trust-badges-grid">
                <div class="oa-trust-badge-item">
                    <div class="oa-badge-icon">🎖️</div>
                    <div class="oa-badge-info">
                        <strong>100% Verified Reviews</strong>
                        <span>Payment & Receipt ID Checked</span>
                    </div>
                </div>

                <div class="oa-trust-badge-item">
                    <div class="oa-badge-icon">⭐</div>
                    <div class="oa-badge-info">
                        <strong><?php echo esc_html( $avg_rating ); ?> / 5.0 Rating</strong>
                        <span>Based on <?php echo esc_html( $total_reviews > 0 ? $total_reviews : '2,800+' ); ?> cadet reviews</span>
                    </div>
                </div>

                <div class="oa-trust-badge-item">
                    <div class="oa-badge-icon">🎯</div>
                    <div class="oa-badge-info">
                        <strong>92% Recommendation Rate</strong>
                        <span>Initial Test & ISSB Success</span>
                    </div>
                </div>

                <div class="oa-trust-badge-item">
                    <div class="oa-badge-icon">🇵🇰</div>
                    <div class="oa-badge-info">
                        <strong>Pakistan's #1 Academy</strong>
                        <span>Over 2,500+ Commissioned Officers</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course Filter Tabs -->
        <div class="oa-filter-tabs-container">
            <div class="oa-filter-label">Filter by Course:</div>
            <div class="oa-filter-tabs">
                <button type="button" class="oa-filter-btn active" data-course="all">All Courses (<?php echo esc_html( $total_reviews ); ?>)</button>
                <?php foreach ( $courses as $course_name ) : ?>
                    <button type="button" class="oa-filter-btn" data-course="<?php echo esc_attr( $course_name ); ?>"><?php echo esc_html( $course_name ); ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Reviews Grid -->
        <div class="oa-reviews-grid" id="oa-reviews-list">
            <?php if ( empty( $reviews ) ) : ?>
                <div class="oa-no-reviews-box">
                    <p>Filhal koi review mojood nahi he. "Write a Review" button pe click kr k pehla review add karein!</p>
                </div>
            <?php else : ?>
                <?php foreach ( $reviews as $rev ) : ?>
                    <div class="oa-review-card" data-course="<?php echo esc_attr( $rev->course ); ?>">
                        <div class="oa-card-top">
                            <div class="oa-student-info">
                                <div class="oa-avatar">
                                    <?php echo esc_html( strtoupper( substr( $rev->student_name, 0, 1 ) ) ); ?>
                                </div>
                                <div>
                                    <h4 class="oa-student-name"><?php echo esc_html( $rev->student_name ); ?></h4>
                                    <span class="oa-verified-tick"><svg width="14" height="14" viewBox="0 0 24 24" fill="#16a34a"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg> Verified Student</span>
                                </div>
                            </div>
                            <div class="oa-review-num-tag">
                                Review #<?php echo esc_html( $rev->review_number ); ?>
                            </div>
                        </div>

                        <div class="oa-card-meta">
                            <span class="oa-course-tag"><?php echo esc_html( $rev->course ); ?></span>
                            <div class="oa-stars-row" title="<?php echo esc_attr( $rev->stars ); ?> out of 5 stars">
                                <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                                    <span class="oa-star <?php echo $i <= $rev->stars ? 'filled' : ''; ?>">★</span>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="oa-card-body">
                            <p class="oa-review-text">“<?php echo esc_html( $rev->review_text ); ?>”</p>
                        </div>

                        <div class="oa-card-footer">
                            <span class="oa-date"><i class="dashicons dashicons-calendar-alt"></i> <?php echo esc_html( date( 'd M, Y', strtotime( $rev->review_date ) ) ); ?></span>
                            <span class="oa-receipt-masked">Receipt: <?php echo esc_html( substr( $rev->payment_receipt_id, 0, 6 ) . '***' ); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Write a Review Modal Form -->
        <div class="oa-modal-overlay" id="oa-review-modal" style="display:none;">
            <div class="oa-modal-container">
                <button type="button" class="oa-modal-close" id="oa-close-modal-btn">&times;</button>
                <div class="oa-modal-header">
                    <span class="oa-modal-badge">Officers Academy</span>
                    <h3>Write a Cadet Review</h3>
                    <p>Apna qeemti tajurba aur feedback share karein ta k deegar students ko rahnumai mil ske.</p>
                </div>

                <form id="oa-front-review-form" class="oa-modal-form">
                    <!-- Student Name -->
                    <div class="oa-form-field">
                        <label for="oa_student_name">Student Full Name <span class="req">*</span></label>
                        <input type="text" id="oa_student_name" name="student_name" placeholder="E.g. Muhammad Ali" required class="oa-input-text">
                    </div>

                    <!-- Course & Review Number Row -->
                    <div class="oa-form-grid-2">
                        <div class="oa-form-field">
                            <label for="oa_course">Select Course <span class="req">*</span></label>
                            <select id="oa_course" name="course" required class="oa-select-field">
                                <option value="">-- Select Course --</option>
                                <?php foreach ( $courses as $c ) : ?>
                                    <option value="<?php echo esc_attr( $c ); ?>"><?php echo esc_html( $c ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="oa-form-field">
                            <label for="oa_review_number">Review Number (1 to 5) <span class="req">*</span></label>
                            <select id="oa_review_number" name="review_number" required class="oa-select-field">
                                <option value="1">Review #1 (Initial Test)</option>
                                <option value="2">Review #2 (Physical & Medical)</option>
                                <option value="3">Review #3 (ISSB Guidance)</option>
                                <option value="4">Review #4 (Academic Classes)</option>
                                <option value="5">Review #5 (Final Recommendation)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Stars & Payment Receipt ID Row -->
                    <div class="oa-form-grid-2">
                        <div class="oa-form-field">
                            <label>Select Stars (1 to 5) <span class="req">*</span></label>
                            <div class="oa-star-picker" id="oa-star-picker">
                                <span class="oa-star-choice active" data-value="1">★</span>
                                <span class="oa-star-choice active" data-value="2">★</span>
                                <span class="oa-star-choice active" data-value="3">★</span>
                                <span class="oa-star-choice active" data-value="4">★</span>
                                <span class="oa-star-choice active" data-value="5">★</span>
                                <input type="hidden" name="stars" id="oa_stars_input" value="5">
                                <span class="oa-star-label" id="oa-star-label">5 Stars (Excellent)</span>
                            </div>
                        </div>

                        <div class="oa-form-field">
                            <label for="oa_payment_receipt_id">Payment Receipt ID <span class="req">*</span></label>
                            <input type="text" id="oa_payment_receipt_id" name="payment_receipt_id" placeholder="E.g. OA-REC-98210" required class="oa-input-text">
                        </div>
                    </div>

                    <!-- Review Text -->
                    <div class="oa-form-field">
                        <label for="oa_review_text">Review Text <span class="req">*</span></label>
                        <textarea id="oa_review_text" name="review_text" rows="4" placeholder="Officers Academy k bary me apna tajurba tafseel se likhein..." required class="oa-textarea-text"></textarea>
                    </div>

                    <div class="oa-modal-footer">
                        <button type="submit" class="oa-submit-btn" id="oa-submit-review-btn">
                            <span>Submit Review</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Success Modal with Green Checkmark (Tick) -->
        <div class="oa-modal-overlay" id="oa-success-modal" style="display:none;">
            <div class="oa-modal-container oa-success-container">
                <button type="button" class="oa-modal-close" id="oa-close-success-btn">&times;</button>
                
                <div class="oa-success-content">
                    <!-- Animated Green Tick Icon -->
                    <div class="oa-green-tick-circle">
                        <svg class="oa-checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                            <circle class="oa-checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                            <path class="oa-checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                        </svg>
                    </div>

                    <h3 class="oa-success-title">Review Submitted!</h3>
                    
                    <div class="oa-success-message-box">
                        <p class="oa-urdu-msg">apka review add ho chuka he payment verify krne k bad yaha display kr dya jae ga</p>
                        <p class="oa-en-submsg">Your review has been successfully received. It will be verified with your Payment Receipt ID and displayed here shortly.</p>
                    </div>

                    <button type="button" class="oa-btn-done" id="oa-done-btn">Theek he (Done)</button>
                </div>
            </div>
        </div>

    </div>
    <?php
    return ob_get_clean();
}

/**
 * 9. Front-end AJAX Submission Handler
 */
add_action( 'wp_ajax_oa_submit_review', 'oa_reviews_handle_front_submit' );
add_action( 'wp_ajax_nopriv_oa_submit_review', 'oa_reviews_handle_front_submit' );
function oa_reviews_handle_front_submit() {
    check_ajax_referer( 'oa_review_submit_nonce', 'nonce' );

    global $wpdb;
    $table_name = $wpdb->prefix . 'oa_reviews';

    $student_name = sanitize_text_field( $_POST['student_name'] ?? '' );
    $review_text = sanitize_textarea_field( $_POST['review_text'] ?? '' );
    $review_number = intval( $_POST['review_number'] ?? 1 );
    $course = sanitize_text_field( $_POST['course'] ?? '' );
    $stars = intval( $_POST['stars'] ?? 5 );
    $receipt_id = sanitize_text_field( $_POST['payment_receipt_id'] ?? '' );

    if ( empty( $student_name ) || empty( $review_text ) || empty( $course ) || empty( $receipt_id ) ) {
        wp_send_json_error( array( 'message' => 'Please fill in all required fields.' ) );
    }

    if ( $stars < 1 || $stars > 5 ) {
        $stars = 5;
    }
    if ( $review_number < 1 || $review_number > 5 ) {
        $review_number = 1;
    }

    // Insert as pending_verification (waiting for admin to verify receipt)
    $inserted = $wpdb->insert(
        $table_name,
        array(
            'student_name'       => $student_name,
            'review_text'        => $review_text,
            'review_number'      => $review_number,
            'course'             => $course,
            'stars'              => $stars,
            'payment_receipt_id' => $receipt_id,
            'status'             => 'pending_verification',
            'review_date'        => current_time( 'Y-m-d' ),
            'created_at'         => current_time( 'mysql' )
        ),
        array( '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
    );

    // Per user requirement: "beshk review add ho ya na ho isko bs ye show ho k apka review add ho chuka he payment verify krne k bad yaha display kr dya jae ga"
    wp_send_json_success( array(
        'message' => 'apka review add ho chuka he payment verify krne k bad yaha display kr dya jae ga'
    ) );
}
