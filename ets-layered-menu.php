<?php
/**
 * Plugin Name: ETS Layered Menu
 * Plugin URI:  https://www.expresstechsoftwares.com/
 * Description: Layered menu for right to left sliding effect for sub menus on mobile devicies
 * Version: 1.0.0
 * Author: ExpressTech Softwares Solutions
 * Requires at least: 5.4
 * Text Domain: ets_layered_menu
 */
 
if (!defined('ABSPATH')) {
	exit;
}

define('LM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LM_PLUGIN_PATH', plugin_dir_path(__FILE__));

class ETS_LAYERED_MENU {

	private static $instance = null;

	/**
	 * Yes, a blank constructor, to implement singleton,
	 * to keep the real essence of WordPress project, 
	 * so that someone can easily unhook any of our method
	 * 
	 * @return void
	 */
	private function __construct()
	{
		
	}	

	/**
	 * Register the WP hooks
	 * 
	 * @return void
	 */
	public static function register(){
		$plugin = self::get_instance();

		// Frontend
		add_filter( 'nav_menu_submenu_css_class', array($plugin, 'child_menu_ul_classes'), 10, 3);
		add_action( 'wp_enqueue_scripts', array($plugin, 'scripts') );
		add_filter( 'wp_footer', array($plugin, 'menujs'));
		add_filter( 'wp_setup_nav_menu_item', array($plugin, 'menu_image_wp_setup_nav_menu_item') );
		add_filter( 'nav_menu_item_title', array($plugin, 'menu_image_nav_menu_item_title_filter'), 10, 4 );
		add_filter( 'the_title', array($plugin, 'menu_image_nav_menu_item_title_filter'), 10, 4 );

		// Admin
		add_action( 'admin_head-nav-menus.php', array($plugin, 'menu_image_admin_head_nav_menus_action') );
		add_filter( 'wp_ajax_ets-set-menu-item-thumbnail', array($plugin, 'ajax_set_menu_item_thumbnail'));
		add_action( 'wp_nav_menu_item_custom_fields', array($plugin, 'menu_item_custom_fields'), 10, 4 );		
	}

