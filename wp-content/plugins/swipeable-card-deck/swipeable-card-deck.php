<?php
/**
 * Plugin Name: Swipeable Card Deck
 * Description: A swipeable card deck where the top card is swipeable and the cards are displayed at an angle.
 * Version: 1.1
 * Author: Your Name
 * License: GPL2
 */

// Enqueue necessary styles and scripts
function scd_enqueue_scripts() {
    wp_enqueue_style( 'scd-style', plugin_dir_url( __FILE__ ) . 'style.css' );
    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'scd-swipe', plugin_dir_url( __FILE__ ) . 'script.js', ['jquery'], null, true );
    wp_localize_script( 'scd-swipe', 'scd_ajax_object', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('scd_nonce')
    ]);
}
add_action( 'wp_enqueue_scripts', 'scd_enqueue_scripts' );

// Shortcode to display the swipeable card deck
function scd_display_card_deck() {
    $job_offers = get_option('scd_job_list2', []);

    ob_start();
    ?>
    <div class="card-deck">
    <?php foreach ($job_offers as $index => $job):
        $jobDate        = isset($job[0]) ? esc_html($job[0]) : '';
        $jobField       = isset($job[1]) ? esc_html($job[1]) : '';
        $jobDescription = isset($job[2]) ? esc_html($job[2]) : '';
        $phoneNumber    = isset($job[3]) ? esc_html($job[3]) : '';
        $jobEmail       = isset($job[4]) ? sanitize_email($job[4]) : '';
        $rotation       = ($index % 2 === 0) ? 5 : -5;
    ?>
        <div class="card" data-card="card-<?php echo $index + 1; ?>" data-email="<?php echo esc_attr($jobEmail); ?>" style="transform: rotate(<?php echo $rotation; ?>deg);">
            <div class="card-inner">
                <div class="card-top">
                    <div class="blue-line"></div>
                    <p class="job-field"><?php echo $jobField; ?></p>
                </div>
                <div class="card-content">
                    <p class="job-date"><?php echo $jobDate; ?></p>
                    <p class="job-description"><?php echo $jobDescription; ?></p>
                </div>
                <button class="uncover-phone" data-phone="<?php echo esc_attr($phoneNumber); ?>">
                    <i class="phone-icon fa fa-phone" aria-hidden="true"></i> טלפון
                    <span class="phone-number"><?php echo $phoneNumber; ?></span>
                </button>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <div class="swipe-instruction">Swipe the top card!</div>

    <!-- Modal for job applications -->
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

    <?php if ( is_user_logged_in() ): ?>
        <div class="post-job-link-container" style="text-align:center; margin:20px 0;">
            <a href="https://indexing.co.il/%d7%a4%d7%a8%d7%a1%d7%9d-%d7%9e%d7%a9%d7%a8%d7%94" class="post-job-link">פרסם משרה חדשה</a>
        </div>
    <?php else: ?>
        <div class="post-job-link-container" style="text-align:center; margin:20px 0;">
            <a href="https://indexing.co.il/login" class="post-job-link">התחבר כדי לפרסם משרה</a>
        </div>
    <?php endif;

    return ob_get_clean();
}
add_shortcode( 'card_swipe2', 'scd_display_card_deck' );

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
    if ( isset($_POST['remove_job_index']) && current_user_can('manage_options') ) {
        $index = intval($_POST['remove_job_index']);
        $jobs = get_option('scd_job_list2', []);
        if (isset($jobs[$index])) {
            unset($jobs[$index]);
            $jobs = array_values($jobs); // Re-index array
            update_option('scd_job_list2', $jobs);
            echo '<div class="updated"><p>המשרה נמחקה בהצלחה.</p></div>';
        }
    }

    // Handle new job submission
    if ( isset($_POST['scd_submit_job']) && current_user_can('manage_options') ) {
        $job_field       = sanitize_text_field($_POST['job_field'] ?? '');
        $job_description = sanitize_text_field($_POST['job_description'] ?? '');
        $job_phone       = sanitize_text_field($_POST['job_phone'] ?? '');

        // Validate phone if provided
        if ( !empty($job_phone) && !preg_match('/^\+?[0-9\s\-]{7,15}$/', $job_phone) ) {
            echo '<div class="error"><p>מספר טלפון לא תקין.</p></div>';
        } else {
            $current_user = wp_get_current_user();
            $user_email = $current_user->user_email;

            $new_job = [
                date('j F Y'),
                $job_field,
                $job_description,
                $job_phone,
                $user_email
            ];

            $jobs = get_option('scd_job_list2', []);
            $jobs[] = $new_job;
            update_option('scd_job_list2', $jobs);

            echo '<div class="updated"><p>המשרה נוספה בהצלחה!</p></div>';
        }
    }

    ?>
    <div class="wrap">
        <h2>הוסף משרה חדשה</h2>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th><label for="job_field">תחום</label></th>
                    <td><input type="text" name="job_field" id="job_field" required></td>
                </tr>
                <tr>
                    <th><label for="job_description">תיאור</label></th>
                    <td><input type="text" name="job_description" id="job_description" required></td>
                </tr>
                <tr>
                    <th><label for="job_phone">טלפון</label></th>
                    <td><input type="text" name="job_phone" id="job_phone" placeholder="לא חובה"></td>
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
                $existing_jobs = get_option('scd_job_list2', []);
                foreach ($existing_jobs as $index => $job):
                    ?>
                    <tr>
                        <td><?php echo esc_html($job[0]); ?></td>
                        <td><?php echo esc_html($job[1]); ?></td>
                        <td><?php echo esc_html($job[2]); ?></td>
                        <td><?php echo esc_html($job[3]); ?></td>
                        <td><?php echo esc_html($job[4]); ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('האם אתה בטוח שברצונך למחוק את המשרה?');">
                                <input type="hidden" name="remove_job_index" value="<?php echo $index; ?>">
                                <input type="submit" class="button button-danger" value="🗑 מחק">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// AJAX handler to send application emails with uploaded resume
