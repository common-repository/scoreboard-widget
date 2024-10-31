<?php
/*
Plugin Name:  Scoreboard Widget
Description:  Create Custom scores post type and widget. Display scores at widget.
Version:      1.1
Author:       Jonathan Wong
Text Domain:  scoreboard-widget
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html

**************************************************************************/
if ( ! defined( 'ABSPATH' ) ){
	exit; // Exit if accessed this file directly
} 

add_action( 'admin_enqueue_scripts', 'jc_score_load_admin_styles' );
function jc_score_load_admin_styles() {
    $current_screen = get_current_screen();
    if ( strpos($current_screen->id, 'jc_scoreboard') === false) {
        return;
    } else {
        wp_enqueue_style('jc_score_admin_css', plugins_url('/css/jc-scores-admin-style.css', __FILE__));        
    }
}

add_action('wp_enqueue_scripts', 'jc_score_callback_for_setting_up_scripts');
function jc_score_callback_for_setting_up_scripts() {
    wp_enqueue_style('jc_score_front_css', plugins_url('/css/jc-scores-styles.css', __FILE__));
    wp_enqueue_script('js_score_front_js', plugins_url('/js/jc-scores-scripts.js', __FILE__), '','1.0',true);
    wp_enqueue_script('js_score_front_elastislide_js', plugins_url('/js/jquery.elastislide.js', __FILE__), '','1.0',true);
}



// Register Scores Custom Post Type
add_action( 'init', 'jc_score_create_scores' );
function jc_score_create_scores() {
    register_post_type( 'jc_scoreboard',
        array(
            'labels' => array(
                'name' => 'Scores',
                'singular_name' => 'Score',
                'add_new_item'        => 'New Score',
                'all_items' => 'All Scores',
                'add_new' => 'Add New Score',
                'add_new_item' => 'Add New Score',
                'edit_item' => 'Edit Score',
                'new_item' => 'New Score',
                'view_item' => 'View Scores',
                'not_found' =>  'No Scores found',
                'not_found_in_trash' => 'No Scores found in Trash',
            ),
        'supports' => array('title','author', 'page-attributes'),
        'public' => true,
        'has_archive' => false,
        )
    );
}

// create a new taxonomy
add_action( 'init', 'jc_score_scores_init' );
function jc_score_scores_init() {
    
    register_taxonomy(
        'jc_scores_cat',
        'jc_scoreboard',
        array(
            'label' => 'Score Categories',
            'rewrite' => array( 'slug' => 'scores' ),
            'hierarchical' => true,
            'query_var' => true,
            'supports' => array('title','author', 'page-attributes')
        )
    );
}

$jc_meta_box_prefix = 'jc_';

$meta_box = array(
    'id' => 'scores-box',
    'title' => 'Scores Info',
    'page' => 'jc_scoreboard',
    'context' => 'normal',
    'priority' => 'high',
    'fields' => array(
        array(
            'name' => 'Score Status',
            'desc' => 'Enter score status (eg. "Mon 1:00pm" or "Final")',
            'id' => $jc_meta_box_prefix . 'score_status',
            'type' => 'text',
        ),
        array(
            'name' => 'Away Team',
            'desc' => 'Enter away team (eg. "USA")',
            'id' => $jc_meta_box_prefix . 'away_team',
            'type' => 'text',
        ),
        array(
            'name' => 'Home Team',
            'desc' => 'Enter home team (eg. "FRA")',
            'id' => $jc_meta_box_prefix . 'home_team',
            'type' => 'text',
        ),
        array(
            'name' => 'Away Team Score',
            'desc' => 'Enter away team score (eg. "1")',
            'id' => $jc_meta_box_prefix . 'away_team_score',
            'type' => 'text',
        'std' => ' 0'
        ),
        array(
            'name' => 'Home Team Score',
            'desc' => 'Enter home team score (eg. "1")',
            'id' => $jc_meta_box_prefix . 'home_team_score',
            'type' => 'text',
        'std' => ' 0'
        )
    )
);

add_action('admin_menu', 'jc_scores_add_box');

// Add meta box
function jc_scores_add_box() {
    global $meta_box;
    add_meta_box($meta_box['id'], $meta_box['title'], 'jc_scores_show_box', $meta_box['page'], $meta_box['context'], $meta_box['priority']);
}

// Callback function to show fields in meta box
function jc_scores_show_box() {
    global $meta_box, $post;
    echo '<input type="hidden" name="scores_meta_box_nonce" value="', wp_create_nonce(basename(__FILE__)), '" />';
    echo '<table class="form-table">';

    foreach ($meta_box['fields'] as $field) {
        // get current post meta data
        $meta = get_post_meta($post->ID, $field['id'], true);

        echo '<tr>',
                '<th style="width:20%"><label for="', $field['id'], '"><strong>', $field['name'], ':</strong></label></th>',
                '<td>';
        switch ($field['type']) {
            case 'text':
                echo '<input type="text" name="', $field['id'], '" id="', $field['id'], '" value="', $meta ? $meta : $field['std'], '" size="30" style="width:97%" />',
                    '<br /><small>', $field['desc'],'</small>';
                break;
            case 'textarea':
                echo '<textarea name="', $field['id'], '" id="', $field['id'], '" cols="60" rows="4" style="width:97%">', $meta ? $meta : $field['std'], '</textarea>',
                    '<br />', $field['desc'];
                break;
            case 'select':
                echo '<select name="', $field['id'], '" id="', $field['id'], '">';
                foreach ($field['options'] as $option) {
                    echo '<option', $meta == $option ? ' selected="selected"' : '', '>', $option, '</option>';
                }
                echo '</select>';
                break;
            case 'radio':
                foreach ($field['options'] as $option) {
                    echo '<input type="radio" name="', $field['id'], '" value="', $option['value'], '"', $meta == $option['value'] ? ' checked="checked"' : '', ' />', $option['name'];
                }
                break;
            case 'checkbox':
                echo '<input type="checkbox" name="', $field['id'], '" id="', $field['id'], '"', $meta ? ' checked="checked"' : '', ' />';
                break;
        }
        echo    '<td>',
            '</tr>';
    }

    echo '</table>';
}

