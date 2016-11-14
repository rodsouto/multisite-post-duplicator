<?php
/**
 * 
 * This file controls the generation and functionality  of the MPD Metabox.
 * 
 * @since 0.4
 * @author Mario Jaconelli <mariojaconelli@gmail.com>
 * @author Sergi Ambel
 * 
 */

if ( ! defined( 'ABSPATH' ) ) exit('You\'re not allowed to see this page');

if ( is_multisite() ) {

    add_action( 'add_meta_boxes', 'mpd_metaboxes' );

}

/**
 * 
 * This function initialises the MPD Metabox on the WordPress Post
 * 
 * Before displaying this function will check the plugin settings option to make sure the use wants to
 * display the metabox or not depending on the post type.
 * 
 * @since 0.4
 * @return null
 * 
 */
function mpd_metaboxes(){
    
    $active_mpd = apply_filters( 'mpd_is_active', true );

    $post_types = mpd_get_postype_decision_from_options();

    if($post_types){

        foreach ($post_types as $page ){

            if ($active_mpd && current_user_can(mpd_get_required_cap()))  {

                    $priority = apply_filters( 'mpd_metabox_priority', 'high' );

                    add_meta_box( 'multisite_clone_metabox', "<i class='fa fa-clone' aria-hidden='true'></i> " . __('Multisite Post Duplicator', 'multisite-post-duplicator' ), 'mpd_publish_top_right', $page, 'side', $priority );

                    do_action('mpd_meta_box', $page);
                   
            }

        } 

    }

    add_action('admin_notices', 'mpd_plugin_admin_notices');
    
}


/**
 * 
 * This function generates the markup for the MPD Metabox
 * 
 * @since 0.4
 * @return null
 * 
 */
function mpd_publish_top_right(){

    $post_statuses  = mpd_get_post_statuses();
    $sites          = mpd_wp_get_sites();
    $options        = get_option( 'mdp_settings' );

    add_thickbox();

    ?>

    <div id="clone_multisite_box">

        <div class="metabox">

            <?php do_action('mpd_before_metabox_content'); ?>

            <p><?php _e('Duplicated post status', 'multisite-post-duplicator' ); ?>:

            <select id="mpd-new-status" name="mpd-new-status" style="width:100%">

                <?php foreach ($post_statuses as $post_status_key => $post_status_value): ?>
                    
                    <option value="<?php echo $post_status_key;?>" <?php echo $post_status_key == 'draft' ? 'selected' : '' ?>><?php echo $post_status_value;?></option>
                
                <?php endforeach ?>
            
            </select>
               
            </p>

            <p><?php _e('Title prefix for new post', 'multisite-post-duplicator' ); ?>:
            
                <input type="text" style="width:100%" name="mpd-prefix" value="<?php echo mpd_get_prefix(); ?>"/>
                
            </p>
             <script>

                jQuery(document).ready(function($) { 
                    accordionClick('.ps-link', '.ps-content', 'fast');
                });

            </script>
            <p><?php _e('Site(s) you want duplicate to', 'multisite-post-duplicator' ); ?> <i class="fa fa-info-circle ps-link accord" aria-hidden="true"></i> :</p>

            <p class="mpdtip ps-content" style="display:none"><?php _e('If you have checked any of the checkboxes above then this post will be duplicated on save.', 'multisite-post-duplicator' );?></p>

                <ul id="mpd_blogschecklist" data-wp-lists="list:category" class="mpd_blogschecklist">

                    <?php $current_blog_id = get_current_blog_id(); ?>

                    <?php foreach ($sites as $site): ?>

                        <?php if (current_user_can_for_blog($site->blog_id, mpd_get_required_cap()) && !in_array($site->blog_id, mpd_get_restrict_some_sites_options())) : ?>

                            <?php $blog_details = get_blog_details($site->blog_id); ?>
                            
                                <li id="mpd_blog_<?php echo $site->blog_id; ?>" class="mpd-site-checkbox">

                                    <label class="selectit">

                                        <input value="<?php echo $site->blog_id; ?>" type="checkbox" name="mpd_blogs[]" id="in_blog_<?php echo $site->blog_id; ?>">  <?php echo $site->blog_id == $current_blog_id ? '<em>' : ''; ?><?php echo $blog_details->blogname; ?> <?php echo $site->blog_id == $current_blog_id ? ' <small>(Current Site)</small></em>' : ''; ?>

                                    </label>

                                </li>
                            
                        <?php endif; ?>

                    <?php endforeach; ?>

                </ul>
            </p>
           
            <?php do_action('mpd_after_metabox_content'); ?>
            
        </div>

    </div>

<?php
}

/**
 * 
 * This function sets up the MPD core function and calls it based on values added by the user in the MPD metabox
 * 
 * @since 0.4
 * @param int $post_id The post ID of the post currently being viewed.
 * @return int The post ID of the post currently being viewed.
 * 
 */

function mpd_clone_post($post_id){


    if (!count($_POST)){

        return $post_id;
        
    }
    
    if(    ( isset($_POST["post_status"] ) )
        && ( $_POST["post_status"] != "auto-draft" )
        && ( isset($_POST['mpd_blogs'] ) )
        && ( count( $_POST['mpd_blogs'] ) )
        && ( $_POST["post_ID"] == $post_id ) //hack to avoid execution in cloning process
    ){

    $mpd_blogs = $_POST['mpd_blogs'];

        foreach( $mpd_blogs as $mpd_blog_id ){


                $createdPost = mpd_duplicate_over_multisite($_POST["ID"], $mpd_blog_id, $_POST["post_type"], get_current_user_id(), $_POST["mpd-prefix"], $_POST["mpd-new-status"]);
                
                if($_POST['persist']){
					
					$args = array();
					
                    $args['source_id'] = get_current_blog_id();
                    $args['destination_id'] = $mpd_blog_id;
                    $args['source_post_id'] = $_POST['ID'];
                    $args['destination_post_id'] = $createdPost['id'];
                    
                    mpd_add_persist($args);

                }
                
            }      

    }

    return $post_id;

}

add_filter( 'save_post', 'mpd_clone_post' );