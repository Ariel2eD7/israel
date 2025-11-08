<?php
/**
 * Plugin Name: Bulk Word Uploader
 * Description: Upload multiple Word (.docx) files and automatically create posts from them.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

/**
 * Add admin menu page
 */
add_action('admin_menu', function() {
    add_menu_page(
        'Bulk Word Uploader',
        'Word Uploader',
        'manage_options',
        'bulk-word-uploader',
        'bwu_admin_page',
        'dashicons-upload',
        25
    );
});

/**
 * DOCX reader function
 */
function bwu_read_docx($file_path) {
    if (!file_exists($file_path)) return false;

    $zip = new ZipArchive;
    if ($zip->open($file_path) === true) {
        if (($index = $zip->locateName('word/document.xml')) !== false) {
            $content = $zip->getFromIndex($index);
            $zip->close();

            // Convert XML to readable text
            $content = str_replace('</w:p>', "\n", $content);
            $content = strip_tags($content);
            return trim($content);
        }
        $zip->close();
    }
    return false;
}

/**
 * Admin page HTML + Upload logic
 */
function bwu_admin_page() {
    if (!current_user_can('manage_options')) return;

    if (!class_exists('ZipArchive')) {
        echo '<div class="notice notice-error"><p>PHP Zip extension is required.</p></div>';
        return;
    }

    echo '<div class="wrap"><h1>Bulk Word Uploader</h1>';

    // Handle uploads
    if (!empty($_FILES['bwu_files'])) {
        $uploaded = $_FILES['bwu_files'];
        echo '<h2>Upload Results</h2><ul>';

        for ($i = 0; $i < count($uploaded['name']); $i++) {
            if ($uploaded['error'][$i] === 0) {
                $tmp = $uploaded['tmp_name'][$i];
                $name = sanitize_file_name($uploaded['name'][$i]);
                $content = bwu_read_docx($tmp);

                if ($content) {
                    $title = pathinfo($name, PATHINFO_FILENAME);
                    $post_id = wp_insert_post([
                        'post_title'   => $title,
                        'post_content' => $content,
                        'post_status'  => 'publish'
                    ]);

                    if ($post_id) {
                        echo "<li>✅ Uploaded <strong>$name</strong> → Post ID: $post_id</li>";
                    } else {
                        echo "<li>❌ Failed to insert <strong>$name</strong></li>";
                    }
                } else {
                    echo "<li>⚠️ Could not read <strong>$name</strong></li>";
                }
            } else {
                echo "<li>⚠️ Error uploading <strong>{$uploaded['name'][$i]}</strong></li>";
            }
        }

        echo '</ul><p><a href="' . admin_url('admin.php?page=bulk-word-uploader') . '" class="button">← Back</a></p>';
        echo '</div>';
        return;
    }

    // Upload form
    echo '<form method="post" enctype="multipart/form-data">
            <p>Select one or more .docx files to upload:</p>
            <input type="file" name="bwu_files[]" accept=".docx" multiple required>
            <br><br>
            <input type="submit" class="button button-primary" value="Upload and Create Posts">
          </form>
    </div>';
}