add_action('wp_ajax_scd_send_application', 'scd_send_application');
add_action('wp_ajax_nopriv_scd_send_application', 'scd_send_application');

function scd_send_application() {
    check_ajax_referer('scd_nonce', 'nonce');

    if ( empty($_FILES['resume']) || empty($_POST['email']) ) {
        wp_send_json_error('Missing data');
    }

    $email        = sanitize_email($_POST['email']);
    $cover_letter = sanitize_textarea_field($_POST['cover_letter'] ?? '');
    $job_field    = sanitize_text_field($_POST['job_field'] ?? '');
    $job_date     = sanitize_text_field($_POST['job_date'] ?? '');
    $job_content  = sanitize_text_field($_POST['job_content'] ?? '');

    // Handle file upload securely
    $uploaded = wp_handle_upload($_FILES['resume'], ['test_form' => false]);

    if ( isset($uploaded['error']) ) {
        wp_send_json_error('Upload error: ' . $uploaded['error']);
    }

    $subject = "New Job Application for: $job_field";
    $message = "Job: $job_field\nDate: $job_date\nDescription: $job_content\n\nCover Letter:\n$cover_letter";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    $attachments = [$uploaded['file']];

    $sent = wp_mail($email, $subject, $message, $headers, $attachments);

    if ( $sent ) {
        wp_send_json_success('Email sent!');
    } else {
        wp_send_json_error('Failed to send email');
    }
}

// Frontend job submission shortcode
function scd_frontend_job_submission() {
    if ( !is_user_logged_in() ) {
        return '<p>עליך להתחבר כדי לפרסם משרה. <a href="/login">התחבר</a> או <a href="/register">הירשם</a>.</p>';
    }

    $current_user = wp_get_current_user();
    $default_phone = get_user_meta($current_user->ID, 'phone', true);

    ob_start();

    if ( isset($_POST['scd_submit_job']) ) {
        $user_email = $current_user->user_email;

        $job_field       = sanitize_text_field($_POST['job_field'] ?? '');
        $job_description = sanitize_text_field($_POST['job_description'] ?? '');
        $job_phone       = sanitize_text_field($_POST['job_phone'] ?? '');

        if ( !empty($job_phone) && !preg_match('/^\+?[0-9\s\-]{7,15}$/', $job_phone) ) {
            echo '<p style="color:red;">מספר טלפון לא תקין.</p>';
        } else {
            $new_job = [
                date('j F Y'),
                $job_field,
                $job_description,
                $job_phone,
                $user_email,
            ];

            $jobs = get_option('scd_job_list2', []);
            $jobs[] = $new_job;
            update_option('scd_job_list2', $jobs);

            // Optional: Send notification email to user
            wp_mail($user_email, 'New Job Posted', 'Job Title: ' . $job_field . "\nDescription: " . $job_description);

            echo '<p style="color:green;">המשרה נוספה בהצלחה!</p>';
        }
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
add_shortcode('submit_job2', 'scd_frontend_job_submission');
