<?php
/**
 * Divi module classes. Loaded only when Divi's builder is ready.
 */

defined( 'ABSPATH' ) || exit;

trait Waypost_Divi_Common {

	public $vb_support = 'on';
	public $type       = 'child'; // Matches the original working build; avoids notices that break Divi saves.

	protected $module_credits = array(
		'module_uri' => 'https://anerisdesigns.com/waypost',
		'author'     => 'Aneris Designs',
		'author_uri' => 'https://anerisdesigns.com',
	);

	public function init() {
		$this->name             = $this->waypost_name();
		$this->main_css_element = '%%order_class%%';
	}

	public function get_fields() {
		return array(
			'admin_label' => array(
				'label'       => et_builder_i18n( 'Admin Label' ),
				'type'        => 'text',
				'toggle_slug' => 'admin_label',
			),
		);
	}

	/** The Visual Builder gets a placeholder; the real output renders on the front end. */
	public function render( $attrs, $content, $render_slug ) {
		if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
			return '<div class="waypost-divi-placeholder">' . esc_html( $this->waypost_name() ) . ' · ' . esc_html__( 'preview on the front end', 'waypost' ) . '</div>';
		}
		return $this->waypost_output();
	}
}

class Waypost_Divi_Menu extends ET_Builder_Module {
	use Waypost_Divi_Common;
	public $slug = 'et_pb_waypost_menu';
	protected function waypost_name() { return __( 'Waypost Docs Menu', 'waypost' ); }
	protected function waypost_output() { return Waypost_Render::menu(); }
}

class Waypost_Divi_Search extends ET_Builder_Module {
	use Waypost_Divi_Common;
	public $slug = 'et_pb_waypost_search';
	protected function waypost_name() { return __( 'Waypost Docs Search', 'waypost' ); }
	protected function waypost_output() { return Waypost_Render::search_box(); }
}

class Waypost_Divi_Toc extends ET_Builder_Module {
	use Waypost_Divi_Common;
	public $slug = 'et_pb_waypost_toc';
	protected function waypost_name() { return __( 'Waypost On This Page', 'waypost' ); }
	protected function waypost_output() { return Waypost_Render::toc(); }
}

class Waypost_Divi_Pager extends ET_Builder_Module {
	use Waypost_Divi_Common;
	public $slug = 'et_pb_waypost_prev_next';
	protected function waypost_name() { return __( 'Waypost Previous / Next', 'waypost' ); }
	protected function waypost_output() { return Waypost_Render::prev_next(); }
}

class Waypost_Divi_Crumbs extends ET_Builder_Module {
	use Waypost_Divi_Common;
	public $slug = 'et_pb_waypost_breadcrumbs';
	protected function waypost_name() { return __( 'Waypost Breadcrumbs', 'waypost' ); }
	protected function waypost_output() { return Waypost_Render::breadcrumbs(); }
}