	/**
	 * Singleton pattern
	 * Method explicity for creating the object
	 * of this class
	 * 
	 * @return object, an object of this class
	 */
	public static function get_instance()
	{
		if (self::$instance == null)
		{
		  self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Adds classes to child menus
	 * 
	 * @param array $classes CSS classes applied to the menu <ul> element
	 * @param object $args an object of wp_nav_menu()
	 * @param int $depth depth of menu item
	 * 
	 * @return array
	 */
	public function child_menu_ul_classes($classes, $args, $depth) {
		if ( array_search('sub-menu', $classes) !== false ) {
			if ( ($key = array_search('children', $classes)) !== false ) {
				unset($classes[$key]);
			}
			$classes[] = 'children-sub-menu-over';
			$classes[] = 'sublevel' . $depth;
		}
		

		return $classes;
	}	

	/**
	 * Enqueue scripts
	 * 
	 * @return void
	 */
	public function scripts() {
		wp_enqueue_style(
			'ets-layered-menu',
			LM_PLUGIN_URL . '/assets/css/style.css',
			[],
			'1.3'
		);
	}

	/**
	 * JS to swap sub-menu on frontend
	 * 
	 * @return void
	 */
	public function menujs() {
	?>
		<script type="text/javascript">
			jQuery(function(){

				jQuery("#main-menu ul.sub-menu").prepend("<li class='submenu-back'><button class='submenu-backbtn'>Back</button></li>");

				jQuery(document).on("click", ".menu-item.menu-item-has-children a", function(e){
					let subMenuUl = jQuery(this).siblings(".children-sub-menu-over");
					if ( subMenuUl.length > 0 ) {
						e.preventDefault();
						jQuery(this).siblings(".children-sub-menu-over").addClass("show");
					}
					return true;
				});	

				jQuery(document).on("click", ".submenu-back", function(e){
					console.log( jQuery(this).closest(".sub-menu").length );
					jQuery(this).closest(".sub-menu").removeClass('show');
				});	
			});
		</script>
	<?php
	}

	/**
	 * Loads media JS and string literals for admin
	 * menu editor
	 * 
	 * @return void
	 */
	public function menu_image_admin_head_nav_menus_action() {
		wp_enqueue_script( 'ets-menu-image-admin', LM_PLUGIN_URL . '/assets/js/admin-menu-image.js' , array( 'jquery' ), '2.9.6' );
		wp_localize_script(
			'ets-menu-image-admin', 'menuImage', array(
				'l10n'     => array(
					'uploaderTitle'      => __( 'Choose menu image', 'ets_layered_menu' ),
					'uploaderButtonText' => __( 'Select', 'ets_layered_menu' ),
				),
				'settings' => array(
					'nonce' => wp_create_nonce( 'update-menu-item' ),
				),
			)
		);
		wp_enqueue_media();
		wp_enqueue_style( 'editor-buttons' );
	}

	/**
	 * Handles the AJAX request from admin
	 * menu editor to save menu item image
	 * 
	 * @return void
	 */
	public function ajax_set_menu_item_thumbnail() {
		$json = ! empty( $_REQUEST['json'] );

		$post_ID = intval( $_POST['post_id'] );
		if ( ! current_user_can( 'edit_post', $post_ID ) ) {
			wp_die( - 1 );
		}

		$thumbnail_id = intval( $_POST['thumbnail_id'] );
		$is_hovered   = (bool) $_POST['is_hover'];

		check_ajax_referer( 'update-menu-item' );

		if ( $thumbnail_id == '-1' ) {
			$success = delete_post_thumbnail( $post_ID );
		} else {
			$success = set_post_thumbnail( $post_ID, $thumbnail_id );
		}

		if ( $success ) {
			$return = $this->wp_post_thumbnail_only_html( $post_ID );
			$json ? wp_send_json_success( $return ) : wp_die( $return );
		}

		wp_die( 0 );
	}

	/**
	 * Generated the HTML for thumbnail on menu editor
	 * 
	 * @param int $item_id menu item ID
	 * 
	 * @return string
	 */
	protected function wp_post_thumbnail_only_html( $item_id ) {
		$markup = '<p class="description description-thin" ><label>%s<br /><a title="%s" href="#" class="set-post-thumbnail button%s" data-item-id="%s" style="height: auto;">%s</a>%s</label></p>';

		$thumbnail_id = get_post_thumbnail_id( $item_id );
		$content      = sprintf(
			$markup,
			esc_html__( 'Menu image', 'ets_layered_menu' ),
			$thumbnail_id ? esc_attr__( 'Change menu item image', 'ets_layered_menu' ) : esc_attr__( 'Set menu item image', 'menu-image' ),
			'',
			$item_id,
			$thumbnail_id ? wp_get_attachment_image( $thumbnail_id, [36, 36] ) : esc_html__( 'Set image', 'ets_layered_menu' ),
			$thumbnail_id ? '<a href="#" class="remove-post-thumbnail">' . __( 'Remove', 'ets_layered_menu' ) . '</a>' : ''
		);

		return $content;	
	}

	/**
	 * Generates the custom option to upload
	 * the menu image in the WP menu edior
	 * 
	 * @param int $item_id menu item ID
	 * @param object $item menu item object (WP_Post)
	 * @param int $depth depth of menu item
	 * @param object $args an object of menu item arguments
	 * 
	 * @return void
	 */
	public function menu_item_custom_fields( $item_id, $item, $depth, $args ) {
		if ( ! $item_id && isset( $item->ID ) ) {
			$item_id = $item->ID;
		}

		?>
		<div class="field-image hide-if-no-js wp-media-buttons">
			<div class='menu-item-images' style='min-height:70px'>
				<?php echo $this->wp_post_thumbnail_only_html($item_id); ?>
			</div>
		</div>
	<?php
	}

	/**
	 * For frontend, sets the thumbnail ID in the menu item
	 * 
	 * @param object $item menu item
	 * 
	 * @return object
	 */
	public function menu_image_wp_setup_nav_menu_item( $item ) {
		if ( ! isset( $item->thumbnail_id ) ) {
			$item->thumbnail_id = get_post_thumbnail_id( $item->ID );
		}

		return $item;
	}	

	/**
	 * Adds the menu item image to the frontend menu
	 * also adds the necessary HTML structure
	 * 
	 * @param string $title title of menu item
	 * @param object $item menu item object (WP_Post)
	 * @param int $depth depth of the menu item
	 * @param object $args object of menu item arguments
	 * 
	 * @return string
	 */
	public function menu_image_nav_menu_item_title_filter( $title, $item = null, $depth = null, $args = null ) {

		if ( strpos( $title, 'menu-image' ) > 0 || ! is_nav_menu_item( $item ) || ! isset( $item ) ) {
			return $title;
		}

		if ( is_numeric( $item ) && $item < 0 ) {
			return $title;
		}

		if ( is_numeric( $item ) && $item > 0 ) {
			$item = wp_setup_nav_menu_item( get_post( $item ) );
		}

		// Process only if there is an menu image associated with the menu item.
		if ( '' !== $item->thumbnail_id && $item->thumbnail_id > 0 ) {
			$class = 'ets-imaged-title-menu-img';
			$image = wp_get_attachment_image( $item->thumbnail_id, [50, 50], false, "class=menu-image {$class}" );
			$class = 'ets-imaged-title-menu';
			$none = '';
			$title = vsprintf( '%s<span class="%s">%s</span>%s', [$none, $class, $title, $image] );
		}

		return $title;
	}

} 

ETS_LAYERED_MENU::register();