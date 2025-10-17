<?php
/**
 * Plugin Name: Swipeable Card Deck
 * Description: A swipeable card deck where the top card is swipeable and the cards are displayed at an angle.
 * Version: 1.0
 * Author: Your Name
 * License: GPL2
 */

// Enqueue necessary styles and scripts
function scd_enqueue_scripts() {
    wp_enqueue_style( 'scd-style', plugin_dir_url( __FILE__ ) . 'style.css' );
    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'scd-swipe', plugin_dir_url( __FILE__ ) . 'script.js', ['jquery'], '', true );
    wp_localize_script('scd-swipe', 'scd_ajax_object', ['ajaxurl' => admin_url('admin-ajax.php')]);
}
add_action( 'wp_enqueue_scripts', 'scd_enqueue_scripts' );


// Shortcode to display the swipeable card deck
function scd_display_card_deck() {
    $job_offers = get_option('scd_job_list', []);

    ob_start();
    echo '<div class="card-deck">';

    foreach ($job_offers as $index => $job) {
        $jobDate = isset($job[0]) ? esc_html($job[0]) : '';
        $jobField = isset($job[1]) ? esc_html($job[1]) : '';
        $jobDescription = isset($job[2]) ? esc_html($job[2]) : '';
        $phoneNumber = isset($job[3]) ? esc_html($job[3]) : '';
        $jobEmail = isset($job[4]) ? esc_attr($job[4]) : '';

        $rotation = ($index % 2 === 0) ? 5 : -5;

        echo "<div class='card' data-card='card-" . ($index + 1) . "' data-email='{$jobEmail}' style='transform: rotate({$rotation}deg);'>
                <div class='card-inner'>
                    <div class='card-top'>
                        <div class='blue-line'></div>
                        <p class='job-field'>{$jobField}</p>
                    </div>
                    <div class='card-content'>
                        <p class='job-date'>{$jobDate}</p>
                        <p class='job-description'>{$jobDescription}</p>
                    </div>
                    <button class='uncover-phone' data-phone='{$phoneNumber}'>
                        <i class='phone-icon fa fa-phone'></i> טלפון
                        <span class='phone-number'>{$phoneNumber}</span>
                    </button>
                </div>
              </div>";
    }

    echo '</div><div class="swipe-instruction">Swipe the top card!</div>';

    // Modal HTML for job applications
    ?>
    <div id="applyModal" style="display:none;">
        <div class="modal-header"><h3>הגש מועמדות</h3></div>
        <div class="modal-content">
            <p>משרה: <span id="modalJobField"></span></p>
            <p>תאריך: <span id="modalJobDate"></span></p>
            <p>תיאור: <span id="modalJobContent"></span></p>
            <input type="file" id="resumeInput" accept=".pdf,.docx,.txt" />
            <textarea id="coverLetterInput" placeholder="רשום כמה מילים..."></textarea>
            <button id="applyButton">שלח</button>
        </div>
    </div>
    <?php

    // Add "Post Job" or "Login" link
    echo '<div class="post-job-link-container" style="text-align:center; margin:20px 0;">';
    if ( is_user_logged_in() ) {
        echo '<a href="https://indexing.co.il/%d7%a4%d7%a8%d7%a1%d7%9d-%d7%9e%d7%a9%d7%a8%d7%94" class="post-job-link">פרסם משרה חדשה</a>';
    } else {
        echo '<a href="https://indexing.co.il/login" class="post-job-link">התחבר כדי לפרסם משרה</a>';
    }
    echo '</div>';

    return ob_get_clean();
}
add_shortcode( 'card_swipe', 'scd_display_card_deck' );

// Admin menu page for managing job entries
function scd_add_admin_menu() {
    add_menu_page(
        'ניהול משרות',
        'ניהול משרות',
        'manage_options',
        'scd-manage-jobs',
        'scd_render_admin_page',
        'dashicons-id',
        20
    );
}
add_action('admin_menu', 'scd_add_admin_menu');