add_action('save_post', 'jc_scores_save_data');

// Save data from meta box
function jc_scores_save_data($post_id) {
    global $meta_box;

    // verify nonce
    if ( !isset( $_POST['scores_meta_box_nonce'] ) || !wp_verify_nonce( $_POST['scores_meta_box_nonce'], basename(__FILE__) ) ) {
        return $post_id;
    }

    // check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }

    // check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return $post_id;
    }

    foreach ($meta_box['fields'] as $field) {
        $old = get_post_meta($post_id, $field['id'], true);
        $new = isset( $_POST[$field['id']] ) ? $_POST[$field['id']] : '';

        if ($new && $new != $old) {
            update_post_meta($post_id, $field['id'], sanitize_text_field($new));
        } elseif ('' == $new && $old) {
            delete_post_meta($post_id, $field['id'], sanitize_text_field($old));
        }
    }
}


// Register Scores widget
add_action( 'widgets_init', 'jc_scoreboard_widgets' );

function jc_scoreboard_widgets() {
    register_widget( 'jc_scoreboard_widget' );
}

class jc_scoreboard_widget extends WP_Widget {

     //Widget setup.
    function jc_scoreboard_widget() {
        // Widget settings
        $widget_ops = array( 'classname' => 'jc_scoreboard_widget', 'description' => __('A scoreboard widget.', 'jc_scoreboard_widget') );

        // Widget control settings
        $control_ops = array( 'width' => '100%', 'height' => 'auto', 'id_base' => 'jc_scoreboard_widget' );

        // Create the widget
        $this->__construct( 'jc_scoreboard_widget', __('JC : Scoreboard Widget', 'jc_scoreboard_widget'), $widget_ops, $control_ops );
    }

    // How to display the widget on the screen.
    function widget( $args, $instance ) {
        extract( $args );

        // Get variables from the widget settings.
        $score_cat = $instance['score_cat'];
        $scores = get_terms('jc_scores_cat','orderby=count&hide_empty=0');
        ?>
            <div class="score-wrapper tabber-container">
                <ul class="score-nav tabs">
                <?php
                    if ($score_cat) {
                        foreach ($scores as $score) {
                            $i = 1;
                            foreach ($score_cat as $cat) {
                                if ($cat == $score->term_id) { ?>
                                    <li><a href="#tab<?php echo $i ?>"><?php echo $score->name; ?></a></li>
                                    <?php 
                                }
                                $i++;
                            }
                        }
                    }
                ?>
                </ul>
                
                <?php 
                $u = 1;
                foreach ($score_cat as $cat) { ?>
                <div id="tab<?php echo $u; ?>" class="carousel es-carousel es-carousel-wrapper tabber-content">
                    <ul class="score-list">
                        <?php global $post; $recent = new WP_Query(array( 'post_type' => 'jc_scoreboard', 'showposts' => '999', 'tax_query' => array(array( 'taxonomy' => 'jc_scores_cat', 'field' => 'terms_id', 'terms' => $cat ))  )); while($recent->have_posts()) : $recent->the_post();
                        if (get_post_meta($post->ID, "jc_away_team", true) && get_post_meta($post->ID, "jc_home_team", true)) { ?>
                        <li>
                            <a href="javascript:void(0)" class="score-link" rel="bookmark">
                            <span class="score-status"><?php echo get_post_meta($post->ID, "jc_score_status", true); ?></span>
                            <div class="score-teams">
                                <span class="team away-team"><?php echo get_post_meta($post->ID, "jc_away_team", true); ?></span>
                                <span class="team home-team"><?php echo get_post_meta($post->ID, "jc_home_team", true); ?></span>
                            </div><!--score-teams-->
                            <div class="score-right">
                                <span class="score away-score"><?php echo get_post_meta($post->ID, "jc_away_team_score", true); ?></span>
                                <span class="score home-score"><?php echo get_post_meta($post->ID, "jc_home_team_score", true); ?></span>
                            </div><!--score-right-->
                            </a>
                        </li>
                        <?php
                        }
                        endwhile; ?>
                    </ul><!--score-list-->
                </div><!--tab-->
                <?php $u++; }

    }

    // Update the widget settings.
    function update( $new_instance, $old_instance ) {
        $instance = $old_instance;
        
        $instance['score_cat'] = esc_sql( $new_instance['score_cat'] );
        return $instance;
    }


    function form( $instance ) {
        //Check instance is array or not
        if (is_array($option = $instance['score_cat'])){
            $option = $instance['score_cat'];
        } else {
            $option = array();
        } 
        $scores = get_terms('jc_scores_cat', 'orderby=count&hide_empty=0');
        ?>

        <!-- Category Option -->
        <p><?php //print_r($scores); ?>
            <label for="<?php echo $this->get_field_id('score_cat'); ?>">Select Score Category to Display (Multiple):</label>
            <select multiple id="<?php echo $this->get_field_id('score_cat'); ?>" name="<?php echo $this->get_field_name('score_cat'); ?>[]" style="width:100%;">
                <option value='all' disabled>Select category(s):</option>
                <?php                    
                    foreach($scores as $score) { ?>
                <option value="<?php echo $score->term_id; ?>" <?php if (in_array($score->term_id, $option)) echo 'selected="selected"'; ?>><?php echo $score->name; ?></option>
                <?php } ?>
            </select>
        </p>
    <?php
    }
}

?>