<?php
defined('ABSPATH') || exit;

function create_post_firebase_form_shortcode() {
    ob_start();

    // Output the common layout/header
    echo do_shortcode('[firebase_layout_page]');

    // Load the form UI from the HTML file
    $html_path = plugin_dir_path(__FILE__) . 'create-post.html';
    if (file_exists($html_path)) {
        echo file_get_contents($html_path);
    } else {
        echo '<p>Error: Could not load the form template.</p>';
    }
    ?>

    <script>
    async function fapCreatePost() {
        const user = firebase.auth().currentUser;
        if (!user) {
            alert('You must be logged in to create a post.');
            return;
        }

        const titleInput = document.getElementById('post_title');
        const contentInput = document.getElementById('post_content');
        const title = titleInput.value.trim();
        const content = contentInput.value.trim();

        if (!title) {
            alert('Please enter a post title.');
            return;
        }

        try {
            await firebase.firestore().collection('posts').add({
                userId: user.uid,
                userEmail: user.email,
                title: title,
                content: content,
                createdAt: firebase.firestore.FieldValue.serverTimestamp()
            });
            alert('Post created successfully!');
            titleInput.value = '';
            contentInput.value = '';
        } catch (err) {
            alert('Failed to create post: ' + err.message);
        }
    }

    // Attach form submission
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('createPostForm');
        if (form) {
            form.addEventListener('submit', e => {
                e.preventDefault();
                fapCreatePost();
            });
        }
    });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('create_post_firebase_form', 'create_post_firebase_form_shortcode');
