<?php 
/*
 * Plugin Name: Wishlist - Real Estate Manager Extension
 * Plugin URI: https://webcodingplace.com/real-estate-manager-wordpress-plugin/
 * Description: Add properties to wishlist and then bulk contact.
 * Version: 1.0
 * Author: WebCodingPlace
 * Author URI: https://webcodingplace.com/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wishlist-real-estate-manager
 * Domain Path: /languages
*/
 if( ! defined('ABSPATH' ) ){
	exit;
}

define( 'REM_WISHLIST_PATH', untrailingslashit(plugin_dir_path( __FILE__ )) );
define( 'REM_WISHLIST_URL', untrailingslashit(plugin_dir_url( __FILE__ )) );


class REM_WISHLIST {

	function __construct(){

		// adding wishlist button
		add_action( 'rem_listing_footer', array( $this, 'add_wishlist_button' ) , 10, 3 );
        
        add_action( 'rem_single_property_slider', array($this, 'add_wishlist_button_in_single_property' ), 10, 1 );
		// shortcode for wishlist
		add_shortcode( 'rem_wishlist', array( $this, 'rem_wishlist') );

		// ajax calbackes
		add_action( 'wp_ajax_rem_get_wishlist_properties', array( $this, 'get_wishlist_properties' ) );
		add_action( 'wp_ajax_nopriv_rem_get_wishlist_properties', array( $this, 'get_wishlist_properties' ) );
		add_action( 'wp_ajax_rem_wishlist_properties_inquiry', array( $this, 'send_email_about_wishlist_properties' ) );
		add_action( 'wp_ajax_nopriv_rem_wishlist_properties_inquiry', array( $this, 'send_email_about_wishlist_properties' ) );
		
		// add scripts for plugin
		add_action( 'wp_enqueue_scripts', array( $this, 'load_frontend_scripts' ) );

		// var_dump( get_post_meta(125) ); 
	}

	function add_wishlist_button(  $property_id, $style = '' , $target = '' ) {
	    
	    echo ' <img class="rem-loading-img" src="'.REM_WISHLIST_URL.'/loading-icon.gif">';
		echo '<a href="#" title="'.__( "Add to wishlist", "rem-wishlist").'" class="btn btn-default rem-wishlist-btn" data-id="'.$property_id.'" ><i class="far fa-heart"></i>';
		echo '</a>';
	}

	function add_wishlist_button_in_single_property(  $property_id ) {
	    
		echo '<p class="text-center" style="margin-top: 5px;">';
		echo ' <img class="rem-loading-img" src="'.REM_WISHLIST_URL.'/loading-icon.gif">';
		echo '<a href="#" title="'.__( "Add to wishlist", "rem-wishlist").'" class="btn btn-default btn-center rem-wishlist-btn" data-id="'.$property_id.'" ><i class="far fa-heart"></i>';
		echo '</a>';
		echo '<p>';
	}

	function rem_wishlist() {
		
        wp_enqueue_style( 'font-awesome-rem', REM_URL . '/assets/front/css/font-awesome.min.css' );
        wp_enqueue_style( 'rem-bs', REM_URL . '/assets/admin/css/bootstrap.min.css' );
		ob_start();
		include REM_WISHLIST_PATH . '/templates/wishlist.php';
		return ob_get_clean();
	}

	function load_frontend_scripts() {

		wp_enqueue_script( 'rem-sweet-alerts', REM_URL . '/assets/admin/js/sweetalert.min.js' , array('jquery'));
		wp_enqueue_style( 'rem-wishlist-css', REM_WISHLIST_URL . '/css/styles.css' );
		wp_enqueue_script( 'rem-scripts', REM_WISHLIST_URL . '/js/scripts.js' , array('jquery') );
		wp_enqueue_script( 'rem-store2-script', REM_WISHLIST_URL . '/js/store2.js' , array('jquery') );
		$localize_vars = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
        );
        wp_localize_script( 'rem-scripts', 'rem_wishlist_var', $localize_vars );
		
	}

	function get_wishlist_properties() {
		$prop_ids = $_REQUEST['property_ids'];
		$args = array(
			'post_type' => 'rem_property',
			'posts_per_page' => -1,
		    'post__in' => $prop_ids
		);

		$posts = get_posts($args);
		foreach ($posts as $post) {
					
			$html .= 	"<tr>";
				$html .= 	"<td class='img-wrap'>";
					$html .= 	"<label class='product-check-label'>";
						$html .=   "<input type='checkbox' class='property-check' value='" .esc_attr($post->ID)."'>";
						$html .=   "<span class='checkmark'></span>";
					$html .=  "</label>";
					$html .=  get_the_post_thumbnail( $post->ID, array( '50', '50' ));
				$html .= 	"</td>";
				$html .= 	"<td><a href='". get_the_permalink($post->ID)."'>". $post->post_title. "</a> ". get_post_meta($post->ID,'rem_property_address', true)."</td>";
				$html .= 	"<td class='hidden-xs'>". ucfirst(get_post_meta($post->ID,"rem_property_type", true )) ."</td>";
				$html .= 	"<td>";
					$html .= 	"<img class='rem-loading-img' src='". REM_WISHLIST_URL ."/loading-icon.gif'>";
					$html .= 	"<a href='' class='remove-property-btn' data-id='". $post->ID ."'><i class='fa fa-trash'></i></a>";
				$html .= 	"</td>";
			$html .= 	"</tr>";
		}
		// var_dump($html);
		wp_send_json( $html );
	}

	function send_email_about_wishlist_properties(){

		$client_name = sanitize_text_field( $_POST['client_name'] );
		$client_email = sanitize_email($_POST['client_email']);
		$message = "Sender: ".sanitize_email( $_POST['client_email'] )."\n";
		$message .= "Subject: 'Property Inquiry'\n";
			
		$wishlist_properties = explode(",",sanitize_text_field($_POST['ids']));
		$resp = array();
		foreach ($wishlist_properties as $key => $property_id) {

			$property_src = get_permalink( $property_id );
			$property_title = get_the_title( $property_id );
			
			$message .= "Content 'Information about: ".$property_title."', '".sanitize_text_field( $_POST['message'] );
			$mail_status = $this->send_email_agent( $property_id, $client_name, $client_email, $message );
			
			$resp[$property_id] = array(
				'status' => $mail_status,
				'msg'	=> __( 'Email for '. $property_title. ' '. $mail_status, 'rem-wishlist'),
			);
			
		}
		wp_send_json($resp);
		die(0);
	}
	function send_email_agent( $property_id, $client_name, $client_email, $message ){

		$property = get_post($property_id);
		$agent_id = $property->post_author;
        $agent_info = get_userdata($agent_id);
        $agent_email = $agent_info->user_email;

        $headers = array();
        $headers[] = 'From: '.$client_name.'  <'.$client_email.'>' . "\r\n";
        $subject = 'Inquiry form '.$client_name;
        
        if (wp_mail( $agent_email, $subject, $message, $headers )) {
            $resp = 'Sent';
        } else {
            $resp = 'Fail';
        }

        return $resp;
    }

}
add_action('plugins_loaded', 'rem_wishlist_start');
function rem_wishlist_start() {

	return new REM_WISHLIST();
}

