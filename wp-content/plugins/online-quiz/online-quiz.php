<?php
/**
 * Plugin Name: Online Quiz
 * Plugin URI: https://example.com/online-quiz 
 * Description: A simple online quiz plugin.
 * Version: 1.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL2 
 */

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



// Hook to add a shortcode for displaying quiz selection
add_shortcode( 'quiz_selection', 'display_quiz_selection' );

// Hook to add a shortcode
add_shortcode( 'online_quiz', 'display_online_quiz' );

// Register the shortcode
add_shortcode( 'quiz_results', 'display_quiz_results' );



// Include your trivia game functionality file (trivia-game.php)
require_once plugin_dir_path(__FILE__) . 'trivia-game.php';

add_shortcode('trivia_game', 'trivia_game_shortcode');



require_once plugin_dir_path(__FILE__) . 'select-exam.php';
require_once plugin_dir_path(__FILE__) . 'exam-screen.php';
include(plugin_dir_path(__FILE__) . 'quiz-results.php');

?>