function scd_render_admin_page() {
    // Handle job removal
    if (isset($_POST['remove_job_index'])) {
        $index = intval($_POST['remove_job_index']);
        $jobs = get_option('scd_job_list', []);
        if (isset($jobs[$index])) {
            unset($jobs[$index]);
            $jobs = array_values($jobs); // Re-index array
            update_option('scd_job_list', $jobs);
            echo '<div class="updated"><p>המשרה נמחקה בהצלחה.</p></div>';
        }
    }

    // Handle form submission
    if (isset($_POST['scd_submit_job'])) {
        $job_field = sanitize_text_field($_POST['job_field']);
        $job_description = sanitize_text_field($_POST['job_description']);
        $job_phone = sanitize_text_field($_POST['job_phone']);

        // Validate phone if not empty
        if (!empty($job_phone) && !preg_match('/^\+?[0-9\s\-]{7,15}$/', $job_phone)) {
            echo '<div class="error"><p>מספר טלפון לא תקין.</p></div>';
            return;
        }

        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;

        $new_job = [
            date('j F Y'),
            $job_field,
            $job_description,
            $job_phone,
            $user_email
        ];

        $jobs = get_option('scd_job_list', []);
        $jobs[] = $new_job;
        update_option('scd_job_list', $jobs);

        echo '<div class="updated"><p>המשרה נוספה בהצלחה!</p></div>';
    }

    // Output form and existing jobs table
    ?>
    <div class="wrap">
        <h2>הוסף משרה חדשה</h2>
        <form method="post">
            <table class="form-table">
                <input type="hidden" name="job_date" value="<?php echo esc_attr(date('j בF Y')); ?>">
                <tr>
                    <th><label for="job_field">תחום</label></th>
                    <td><input type="text" name="job_field" required></td>
                </tr>
                <tr>
                    <th><label for="job_description">תיאור</label></th>
                    <td><input type="text" name="job_description" required></td>
                </tr>
                <tr>
                    <th><label for="job_phone">טלפון</label></th>
                    <td><input type="text" name="job_phone" placeholder="לא חובה"></td>
                </tr>
            </table>
            <?php submit_button('הוסף משרה', 'primary', 'scd_submit_job'); ?>
        </form>

        <h2>רשימת משרות קיימות</h2>
        <table class="widefat fixed" style="max-width:600px;">
            <thead>
                <tr>
                    <th>תאריך</th>
                    <th>תחום</th>
                    <th>תיאור</th>
                    <th>טלפון</th>
                    <th>אימייל</th>
                    <th>מחיקה</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $existing_jobs = get_option('scd_job_list', []);
                foreach ($existing_jobs as $index => $job) {
                    echo '<tr>';
                    echo '<td>' . esc_html($job[0]) . '</td>';
                    echo '<td>' . esc_html($job[1]) . '</td>';
                    echo '<td>' . esc_html($job[2]) . '</td>';
                    echo '<td>' . esc_html($job[3]) . '</td>';
                    echo '<td>' . esc_html($job[4]) . '</td>';
                    echo '<td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="remove_job_index" value="' . $index . '">
                                <input type="submit" class="button button-danger" value="🗑 מחק">
                            </form>
                          </td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}

// AJAX handler for sending job applications
add_action('wp_ajax_scd_send_application', 'scd_send_application');
add_action('wp_ajax_nopriv_scd_send_application', 'scd_send_application'); // Optional: allow non-logged-in users

function scd_send_application() {
    if (!isset($_FILES['resume']) || !isset($_POST['email'])) {
        wp_send_json_error('Missing data');
    }

    $email = sanitize_email($_POST['email']);
    $cover_letter = sanitize_textarea_field($_POST['cover_letter']);
    $job_field = sanitize_text_field($_POST['job_field']);
    $job_date = sanitize_text_field($_POST['job_date']);
    $job_content = sanitize_text_field($_POST['job_content']);

    // Handle file upload
    $uploaded = wp_handle_upload($_FILES['resume'], ['test_form' => false]);

    if (isset($uploaded['error'])) {
        wp_send_json_error('Upload error: ' . $uploaded['error']);
    }

    $subject = "New Job Application for: $job_field";
    $message = "Job: $job_field\nDate: $job_date\nDescription: $job_content\n\nCover Letter:\n$cover_letter";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    $attachments = [$uploaded['file']];

    $sent = wp_mail($email, $subject, $message, $headers, $attachments);

    if ($sent) {
        wp_send_json_success('Email sent!');
    } else {
        wp_send_json_error('Failed to send email');
    }
}

// Frontend job submission shortcode
function scd_frontend_job_submission() {
    if (!is_user_logged_in()) {
        return '<p>עליך להתחבר כדי לפרסם משרה. <a href="/login">התחבר</a> או <a href="/register">הירשם</a>.</p>';
    }

    $current_user = wp_get_current_user();
    $default_phone = get_user_meta($current_user->ID, 'phone', true);

    ob_start();

    if (isset($_POST['scd_submit_job'])) {
        $user_email = $current_user->user_email;

        $job_field = sanitize_text_field($_POST['job_field']);
        $job_description = sanitize_text_field($_POST['job_description']);
        $job_phone = sanitize_text_field($_POST['job_phone']);

        // Validate phone if not empty
        if (!empty($job_phone) && !preg_match('/^\+?[0-9\s\-]{7,15}$/', $job_phone)) {
            echo '<p style="color:red;">מספר טלפון לא תקין.</p>';
            return ob_get_clean();
        }

        $new_job = [
            date('j F Y'),
            $job_field,
            $job_description,
            $job_phone,
            $user_email,
        ];

        $jobs = get_option('scd_job_list', []);
        $jobs[] = $new_job;
        update_option('scd_job_list', $jobs);

        wp_mail($user_email, 'New Job Posted', 'Job Title: ' . $job_field . "\nDescription: " . $job_description);

        echo '<p style="color:green;">המשרה נוספה בהצלחה!</p>';
    }

    ?>
    <form method="post" class="scd-job-form">
        <div class="form-group">
            <label>תחום:</label>
            <input type="text" name="job_field" required>
        </div>
        <div class="form-group">
            <label>תיאור:</label>
            <input type="text" name="job_description" required>
        </div>
        <div class="form-group">
            <label>טלפון (אופציונלי):</label>
            <input type="text" name="job_phone" value="<?php echo esc_attr($default_phone); ?>">
        </div>
        <div class="form-group">
            <input type="submit" name="scd_submit_job" value="🚀 פרסם משרה" class="submit-button">
        </div>
    </form>
    <?php

    return ob_get_clean();
}
add_shortcode('submit_job', 'scd_frontend_job_submission');
